#!/usr/bin/env bash
# Nightly backup of the server stack (README, "Backups"). Run by cron on the HOST, as root:
#   30 2 * * *  root  bash /opt/hr-application/docker/server/backup.sh >> /var/log/hr-backup.log 2>&1
#
# One run makes one folder, $BACKUP_DIR/YYYY-MM-DD_HHMMSS, holding four encrypted files:
#   database.dump.age     the PostgreSQL database (pg_dump, custom format)
#   storage.tar.gz.age    the uploaded documents, photos, logos and leave attachments (storage/app)
#   wireguard.tar.gz.age  the VPN's server key and every client's key and address
#   env.age               docker/server/.env. Its APP_KEY decrypts the 2FA secrets stored in the database, so a
#                         restored database without it would lock every elevated account out.
# plus SHA256SUMS and MANIFEST.txt (names, sizes, times: nothing secret).
#
# Everything is encrypted with age, to a PUBLIC key (AGE_RECIPIENT). The matching private key is never on this server, so
# someone who takes this server, or the copy kept elsewhere, cannot read the backups. Each file is encrypted as it is
# produced, straight from the pipe: no unencrypted copy of the data is ever written to the disk.
#
# Then the folder is copied off the server with rclone (BACKUP_REMOTE) and checked there. Without a remote this still
# protects against a mistake, but not against losing the server, and it says so. If that copy fails, the backup made on
# this server is kept, and the run reports the failure.
#
# SETTINGS come from two places. docker/server/backup.env is the server's own file. The admin page "Cadangan data" saves
# the same settings in the database (app_settings, keys backup.*), and where it has a value it wins; a blank there means
# "use backup.env". The page is a web form feeding a root cron job, so nothing it saves is trusted: every value is
# checked here with the same patterns as app/Support/Backup/BackupSettings.php, and a value that does not match is
# ignored (with a warning in the report) in favour of backup.env. Set BACKUP_IGNORE_PAGE=true in backup.env to take the
# page out of the picture altogether.
#
# After each run it writes a short report to the database (key backup.status) for the admin page to show.
set -Eeuo pipefail
umask 077

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="${REPO_DIR:-$(cd "$HERE/../.." && pwd)}"
COMPOSE_FILE="${COMPOSE_FILE:-$REPO_DIR/docker-compose.server.yml}"
STACK_ENV="${STACK_ENV:-$HERE/.env}"
BACKUP_CONFIG="${BACKUP_CONFIG:-$HERE/backup.env}"

if [ -f "$BACKUP_CONFIG" ]; then
    set -a
    # shellcheck disable=SC1090
    . "$BACKUP_CONFIG"
    set +a
fi

BACKUP_DIR="${BACKUP_DIR:-/var/backups/hr-app}"
BACKUP_KEEP_DAYS="${BACKUP_KEEP_DAYS:-7}"
BACKUP_REMOTE="${BACKUP_REMOTE:-}"
BACKUP_REMOTE_KEEP_DAYS="${BACKUP_REMOTE_KEEP_DAYS:-30}"
BACKUP_OK_URL="${BACKUP_OK_URL:-}"
BACKUP_FAIL_URL="${BACKUP_FAIL_URL:-}"
BACKUP_IGNORE_PAGE="${BACKUP_IGNORE_PAGE:-false}"
AGE_RECIPIENT="${AGE_RECIPIENT:-}"

# Where each effective setting came from, for the report.
SRC_RECIPIENT='file'
SRC_REMOTE='file'

# What a value from the admin page must look like. The same patterns as BackupSettings.php; keep them the same.
RE_RECIPIENT='^age1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{58}$'
RE_REMOTE='^[A-Za-z0-9_][A-Za-z0-9_.-]*:[A-Za-z0-9_./-]*$' # also no ".." (checked below)
RE_URL="^https://[A-Za-z0-9._~:/?#@!&'()*+,;=%-]{1,500}\$"
RE_DAYS='^[0-9]{1,4}$'

ENABLED=true
WARNINGS=""
STEP="starting"
STAMP=""
RUN_DIR=""
LOCAL_COMPLETE=false

log() { echo "$(date -Is) backup: $*"; }
warn() {
    log "WARNING: $*"
    WARNINGS="${WARNINGS:+$WARNINGS; }$*"
}

# A monitoring ping that cannot be delivered must never fail or hide the backup itself. https only, and a redirect may
# not leave https either.
ping_url() {
    if [ -n "${1:-}" ]; then
        curl -fsS -m 15 --retry 3 --proto '=https' --proto-redir '=https' -o /dev/null "$1" \
            || log "could not reach the monitoring URL (the backup itself is unaffected)"
    fi
}

