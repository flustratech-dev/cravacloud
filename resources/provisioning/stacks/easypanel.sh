#!/usr/bin/env bash
# CravaCloud stack installer: easypanel (idempotent, _common.sh hardened).
# Official Easypanel installer on TCP port 3000 + 80/443 Traefik edge.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && docker service ls --format '{{.Name}}' 2>/dev/null | grep -q easypanel \
    && ss -tln 2>/dev/null | grep -qE ':3000([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg lsof ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Easypanel Setup =="
if [ ! -d /etc/easypanel ]; then
    docker run --rm \
        -v /etc/easypanel:/etc/easypanel \
        -v /var/run/docker.sock:/var/run/docker.sock:ro \
        easypanel/easypanel:latest setup
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 3000 180
crava_ufw_allow 22/tcp 80/tcp 443/tcp 3000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
