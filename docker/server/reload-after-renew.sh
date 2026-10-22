#!/bin/sh
# Certbot deploy hook: reloads both Nginxes after a renewal, so the new certificate reaches the host's own Nginx
# and the `web` container (which mounts /etc/letsencrypt read-only and does not otherwise notice a renewed file).
# Certbot runs every script here after it successfully issues or renews ANY certificate, so this one hook covers
# both of this app's certificates:
#   - the public entrance's, renewed automatically by Certbot's own timer (HTTP-01) — this fires on its own.
#   - VPN_HOSTNAME's (README, "HTTPS over the VPN"), which has no DNS provider API and so cannot renew
#     unattended — this only fires when that manual command is re-run (every 60-90 days; see the README), but
#     fires the same way once it succeeds, so there is nothing extra to remember beyond the renewal itself.
#
# Install once, on the server (not run from the repo):
#   cp docker/server/reload-after-renew.sh /etc/letsencrypt/renewal-hooks/deploy/hr-app.sh
#   chmod +x /etc/letsencrypt/renewal-hooks/deploy/hr-app.sh
# Then `certbot renew --dry-run` should show it running. Update REPO_DIR below if the clone lives somewhere else.
set -eu

REPO_DIR=/opt/hr-application

nginx -t && nginx -s reload

docker compose -f "$REPO_DIR/docker-compose.server.yml" --env-file "$REPO_DIR/docker/server/.env" exec -T web nginx -s reload
