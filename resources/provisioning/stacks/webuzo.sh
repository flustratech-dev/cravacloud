#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"

crava_require_os ubuntu rocky

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates wget
crava_ensure_swap 2048

curl -fsSL "https://files.webuzo.com/install.sh" -o /tmp/webuzo-install.sh
chmod +x /tmp/webuzo-install.sh
/tmp/webuzo-install.sh --install=lamp || true
rm -f /tmp/webuzo-install.sh

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 2002/tcp
crava_ufw_allow 2003/tcp
crava_ufw_allow 2004/tcp
crava_ufw_allow 2005/tcp

crava_wait_port 2004 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Webuzo BYOL aktif di https://<IP>:2004. Tautkan lisensi Webuzo Anda di panel admin."
echo "CRAVA_INSTALL_OK {{stack}}"
