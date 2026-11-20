#!/usr/bin/env bash
# CravaCloud stack installer: serverkit (idempotent, _common.sh hardened).
# Official ServerKit panel installer on HTTP/HTTPS ports 80/443.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && systemctl is-active --quiet nginx \
    && ss -tln 2>/dev/null | grep -qE ':80([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian rocky

echo "== [1/3] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl openssl ufw
crava_ensure_swap 2048

echo "== [2/3] ServerKit (Official Installer) =="
CREDS_FILE=/root/.crava-serverkit.env
if [ ! -f "$CREDS_FILE" ]; then
    umask 077
    {
        printf 'SERVERKIT_ADMIN_USERNAME=cravaadmin\n'
        printf 'SERVERKIT_ADMIN_EMAIL=admin@cravacloud.local\n'
        printf 'SERVERKIT_ADMIN_PASSWORD=%s\n' "$(openssl rand -hex 16)"
    } > "$CREDS_FILE"
    chmod 600 "$CREDS_FILE"
fi
# shellcheck disable=SC1090
. "$CREDS_FILE"

umask 077
touch /var/log/cravacloud-serverkit-setup.log
chmod 600 /var/log/cravacloud-serverkit-setup.log

curl -fsSL https://serverkit.ai/install.sh -o /tmp/serverkit-install.sh
SERVERKIT_PROFILE=standard \
SERVERKIT_SKIP_SSL=1 \
SERVERKIT_SKIP_FIREWALL=1 \
SERVERKIT_ADMIN_EMAIL="$SERVERKIT_ADMIN_EMAIL" \
SERVERKIT_ADMIN_USERNAME="$SERVERKIT_ADMIN_USERNAME" \
SERVERKIT_ADMIN_PASSWORD="$SERVERKIT_ADMIN_PASSWORD" \
bash /tmp/serverkit-install.sh >/var/log/cravacloud-serverkit-setup.log 2>&1
rm -f /tmp/serverkit-install.sh

echo "== [3/3] Readiness Gate & Firewall =="
crava_wait_service nginx 120
crava_wait_port 80 120
crava_ufw_allow 22/tcp 80/tcp 443/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial admin ServerKit disimpan di /root/.crava-serverkit.env (root-only)."
