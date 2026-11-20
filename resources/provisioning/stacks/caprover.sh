#!/usr/bin/env bash
# CravaCloud stack installer: caprover (idempotent, _common.sh hardened).
# Official CapRover PaaS container (caprover/caprover) on ports 80, 443, 3000.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && ss -tln 2>/dev/null | grep -qE ':3000([[:space:]]|$)'; then
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

echo "== [3/4] CapRover Server =="
mkdir -p /captain
if ! docker ps --format '{{.Names}}' | grep -qE 'captain|caprover'; then
    docker run -d \
        --name captain-bootstrap \
        --restart=no \
        -p 80:80 -p 443:443 -p 3000:3000 \
        -e ACCEPTED_TERMS=true \
        -v /var/run/docker.sock:/var/run/docker.sock \
        -v /captain:/captain \
        caprover/caprover:latest
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 3000 180
crava_ufw_allow 22/tcp 80/tcp 443/tcp 3000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
