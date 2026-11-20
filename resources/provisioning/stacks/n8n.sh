#!/usr/bin/env bash
# CravaCloud stack installer: n8n (idempotent, _common.sh hardened).
# The owner account is created by the customer on first-run web setup (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' crava-n8n 2>/dev/null | grep -qx true; then
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

echo "== [3/4] n8n =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-n8n; then
    docker volume create n8n_data >/dev/null
    docker run -d --name crava-n8n --restart unless-stopped \
        -p 5678:5678 \
        -e N8N_HOST="{{hostname}}" -e N8N_PROTOCOL=http -e WEBHOOK_URL="http://{{hostname}}:5678/" \
        -v n8n_data:/home/node/.n8n \
        docker.n8n.io/n8nio/n8n:latest
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-n8n 120
crava_wait_port 5678 120
crava_ufw_allow 22/tcp 5678/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
