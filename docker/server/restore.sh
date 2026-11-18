#!/usr/bin/env bash
# Restores a backup onto the server (README, "Backups", "Restore"). Run it on YOUR computer, not the server: it needs the
# private key, which must never be on the server, and it streams everything decrypted straight into the containers, so
# nothing decrypted is ever written to the server's disk.
#
#   bash docker/server/restore.sh <backup folder> <age private key file> <user@server> [options]
#
#   --parts LIST         what to restore, comma separated: database, files, vpn, settings. Without it you are asked.
#   --dir PATH           the clone on the server (default /opt/hr-application)
#   --dry-run            check the backup, the key and the server, then stop. Nothing on the server is changed.
#   --no-safety-copy     do not take an encrypted copy of the live database first (see below)
#   --yes                do not ask for confirmation (for a script; you are then sure)
#   -h, --help           this text
#
# The four parts, matching the four files in a backup folder:
#   database   database.dump.age     REPLACES everything in the database with the backup's. A mistaken deletion is usually
#                                    only this.
#   files      storage.tar.gz.age    uploaded documents, photos, logos, attachments. Added over what is there: files made
#                                    after the backup are kept, files with the same name are overwritten.
#   vpn        wireguard.tar.gz.age  WireGuard's server key and every client. Restarts the VPN.
#   settings   env.age               docker/server/.env. Only for a rebuilt server, and only on its own, before the stack
#                                    is first started (an existing .env is kept as .env.pre-restore).
#
# Before the database is replaced, an encrypted dump of what is there now is written NEXT TO the backup folder on your
# computer (pre-restore-<time>.dump.age, to the same key), so a restore that turns out to be the wrong backup can be
# undone. Delete it when you are satisfied: it holds the live data.
#
# Needs bash, age (with age-keygen), ssh and sha256sum on your computer. On Windows use Git Bash (Start menu), not
# PowerShell: `bash` there is the WSL launcher, and PowerShell 5.1 corrupts binary data piped between programs. The login
# on the server must be allowed to run docker. Use an ssh key or agent, so you are not asked for a password at every step.
#
# Environment:
#   RESTORE_COMPOSE_FILES   compose files on the server, relative to --dir, space separated (default docker-compose.server.yml;
#                           the Tailscale setup: "docker-compose.server.yml docker/server/local/docker-compose.tailscale.yml")
#   RESTORE_SSH_OPTS        extra ssh options, for example "-i ~/.ssh/hr-server -p 2222"

# No -E on purpose: the ERR trap below must run once, in the main shell, not again inside the ssh helper functions.
set -euo pipefail

SERVER_DIR="/var/www/hr-application"
COMPOSE_FILES="${RESTORE_COMPOSE_FILES:-docker-compose.server.yml}"
SSH_OPTS="${RESTORE_SSH_OPTS:-}"
PARTS=""
DRY_RUN=false
SAFETY_COPY=true
ASSUME_YES=false

[ "${BASH_VERSINFO[0]}" -ge 4 ] || { echo "restore: needs bash 4 or newer (on macOS: brew install bash)" >&2; exit 2; }

usage() { awk 'NR > 1 && /^#/ { sub(/^# ?/, ""); print; next } NR > 1 { exit }' "$0"; }
die() { echo "restore: $*" >&2; exit 2; }
say() { printf '\n== %s\n' "$*"; }
# So a command shown to the user can be pasted inside double quotes as printed.
escape_for_double_quotes() { local text="$1"; text="${text//\\/\\\\}"; text="${text//\"/\\\"}"; text="${text//\$/\\\$}"; printf '%s' "$text"; }

# --- arguments ------------------------------------------------------------------------------------------------------------

POSITIONAL=()
while [ $# -gt 0 ]; do
    case "$1" in
        --parts) [ $# -ge 2 ] || die "--parts needs a value"; PARTS="$2"; shift 2 ;;
        --dir) [ $# -ge 2 ] || die "--dir needs a value"; SERVER_DIR="$2"; shift 2 ;;
        --dry-run) DRY_RUN=true; shift ;;
        --no-safety-copy) SAFETY_COPY=false; shift ;;
        --yes) ASSUME_YES=true; shift ;;
        -h | --help) usage; exit 0 ;;
        --*) die "unknown option $1 (see --help)" ;;
        *) POSITIONAL+=("$1"); shift ;;
    esac
