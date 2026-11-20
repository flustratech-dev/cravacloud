#!/usr/bin/env bash
# CravaCloud stack installer: cosmos_cloud (idempotent, _common.sh hardened).
# Official Cosmos Cloud (azukaar/Cosmos-Server) service installer + UDP 4242 Constellation.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && systemctl is-active --quiet CosmosCloud \
    && ss -tln 2>/dev/null | grep -qE ':80([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl unzip ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Cosmos Cloud (Official Installer) =="
curl -fsSL https://cosmos-cloud.io/get.sh -o /tmp/cosmos-get.sh
if [ -d /opt/cosmos ] && [ -n "$(ls -A /opt/cosmos 2>/dev/null)" ]; then
    bash /tmp/cosmos-get.sh --fresh --no-docker
else
    bash /tmp/cosmos-get.sh --no-docker
fi
rm -f /tmp/cosmos-get.sh

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_service CosmosCloud 120
crava_wait_port 80 120
crava_ufw_allow 22/tcp 80/tcp 443/tcp 4242/udp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
