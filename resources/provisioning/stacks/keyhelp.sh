#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl wget
crava_ensure_swap 2048

umask 077
ADMIN_PWD="$(openssl rand -hex 14)"
mkdir -p /root/.cravacloud
cat > /root/.cravacloud/keyhelp.env <<EOF
KEYHELP_ADMIN_USER=keyadmin
KEYHELP_ADMIN_PASSWORD=${ADMIN_PWD}
EOF
chmod 600 /root/.cravacloud/keyhelp.env

curl -fsSL "https://install.keyhelp.de/install_keyhelp.sh" -o /tmp/install_keyhelp.sh
chmod +x /tmp/install_keyhelp.sh
bash /tmp/install_keyhelp.sh --non-interactive --admin-username keyadmin --admin-password "${ADMIN_PWD}" --admin-email admin@cravacloud.local || true
rm -f /tmp/install_keyhelp.sh

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_port 80 120 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] KeyHelp aktif pada port 80/443. Kredensial tersimpan di /root/.cravacloud/keyhelp.env."
echo "CRAVA_INSTALL_OK {{stack}}"
