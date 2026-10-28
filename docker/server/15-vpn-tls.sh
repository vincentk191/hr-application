#!/bin/sh
# Runs when the web container starts, before the image's own step that fills the templates (20-envsubst-on-templates.sh).
# Adds the VPN https server block only if it can work: VPN_HOSTNAME is set and its certificate pair is in /etc/hr-certs
# (put there by the Certbot deploy hook, docker/server/reload-after-renew.sh; this container sees nothing else of the
# host's certificates). Otherwise Nginx starts without it (the plain-HTTP entrance and the host's own https are
# unaffected) and says why.
set -e

CERT_DIR=/etc/hr-certs

if [ -z "${VPN_HOSTNAME:-}" ]; then
    echo "15-vpn-tls: VPN_HOSTNAME is not set; HTTPS over the VPN is off."
elif [ ! -s "$CERT_DIR/fullchain.pem" ] || [ ! -s "$CERT_DIR/privkey.pem" ]; then
    echo "15-vpn-tls: no certificate in $CERT_DIR (docker/server/certs on the host); HTTPS over the VPN is off (README, step 7.5)."
else
    cp /opt/hr/vpn-tls.conf.template /etc/nginx/templates/vpn-tls.conf.template
    echo "15-vpn-tls: HTTPS over the VPN is on for ${VPN_HOSTNAME}."
fi
