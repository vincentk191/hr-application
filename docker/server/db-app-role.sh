#!/bin/sh
# Gives the application its own PostgreSQL login, one that is not a superuser (README, "The database logins").
#
# The postgres image runs everything in /docker-entrypoint-initdb.d once, when the database is first created
# (docker-compose.server.yml mounts this file there). At that point two roles are involved:
#
#   POSTGRES_USER  (DB_ADMIN_USERNAME, default hr_admin)  the superuser the image creates. It is used only inside this
#                  container: the backup script and the restore run `psql`/`pg_dump`/`pg_restore` through
#                  `docker compose exec db`, over the local socket, which needs no password.
#   APP_DB_USER    (DB_USERNAME, default hr_app)          what the application, the queue and the scheduler sign in as.
#                  It owns the database and everything in it, so migrations work, but it cannot run programs on the
#                  database host (COPY ... PROGRAM), read its files, or create roles, databases or extensions.
#
# Why it matters: a flaw in the application that lets someone run their own SQL would otherwise run as the superuser.
# The last step removes the superuser's password, so over the network it cannot sign in at all. (The image needs a
# password to create it, and gets DB_PASSWORD as a placeholder; no separate superuser password exists to leak into the app
# containers, which share one env file.)
#
# Safe to run again by hand on a database that already has the role: it resets the app login's password and flags. That is
# how a changed DB_PASSWORD reaches the database (README, "The database logins").
set -eu

: "${APP_DB_USER:?APP_DB_USER (DB_USERNAME) is not set}"
: "${APP_DB_PASSWORD:?APP_DB_PASSWORD (DB_PASSWORD) is not set}"

if [ "$APP_DB_USER" = "$POSTGRES_USER" ]; then
    echo "db-app-role: DB_USERNAME and DB_ADMIN_USERNAME are both '$APP_DB_USER'. They must differ: one is the application's" >&2
    echo "db-app-role: login, the other the superuser that stays inside the database container." >&2
    exit 1
fi

# The names and the password travel as psql variables, so they are quoted by psql (:'name') and by format() (%I, %L)
# and never become part of the SQL text.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -v app_user="$APP_DB_USER" -v app_password="$APP_DB_PASSWORD" -v admin_user="$POSTGRES_USER" <<'SQL'
SELECT format('CREATE ROLE %I', :'app_user')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'app_user') \gexec

SELECT format(
    'ALTER ROLE %I LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD %L',
    :'app_user', :'app_password'
) \gexec

SELECT format('ALTER DATABASE %I OWNER TO %I', current_database(), :'app_user') \gexec

SELECT format('ALTER ROLE %I PASSWORD NULL', :'admin_user') \gexec
SQL

echo "db-app-role: '$APP_DB_USER' owns '$POSTGRES_DB' and is not a superuser; '$POSTGRES_USER' has no password."
