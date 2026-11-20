#!/usr/bin/env bash
# CravaCloud stack installer: uptime_kuma (idempotent, _common.sh hardened).
# Admin account is created by the customer on first-run web setup (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' crava-uptime-kuma 2>/dev/null | grep -qx true; then
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

echo "== [3/4] Uptime Kuma =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-uptime-kuma; then
    docker volume create uptime_kuma_data >/dev/null
    docker run -d --name crava-uptime-kuma --restart unless-stopped \
        -p 3001:3001 \
        -v uptime_kuma_data:/app/data \
        louislam/uptime-kuma:latest
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-uptime-kuma 120
crava_wait_port 3001 120
crava_ufw_allow 22/tcp 3001/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
