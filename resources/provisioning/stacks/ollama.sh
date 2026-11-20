#!/usr/bin/env bash
# CravaCloud stack installer: ollama (idempotent, _common.sh hardened).
# Model weights are pulled by the customer after install; no secrets needed (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' crava-ollama 2>/dev/null | grep -qx true; then
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

echo "== [3/4] Ollama =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-ollama; then
    docker volume create ollama_data >/dev/null
    docker run -d --name crava-ollama --restart unless-stopped \
        -p 11434:11434 \
        -e OLLAMA_HOST=0.0.0.0 \
        -v ollama_data:/root/.ollama \
        ollama/ollama:latest
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-ollama 120
crava_wait_port 11434 120
crava_ufw_allow 22/tcp 11434/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