compose() { docker compose -f "$COMPOSE_FILE" --env-file "$STACK_ENV" "$@"; }
encrypt() { age -r "$AGE_RECIPIENT"; }

# Writes the report the admin page shows (app_settings, key backup.status). Values travel as psql variables, so they
# are quoted by psql and never become part of the SQL text. Failing to report must not fail the backup.
#   $1 ok (true|false)   $2 message   $3 paused (true|false)   $4 remote_ok (true|false|"" for no remote)
report_status() {
    local message="${2//$'\n'/ }"
    message="${message:0:300}"
    local bytes=""
    if [ -n "$RUN_DIR" ] && [ -d "$RUN_DIR" ]; then bytes="$(du -sb "$RUN_DIR" | cut -f1)"; fi
    local remotes=""
    if command -v rclone >/dev/null 2>&1; then remotes="$(rclone listremotes 2>/dev/null | tr -d ':' | paste -sd, - | cut -c1-300 || true)"; fi

    # shellcheck disable=SC2016  # single quotes on purpose: the variables expand inside the container, not here
    compose exec -T \
        -e BK_FINISHED="$(date -u +%Y-%m-%dT%H:%M:%SZ)" -e BK_OK="$1" -e BK_MESSAGE="$message" -e BK_PAUSED="$3" \
        -e BK_REMOTE_OK="$4" -e BK_FOLDER="$STAMP" -e BK_BYTES="$bytes" -e BK_REMOTES="$remotes" \
        -e BK_RECIPIENT="$AGE_RECIPIENT" -e BK_RECIPIENT_SRC="$SRC_RECIPIENT" -e BK_REMOTE="$BACKUP_REMOTE" -e BK_REMOTE_SRC="$SRC_REMOTE" \
        -e BK_KEEP="$BACKUP_KEEP_DAYS" -e BK_RKEEP="$BACKUP_REMOTE_KEEP_DAYS" -e BK_WARNINGS="$WARNINGS" \
        db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -v ON_ERROR_STOP=1 \
            -v finished="$BK_FINISHED" -v ok="$BK_OK" -v message="$BK_MESSAGE" -v paused="$BK_PAUSED" -v remote_ok="$BK_REMOTE_OK" \
            -v folder="$BK_FOLDER" -v bytes="$BK_BYTES" -v remotes="$BK_REMOTES" -v recipient="$BK_RECIPIENT" \
            -v recipient_src="$BK_RECIPIENT_SRC" -v remote="$BK_REMOTE" -v remote_src="$BK_REMOTE_SRC" \
            -v keep="$BK_KEEP" -v rkeep="$BK_RKEEP" -v warnings="$BK_WARNINGS" -f -' >/dev/null 2>&1 <<'SQL' \
        || log "could not report the result to the admin page"
insert into app_settings (key, value, created_at, updated_at)
values ('backup.status', json_build_object(
    'finished_at', :'finished',
    'ok', :'ok'::boolean,
    'paused', :'paused'::boolean,
    'remote_ok', nullif(:'remote_ok', '')::boolean,
    'message', :'message',
    'folder', :'folder',
    'bytes', nullif(:'bytes', '')::bigint,
    'last_success_at', case
        when :'ok'::boolean and not :'paused'::boolean then :'finished'
        else coalesce((select value::jsonb ->> 'last_success_at' from app_settings where key = 'backup.status'), '')
    end,
    'remotes', to_json(string_to_array(nullif(:'remotes', ''), ',')),
    'warnings', :'warnings',
    'effective', json_build_object(
        'recipient', :'recipient', 'recipient_source', :'recipient_src',
        'remote', :'remote', 'remote_source', :'remote_src',
        'keep_days', nullif(:'keep', '')::int, 'remote_keep_days', nullif(:'rkeep', '')::int
    )
), now(), now())
on conflict (key) do update set value = excluded.value, updated_at = now();
SQL
}

on_error() {
    local code=$? line=$1
    trap - ERR
    log "FAILED at line $line while: $STEP (exit $code)"
    if [ "$LOCAL_COMPLETE" = false ] && [ -n "$RUN_DIR" ]; then
        log "removing the unfinished folder"
        rm -rf "$RUN_DIR"
    fi
    report_status false "failed while: $STEP" false ""
    ping_url "$BACKUP_FAIL_URL"
    exit "$code"
}
trap 'on_error $LINENO' ERR

# Fails the run (through the ERR trap) when a file is missing or smaller than a real result could be.
require_size() {
    local size
    size="$(stat -c %s "$1")"
    if [ "$size" -lt "$2" ]; then
        log "$1 is only $size bytes: the source produced nothing"
        return 1
    fi
}

