#!/usr/bin/env bash
# CravaCloud stack installer: portainer (idempotent, _common.sh hardened).
# Official Portainer CE LTS Docker container on HTTPS port 9443 (and optional HTTP 80/443 proxy ready).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && docker inspect --format '{{.State.Running}}' portainer 2>/dev/null | grep -qx true \
    && ss -tln 2>/dev/null | grep -qE ':9443([[:space:]]|$)'; then
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

echo "== [3/4] Portainer CE (Official Container) =="
docker volume create portainer_data >/dev/null
if ! docker ps -a --format '{{.Names}}' | grep -qx portainer; then
    docker run -d \
        -p 9443:9443 \
        --name portainer \
        --restart=always \
        -v /var/run/docker.sock:/var/run/docker.sock \
        -v portainer_data:/data \
        portainer/portainer-ce:lts
else
    docker start portainer >/dev/null 2>&1 || true
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container portainer 120
crava_wait_port 9443 120
crava_ufw_allow 22/tcp 80/tcp 443/tcp 9443/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