done
[ "${#POSITIONAL[@]}" -eq 3 ] || { usage >&2; exit 2; }
RUN_DIR="${POSITIONAL[0]}"
KEY="${POSITIONAL[1]}"
SERVER="${POSITIONAL[2]}"

# These end up inside a command run on the server, or as an ssh argument, so they may only look like what they are.
[[ "$SERVER" =~ ^[A-Za-z0-9_][A-Za-z0-9_.@:-]*$ ]] || die "the server must look like user@host"
[[ "$SERVER_DIR" =~ ^/[A-Za-z0-9_./-]+$ ]] || die "--dir must be an absolute path with only letters, digits and _ . / -"
[[ "$COMPOSE_FILES" =~ ^[A-Za-z0-9_./\ -]+$ ]] || die "RESTORE_COMPOSE_FILES may only hold file names"

for tool in age age-keygen ssh; do
    command -v "$tool" >/dev/null || die "'$tool' is not installed (on Windows: winget install FiloSottile.age, then open a new terminal)"
done
if command -v sha256sum >/dev/null; then
    SHA_CHECK=(sha256sum -c --ignore-missing SHA256SUMS)
elif command -v shasum >/dev/null; then
    SHA_CHECK=(shasum -a 256 -c SHA256SUMS)
else
    die "neither sha256sum nor shasum is installed"
fi
[ -f "$KEY" ] || die "key file $KEY not found"
[ -d "$RUN_DIR" ] || die "backup folder $RUN_DIR not found"
RUN_DIR="$(cd "$RUN_DIR" && pwd)"
KEY="$(cd "$(dirname "$KEY")" && pwd)/$(basename "$KEY")"
[ -f "$RUN_DIR/SHA256SUMS" ] || die "$RUN_DIR has no SHA256SUMS: is it a backup folder?"

# Ask, unless --parts was given.
if [ -z "$PARTS" ]; then
    [ -t 0 ] || die "no --parts given and this is not a terminal to ask in"
    cat <<'MENU'

What do you want to restore?
  1) database   the PostgreSQL data: employees, requests, accounts, settings. REPLACES what is there now.
  2) files      uploaded documents, photos, logos, attachments. Added over what is there.
  3) vpn        WireGuard's server key and clients. Restarts the VPN.
  4) settings   docker/server/.env. Only for a rebuilt server, and only on its own.
MENU
    read -r -p "Numbers separated by spaces (for example 1 2): " ANSWER
    for n in $ANSWER; do
        case "$n" in
            1) PARTS="$PARTS,database" ;;
            2) PARTS="$PARTS,files" ;;
            3) PARTS="$PARTS,vpn" ;;
            4) PARTS="$PARTS,settings" ;;
            *) die "'$n' is not one of 1 to 4" ;;
        esac
    done
    PARTS="${PARTS#,}"
fi

declare -A FILE_OF=([database]=database.dump.age [files]=storage.tar.gz.age [vpn]=wireguard.tar.gz.age [settings]=env.age)
declare -A WANT=()
IFS=',' read -r -a LIST <<<"$PARTS"
for part in "${LIST[@]}"; do
    [ -n "${FILE_OF[$part]:-}" ] || die "'$part' is not a part (database, files, vpn, settings)"
    WANT[$part]=true
    [ -f "$RUN_DIR/${FILE_OF[$part]}" ] || die "$RUN_DIR/${FILE_OF[$part]} is missing"
done
[ "${#WANT[@]}" -gt 0 ] || die "nothing chosen to restore"
if [ -n "${WANT[settings]:-}" ] && [ "${#WANT[@]}" -gt 1 ]; then
    die "'settings' must be restored on its own: on a rebuilt server put .env back first, run 'up -d --build', then restore the rest"
fi

# --- the server, as seen through ssh ----------------------------------------------------------------------------------------

COMPOSE_ARGS=""
for file in $COMPOSE_FILES; do COMPOSE_ARGS="$COMPOSE_ARGS -f $file"; done
REMOTE_DC="cd $SERVER_DIR && docker compose$COMPOSE_ARGS --env-file docker/server/.env"

# shellcheck disable=SC2086  # SSH_OPTS is meant to split into words
rsh() { ssh -n $SSH_OPTS -- "$SERVER" "$@"; }   # a command with nothing piped in
# shellcheck disable=SC2086
rpipe() { ssh $SSH_OPTS -- "$SERVER" "$@"; }    # a command that reads what is piped into it

