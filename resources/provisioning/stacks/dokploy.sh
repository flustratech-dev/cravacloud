#!/usr/bin/env bash
# CravaCloud stack installer: dokploy (idempotent, _common.sh hardened).
# Admin account is created by the customer on first-run web setup (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' dokploy 2>/dev/null | grep -qx true; then
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

echo "== [3/4] Dokploy =="
if ! docker ps -a --format '{{.Names}}' | grep -qx dokploy; then
    docker run -d --name dokploy --restart unless-stopped \
        -p 3000:3000 -p 80:80 -p 443:443 \
        -v /var/run/docker.sock:/var/run/docker.sock \
        -v dokploy-data:/etc/dokploy \
        dokploy/dokploy:latest
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container dokploy 120
crava_wait_port 3000 120
crava_ufw_allow 22/tcp 80/tcp 443/tcp 3000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
