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
crava_pkg_install curl ca-certificates wget
crava_ensure_swap 2048

curl -fsSL "https://get.ispconfig.org" -o /tmp/ispconfig-install.sh
echo "yes" | sh /tmp/ispconfig-install.sh --use-nginx --use-php=8.2 --no-quota --no-mail --no-dns --unattended-upgrades || true
rm -f /tmp/ispconfig-install.sh

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 8080/tcp
crava_ufw_allow 8081/tcp

crava_wait_port 8080 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] ISPConfig aktif di https://<IP>:8080."
echo "CRAVA_INSTALL_OK {{stack}}"
