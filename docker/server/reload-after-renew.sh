#!/bin/sh
# Certbot deploy hook. After a certificate is issued or renewed it
#   1. when the certificate is VPN_HOSTNAME's, copies its key pair into docker/server/certs/, the only certificate
#      the `web` container can see (README, "HTTPS over the VPN"), and
#   2. reloads both Nginxes: the host's own (the public entrance's certificate) and the one in the `web` container,
#      which does not otherwise notice a renewed file.
# Certbot runs every script here after it successfully issues or renews ANY certificate, so this one hook covers
# both of this app's certificates:
#   - the public entrance's, renewed automatically by Certbot's own timer (HTTP-01) — this fires on its own, and
#     only reloads: the `web` container never gets that certificate.
#   - VPN_HOSTNAME's (README, "HTTPS over the VPN"), which has no DNS provider API unless the domain is on Cloudflare,
#     and then cannot renew unattended — this only fires when the manual command is re-run (every 60-90 days; see
#     the README), but fires the same way once it succeeds, so there is nothing extra to remember beyond the renewal.
#
# Install once, on the server (not run from the repo):
#   cp docker/server/reload-after-renew.sh /etc/letsencrypt/renewal-hooks/deploy/hr-app.sh
#   chmod +x /etc/letsencrypt/renewal-hooks/deploy/hr-app.sh
# Then `certbot renew --dry-run` should show it running. Update REPO_DIR below if the clone lives somewhere else.
#
# To put the pair in place without a renewal (the first time, or on a server that already had the VPN https), run it by
# hand with the two variables Certbot would set:
#   RENEWED_LINEAGE=/etc/letsencrypt/live/<vpn-host> RENEWED_DOMAINS=<vpn-host> /etc/letsencrypt/renewal-hooks/deploy/hr-app.sh
set -eu

REPO_DIR=/opt/hr-application
CERT_DIR="$REPO_DIR/docker/server/certs"

# The value of VPN_HOSTNAME in the stack's env file, without quotes or trailing spaces. Empty when it is not set.
vpn_hostname=""
if [ -f "$REPO_DIR/docker/server/.env" ]; then
    vpn_hostname="$(sed -n 's/^VPN_HOSTNAME=//p' "$REPO_DIR/docker/server/.env" | tail -n 1 | tr -d '\r"'"'"' ')"
fi

# RENEWED_DOMAINS is the space-separated list of names on the certificate Certbot just issued.
if [ -n "$vpn_hostname" ] && [ -n "${RENEWED_LINEAGE:-}" ]; then
    case " ${RENEWED_DOMAINS:-} " in
        *" $vpn_hostname "*)
            mkdir -p "$CERT_DIR"
            # cp -L follows Certbot's live/ symlinks and copies the files themselves. Written under a temporary name
            # and moved into place, so Nginx never reads half a file.
            cp -L "$RENEWED_LINEAGE/fullchain.pem" "$CERT_DIR/fullchain.pem.new"
            cp -L "$RENEWED_LINEAGE/privkey.pem" "$CERT_DIR/privkey.pem.new"
            chmod 644 "$CERT_DIR/fullchain.pem.new"
            chmod 600 "$CERT_DIR/privkey.pem.new"
            mv -f "$CERT_DIR/fullchain.pem.new" "$CERT_DIR/fullchain.pem"
            mv -f "$CERT_DIR/privkey.pem.new" "$CERT_DIR/privkey.pem"
            ;;
    esac
fi

nginx -t && nginx -s reload

docker compose -f "$REPO_DIR/docker-compose.server.yml" --env-file "$REPO_DIR/docker/server/.env" exec -T web nginx -s reload
