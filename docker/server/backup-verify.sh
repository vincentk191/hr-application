#!/usr/bin/env bash
# Proves a backup can be restored (README, "Backups"): run it every quarter, and after changing the key or the remote.
#
#   bash docker/server/backup-verify.sh <backup folder> <age private key file>
#
# Best run on your own computer, not the server: it needs the private key, which must never be on the server, and
# running it there from the downloaded copy also proves the copy that left the server is the one you can read. Needs
# Docker, age and bash.
#
# It checks the checksums, decrypts all four files, restores the database into a throwaway PostgreSQL container (the live
# database is never touched), counts rows in the main tables, and lists the other archives. Exit 0 and "OK" at the end
# mean the backup is good.
set -Eeuo pipefail

RUN_DIR="${1:?usage: backup-verify.sh <backup folder> <age private key file>}"
KEY="${2:?usage: backup-verify.sh <backup folder> <age private key file>}"
POSTGRES_IMAGE="${POSTGRES_IMAGE:-postgres:16-alpine}"
NAME="hr-backup-verify-$$"

for tool in age docker tar sha256sum; do
    command -v "$tool" >/dev/null || { echo "verify: '$tool' is not installed" >&2; exit 2; }
done
[ -f "$KEY" ] || { echo "verify: key file $KEY not found" >&2; exit 2; }
cd "$RUN_DIR"

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; }
trap cleanup EXIT

echo "1/5 checksums"
sha256sum -c SHA256SUMS

echo "2/5 restoring the database into a throwaway container"
docker run -d --rm --name "$NAME" -e POSTGRES_PASSWORD=verify "$POSTGRES_IMAGE" >/dev/null
for _ in $(seq 1 60); do
    docker exec "$NAME" pg_isready -U postgres >/dev/null 2>&1 && break
    sleep 1
done
docker exec "$NAME" createdb -U postgres verify
age -d -i "$KEY" database.dump.age | docker exec -i "$NAME" pg_restore -U postgres -d verify --no-owner --no-privileges --exit-on-error

echo "3/5 rows in the main tables"
for table in companies users employees employee_documents leave_requests migrations; do
    count="$(docker exec "$NAME" psql -U postgres -d verify -At -c "select count(*) from \"$table\"" 2>/dev/null || echo 'MISSING')"
    printf '    %-20s %s\n' "$table" "$count"
    [ "$count" != "MISSING" ] || { echo "verify: table $table is not in the restored database" >&2; exit 1; }
done

echo "4/5 documents and photos, VPN keys"
printf '    storage:   %s files\n' "$(age -d -i "$KEY" storage.tar.gz.age | tar -tzf - | grep -vc '/$' || true)"
printf '    wireguard: %s files\n' "$(age -d -i "$KEY" wireguard.tar.gz.age | tar -tzf - | grep -vc '/$' || true)"

echo "5/5 settings"
age -d -i "$KEY" env.age | grep -q '^APP_KEY=base64:' || { echo "verify: no APP_KEY in env.age" >&2; exit 1; }
echo "    APP_KEY present"

echo "OK: $RUN_DIR can be restored with this key."
