#!/usr/bin/env bash
# CravaCloud stack installer: coolify (idempotent, _common.sh hardened).
# Admin account is created by the customer on first-run web setup (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && [ -d /data/coolify ] && docker ps --format '{{.Names}}' 2>/dev/null | grep -q coolify; then
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

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Coolify =="
if [ ! -d /data/coolify ]; then
    curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 8000 180
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