# A destination must be a remote that rclone config set up on this server. A plain folder (a mounted disk) is also
# accepted, but only from backup.env: the admin page's values have to match RE_REMOTE, which has no such form.
remote_is_usable() {
    case "$1" in
        /*) [ "$SRC_REMOTE" = "file" ] ;;
        *) rclone listremotes | grep -Fxq "${1%%:*}:" ;;
    esac
}

# --- the admin page's settings ----------------------------------------------------------------------------------------

# Sets $2 from $1 when it matches the pattern $3; otherwise warns. A blank means "not set on the page".
adopt_text() { # value, variable, pattern, source variable, label
    local value="$1" variable="$2" pattern="$3" source_variable="$4" label="$5"
    [ -n "$value" ] || return 0
    if [[ $value =~ $pattern ]] && [[ $value != *..* ]]; then
        printf -v "$variable" '%s' "$value"
        printf -v "$source_variable" '%s' page
    else
        warn "the $label saved on the admin page is not valid and was ignored"
    fi
}

adopt_days() { # value, variable, label, max
    local value="$1" variable="$2" label="$3" max="$4"
    [ -n "$value" ] || return 0
    if [[ $value =~ $RE_DAYS ]] && [ "$((10#$value))" -ge 1 ] && [ "$((10#$value))" -le "$max" ]; then
        printf -v "$variable" '%s' "$((10#$value))"
    else
        warn "the $label saved on the admin page is not valid and was ignored"
    fi
}

adopt_url() { # value, variable, label
    local value="$1" variable="$2" label="$3"
    [ -n "$value" ] || return 0
    if [[ $value =~ $RE_URL ]]; then
        printf -v "$variable" '%s' "$value"
    else
        warn "the $label saved on the admin page is not valid and was ignored"
    fi
}

read_page_settings() {
    local rows key value
    # shellcheck disable=SC2016  # single quotes on purpose: the variables expand inside the container, not here
    # Key and value are separated by the control character 0x1f. A value that itself holds a control character is never
    # returned, so a value cannot add lines or fields of its own. An unreachable database leaves the rows empty.
    rows="$(compose exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At' 2>/dev/null <<'SQL' || true
select key || chr(31) || coalesce(value::jsonb #>> '{}', '')
from app_settings
where key in ('backup.enabled', 'backup.age_recipient', 'backup.remote', 'backup.keep_days', 'backup.remote_keep_days',
              'backup.ok_url', 'backup.fail_url')
  and coalesce(value::jsonb #>> '{}', '') !~ '[[:cntrl:]]'
SQL
)"

    while IFS=$'\x1f' read -r key value; do
        case "$key" in
            backup.enabled) [ "$value" != "false" ] || ENABLED=false ;;
            backup.age_recipient) adopt_text "$value" AGE_RECIPIENT "$RE_RECIPIENT" SRC_RECIPIENT "encryption key" ;;
            backup.remote) adopt_text "$value" BACKUP_REMOTE "$RE_REMOTE" SRC_REMOTE "copy destination" ;;
            backup.keep_days) adopt_days "$value" BACKUP_KEEP_DAYS "days kept on the server" 365 ;;
            backup.remote_keep_days) adopt_days "$value" BACKUP_REMOTE_KEEP_DAYS "days kept off the server" 730 ;;
            backup.ok_url) adopt_url "$value" BACKUP_OK_URL "success monitoring address" ;;
            backup.fail_url) adopt_url "$value" BACKUP_FAIL_URL "failure monitoring address" ;;
        esac
    done <<<"$rows"
}

# --- checks, lock, settings -------------------------------------------------------------------------------------------

for tool in age docker flock curl sha256sum; do
    command -v "$tool" >/dev/null || { echo "backup: '$tool' is not installed" >&2; exit 2; }
done
[ -f "$STACK_ENV" ] || { echo "backup: $STACK_ENV not found" >&2; exit 2; }

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
exec 9>"$BACKUP_DIR/.lock"
flock -n 9 || { log "another backup is still running; skipping this one"; exit 1; }

STEP="reading the settings"
if [ "$BACKUP_IGNORE_PAGE" != "true" ]; then
    read_page_settings
fi

if [ "$ENABLED" = false ]; then
    log "paused from the admin page: no backup made"
    report_status true "paused from the admin page" true ""
    exit 0
fi

: "${AGE_RECIPIENT:?set AGE_RECIPIENT (the public key, age1...) in $BACKUP_CONFIG or on the admin page}"
if [ -n "$BACKUP_REMOTE" ]; then
    command -v rclone >/dev/null || { echo "backup: a copy destination is set but 'rclone' is not installed" >&2; exit 2; }
fi

STAMP="$(date +%Y-%m-%d_%H%M%S)"
# mkdir comes before RUN_DIR is set, on purpose: the error handler deletes RUN_DIR, so it must only ever hold a folder
# this run created. (A folder that already exists makes mkdir fail here, and then nothing is deleted.)
mkdir "$BACKUP_DIR/$STAMP"
RUN_DIR="$BACKUP_DIR/$STAMP"
log "started $STAMP (key from ${SRC_RECIPIENT}, destination from ${SRC_REMOTE})"

# --- the backup -------------------------------------------------------------------------------------------------------

# pg_dump takes one consistent snapshot while the app keeps running. The role and database names come from the db
# container's own environment, so they are never repeated here.
STEP="dumping the database"
log "database"
# shellcheck disable=SC2016  # single quotes on purpose: the variables expand inside the container, not here
compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' | encrypt >"$RUN_DIR/database.dump.age"
require_size "$RUN_DIR/database.dump.age" 4096

# tar exits 1 when a file changed while it was read (the app may be writing one); that is still a usable archive.
STEP="archiving documents and photos"
log "documents and photos"
compose exec -T app sh -c 'tar -czf - -C /var/www/backend/storage app; [ $? -le 1 ]' | encrypt >"$RUN_DIR/storage.tar.gz.age"
require_size "$RUN_DIR/storage.tar.gz.age" 100

STEP="archiving the VPN data"
log "wireguard"
compose exec -T wg sh -c 'tar -czf - -C /etc/wireguard .; [ $? -le 1 ]' | encrypt >"$RUN_DIR/wireguard.tar.gz.age"
require_size "$RUN_DIR/wireguard.tar.gz.age" 100

STEP="encrypting the settings"
log "settings"
encrypt <"$STACK_ENV" >"$RUN_DIR/env.age"
require_size "$RUN_DIR/env.age" 100

STEP="writing the checksums"
(cd "$RUN_DIR" && sha256sum ./*.age >SHA256SUMS)
{
    echo "created:  $(date -Is)"
    echo "host:     $(hostname)"
    echo "commit:   $(git -C "$REPO_DIR" rev-parse --short HEAD 2>/dev/null || echo unknown)"
    echo "postgres: $(compose exec -T db postgres --version | tr -d '\r')"
    echo "key:      $AGE_RECIPIENT"
    echo
    (cd "$RUN_DIR" && ls -l ./*.age)
} >"$RUN_DIR/MANIFEST.txt"

# From here the backup on this server is good and stays, whatever happens to the copy.
LOCAL_COMPLETE=true

# --- the copy off the server ------------------------------------------------------------------------------------------

REMOTE_OK=""
MESSAGE="done"
if [ -n "$BACKUP_REMOTE" ]; then
    STEP="copying off the server"
    REMOTE="${BACKUP_REMOTE%/}"
    log "copying to $REMOTE/$STAMP"
    # Chained with && on purpose: inside `if`, bash ignores errors, so a failed step must be what ends the chain.
    # `check` reads the files back from the other side and compares them, so "copied" means "arrived intact". Only
    # this script's own file types are ever removed, and only below the destination, so a folder shared with other
    # things loses nothing else (still, give the backups a path of their own).
    if remote_is_usable "$REMOTE" \
        && rclone copy "$RUN_DIR" "$REMOTE/$STAMP" --checksum \
        && rclone check "$RUN_DIR" "$REMOTE/$STAMP" --one-way \
        && rclone delete "$REMOTE" --min-age "${BACKUP_REMOTE_KEEP_DAYS}d" \
            --include '*.age' --include 'SHA256SUMS' --include 'MANIFEST.txt' \
        && rclone rmdirs "$REMOTE" --leave-root; then
        REMOTE_OK=true
    else
        REMOTE_OK=false
        MESSAGE="the backup is on this server, but the copy to ${REMOTE%%:*}: failed (is the remote set up with rclone config, and reachable?)"
        log "ERROR: $MESSAGE"
    fi
else
    warn "no copy destination is set, so these backups exist only on this server"
fi

STEP="removing old backups on this server"
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name '20??-??-??_[0-9][0-9][0-9][0-9]*' -mtime +"$BACKUP_KEEP_DAYS" -exec rm -rf {} +

trap - ERR
log "done: $(du -sh "$RUN_DIR" | cut -f1) in $RUN_DIR"

if [ "$REMOTE_OK" = false ]; then
    report_status true "$MESSAGE" false false
    ping_url "$BACKUP_FAIL_URL"
    exit 1
fi

report_status true "$MESSAGE" false "$REMOTE_OK"
ping_url "$BACKUP_OK_URL"