# Single quotes on purpose: the variables expand inside the database container, not on either shell.
# shellcheck disable=SC2016
PG_RESTORE='pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --role="$APP_DB_USER" --clean --if-exists --no-owner --no-privileges --exit-on-error'
# shellcheck disable=SC2016
PG_DUMP='pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom'

STEP="starting"
SAFETY_FILE=""
APP_STOPPED=false
on_error() {
    echo >&2
    echo "restore: FAILED while: $STEP" >&2
    if [ "$APP_STOPPED" = true ]; then
        echo "restore: the application is still STOPPED and the database may be only partly restored." >&2
        if [ -n "$SAFETY_FILE" ] && [ -f "$SAFETY_FILE" ]; then
            echo "restore: to put back what was there before this run:" >&2
            echo "    age -d -i '$KEY' '$SAFETY_FILE' | ssh $SERVER \"$REMOTE_DC exec -T db sh -c '$(escape_for_double_quotes "$PG_RESTORE")'\"" >&2
        fi
        echo "restore: then start it again:  ssh $SERVER \"$REMOTE_DC up -d app queue scheduler\"" >&2
    fi
    exit 1
}
trap on_error ERR

# --- checks that change nothing ---------------------------------------------------------------------------------------------

say "the backup"
sed -n '1,5p' "$RUN_DIR/MANIFEST.txt" 2>/dev/null | sed 's/^/    /' || true
echo "    folder:   $RUN_DIR"
echo "    restoring: ${PARTS//,/, }"

STEP="checking the checksums"
say "1/3 checksums"
(cd "$RUN_DIR" && "${SHA_CHECK[@]}")

STEP="decrypting with the key"
say "2/3 the key opens the chosen files"
for part in "${!WANT[@]}"; do
    age -d -i "$KEY" "$RUN_DIR/${FILE_OF[$part]}" >/dev/null
    echo "    ${FILE_OF[$part]}: ok"
done

STEP="reaching the server"
say "3/3 the server"
rsh "test -d '$SERVER_DIR'" || die "cannot reach $SERVER over ssh, or $SERVER_DIR does not exist there"
if [ -z "${WANT[settings]:-}" ]; then
    rsh "test -f '$SERVER_DIR/docker/server/.env'" || die "$SERVER_DIR/docker/server/.env is missing on the server: restore 'settings' first"
    rsh "$REMOTE_DC ps --services" >/dev/null || die "docker compose does not answer on the server (is this login allowed to run docker?)"
fi
echo "    $SERVER:$SERVER_DIR answers"

if [ "$DRY_RUN" = true ]; then
    echo
    echo "dry run: the backup is intact, the key opens it and the server answers. Nothing was changed."
    exit 0
fi

# --- confirmation -----------------------------------------------------------------------------------------------------------

echo
echo "About to change $SERVER:$SERVER_DIR:"
[ -z "${WANT[database]:-}" ] || echo "  - DATABASE: stop app, queue and scheduler; REPLACE the database with the backup's; start them again"
[ -z "${WANT[files]:-}" ] || echo "  - FILES: unpack documents and photos over the live ones"
[ -z "${WANT[vpn]:-}" ] || echo "  - VPN: unpack WireGuard's keys and restart it (connected people are dropped briefly)"
[ -z "${WANT[settings]:-}" ] || echo "  - SETTINGS: write docker/server/.env from the backup (an existing one is kept as .env.pre-restore)"
if [ -n "${WANT[database]:-}" ]; then
    if [ "$SAFETY_COPY" = true ]; then
        echo "  A safety copy of the live database is saved on this computer first."
    else
        echo "  NO safety copy of the live database (--no-safety-copy): this cannot be undone."
    fi
fi
if [ "$ASSUME_YES" != true ]; then
    [ -t 0 ] || die "this is not a terminal to confirm in; pass --yes if you are sure"
    read -r -p "Type RESTORE to go ahead: " REPLY
    [ "$REPLY" = "RESTORE" ] || die "not confirmed, nothing was changed"
fi

# --- the restore ------------------------------------------------------------------------------------------------------------

