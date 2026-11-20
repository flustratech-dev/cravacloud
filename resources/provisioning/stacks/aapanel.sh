#!/usr/bin/env bash
# CravaCloud stack installer: aapanel (idempotent, _common.sh hardened).
# Admin credentials are configured by the customer on first-run web setup (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && [ -d /www/server/panel ] && ss -tln 2>/dev/null | grep -qE ':8888([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian rocky centos

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl wget
if [ "$CRAVA_PKG_MGR" = "apt" ]; then
    crava_pkg_install gnupg ufw
fi
crava_ensure_swap 2048

echo "== [2/4] aaPanel =="
if [ ! -d /www/server/panel ]; then
    crava_wait_pkg_lock 120
    curl -fsSL https://www.aapanel.com/script/install_7.0_en.sh -o /tmp/aapanel-install.sh
    printf 'y\n' | bash /tmp/aapanel-install.sh aapanel
    rm -f /tmp/aapanel-install.sh
fi

echo "== [3/4] Readiness Gate =="
crava_wait_port 8888 180

echo "== [4/4] Firewall & Finalize =="
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8888/tcp 888/tcp 20/tcp 21/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
