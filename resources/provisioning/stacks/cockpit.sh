#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"

crava_require_os ubuntu debian rocky centos

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_ensure_swap 2048
crava_pkg_install cockpit

systemctl enable --now cockpit.socket

crava_ufw_allow 22/tcp
crava_ufw_allow 9090/tcp
crava_wait_port 9090 60 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Cockpit Web Console aktif di https://<IP>:9090."
echo "CRAVA_INSTALL_OK {{stack}}"