if [ -n "${WANT[settings]:-}" ]; then
    STEP="writing docker/server/.env"
    say "settings"
    rsh "cd '$SERVER_DIR/docker/server' && umask 077 && { [ ! -e .env ] || mv .env .env.pre-restore; }"
    age -d -i "$KEY" "$RUN_DIR/env.age" | rpipe "umask 077 && cat > '$SERVER_DIR/docker/server/.env' && chmod 600 '$SERVER_DIR/docker/server/.env'"
    echo "    written. Next, on the server: cd $SERVER_DIR && docker compose$COMPOSE_ARGS --env-file docker/server/.env up -d --build"
    echo "    then run this script again for the database, files and vpn."
    rsh "test ! -e '$SERVER_DIR/docker/server/.env.pre-restore'" || echo "    the old .env was kept as docker/server/.env.pre-restore: delete it once you have compared them"
    echo
    echo "OK: settings restored."
    exit 0
fi

if [ -n "${WANT[database]:-}" ]; then
    if [ "$SAFETY_COPY" = true ]; then
        STEP="saving a safety copy of the live database"
        say "safety copy of the live database"
        SAFETY_FILE="$(dirname "$RUN_DIR")/pre-restore-$(date +%Y-%m-%d_%H%M%S).dump.age"
        PUBLIC_KEY="$(age-keygen -y "$KEY")"
        if ! rpipe "$REMOTE_DC exec -T db sh -c '$PG_DUMP'" | age -r "$PUBLIC_KEY" >"$SAFETY_FILE"; then
            rm -f "$SAFETY_FILE"
            die "the safety copy failed, so nothing was changed. If the database is empty or the stack is down on purpose, run again with --no-safety-copy"
        fi
        [ "$(wc -c <"$SAFETY_FILE")" -ge 1024 ] || { rm -f "$SAFETY_FILE"; die "the safety copy came out empty, so nothing was changed (--no-safety-copy skips it)"; }
        echo "    saved: $SAFETY_FILE"
    fi

    STEP="stopping app, queue and scheduler"
    say "database"
    rsh "$REMOTE_DC stop app queue scheduler"
    APP_STOPPED=true

    STEP="restoring the database (it replaces the live one)"
    age -d -i "$KEY" "$RUN_DIR/database.dump.age" | rpipe "$REMOTE_DC exec -T db sh -c '$PG_RESTORE'"

    STEP="starting app, queue and scheduler"
    rsh "$REMOTE_DC up -d app queue scheduler"
    APP_STOPPED=false
    echo "    restored; the application starts and applies any newer migrations by itself"
fi

if [ -n "${WANT[files]:-}" ] || [ -n "${WANT[vpn]:-}" ]; then
    STEP="waiting for the application container"
    for _ in $(seq 1 30); do
        rsh "$REMOTE_DC exec -T app true" >/dev/null 2>&1 && break
        sleep 2
    done
    rsh "$REMOTE_DC exec -T app true" >/dev/null 2>&1 || die "the app container is not running on the server, so files cannot be unpacked into it"
fi

if [ -n "${WANT[files]:-}" ]; then
    STEP="unpacking documents and photos"
    say "files"
    age -d -i "$KEY" "$RUN_DIR/storage.tar.gz.age" | rpipe "$REMOTE_DC exec -T app tar -xzf - -C /var/www/backend/storage"
    echo "    unpacked. Owners on the restored files (should be 33, the web user):"
    rsh "$REMOTE_DC exec -T app ls -ln /var/www/backend/storage/app/private" 2>/dev/null | sed -n '1,6p' | sed 's/^/    /' || true
fi

if [ -n "${WANT[vpn]:-}" ]; then
    STEP="unpacking the VPN data"
    say "vpn"
    age -d -i "$KEY" "$RUN_DIR/wireguard.tar.gz.age" | rpipe "$REMOTE_DC exec -T wg tar -xzf - -C /etc/wireguard"
    STEP="restarting the VPN"
    rsh "$REMOTE_DC restart wg"
fi

STEP="listing the containers"
say "state of the stack"
rsh "$REMOTE_DC ps" || true

echo
echo "OK: restored ${PARTS//,/, }."
echo "Now check, as a person would: sign in, open a document, and (if you restored the database) that recent data is as expected."
if [ -n "$SAFETY_FILE" ]; then
    echo "The live database as it was before this run is in $SAFETY_FILE (encrypted to your key). Delete it once you are satisfied."
fi
