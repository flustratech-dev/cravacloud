#!/usr/bin/env bash
# CravaCloud stack installer: dify_ollama (idempotent, _common.sh hardened).
# Official Dify docker compose + private Ollama container.
# Dify SECRET_KEY is generated on-server into root-only .env (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -d /opt/crava-dify/docker ] \
    && docker inspect --format '{{.State.Running}}' crava-dify-ollama 2>/dev/null | grep -qx true \
    && ss -tln 2>/dev/null | grep -qE ':80([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/5] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw openssl git
crava_ensure_swap 2048

echo "== [2/5] Docker Engine =="
crava_ensure_docker

echo "== [3/5] Dify (Official Compose) =="
APP_DIR=/opt/crava-dify
if [ ! -d "$APP_DIR/docker" ]; then
    git clone --depth 1 https://github.com/langgenius/dify.git "$APP_DIR"
fi
cd "$APP_DIR/docker"
if [ ! -f .env ]; then
    umask 077
    cp .env.example .env
    printf '\nSECRET_KEY=%s\n' "$(openssl rand -hex 32)" >> .env
    chmod 600 .env
fi
docker compose up -d

echo "== [4/5] Private Ollama (Internal Network) =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-dify-ollama; then
    docker volume create dify_ollama_data >/dev/null
    docker run -d --name crava-dify-ollama --restart unless-stopped \
        -e OLLAMA_HOST=0.0.0.0 \
        -v dify_ollama_data:/root/.ollama \
        ollama/ollama:latest
fi

echo "== [5/5] Readiness Gate & Firewall =="
crava_wait_container crava-dify-ollama 120
if ! crava_wait_port 80 180; then
    docker compose -f "$APP_DIR/docker/docker-compose.yaml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 80/tcp 443/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Secret Dify disimpan di /opt/crava-dify/docker/.env (root-only)."
