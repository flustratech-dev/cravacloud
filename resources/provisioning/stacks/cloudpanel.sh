#!/usr/bin/env bash
# CravaCloud stack installer: cloudpanel (idempotent, _common.sh hardened).
# Admin account is created by the customer on first-run web setup (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && [ -d /home/clp ] && ss -tln 2>/dev/null | grep -qE ':8443([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw
crava_ensure_swap 2048

echo "== [2/4] CloudPanel CE =="
if [ ! -d /home/clp ]; then
    crava_wait_pkg_lock 120
    curl -fsSL https://installer.cloudpanel.io/ce/v2/install.sh -o /tmp/cloudpanel-install.sh
    bash /tmp/cloudpanel-install.sh
    rm -f /tmp/cloudpanel-install.sh
fi

echo "== [3/4] Readiness Gate =="
crava_wait_port 8443 180

echo "== [4/4] Firewall & Finalize =="
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8443/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
