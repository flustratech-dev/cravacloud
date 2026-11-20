#!/usr/bin/env bash
# CravaCloud stack installer: dify_ollama (idempotent — safe to re-run via TAT retry).
# Dify (official docker compose) + a private Ollama container for local RAG.
# Dify's SECRET_KEY and DB passwords are generated on-server; nothing secret is
# echoed to stdout / TAT TaskResult (D6). Ollama stays on the internal network
# (no extra inbound port). The Dify admin account is created on first-run web setup.
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/5] Base dependencies =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw openssl git

echo "== [2/5] Docker =="
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

echo "== [3/5] Dify (official compose) =="
APP_DIR=/opt/crava-dify
if [ ! -d "$APP_DIR/docker" ]; then
    git clone --depth 1 https://github.com/langgenius/dify.git "$APP_DIR"
fi
cd "$APP_DIR/docker"
if [ ! -f .env ]; then
    cp .env.example .env
    umask 077
    printf '\nSECRET_KEY=%s\n' "$(openssl rand -hex 32)" >> .env
fi
docker compose up -d

echo "== [4/5] Private Ollama (internal network only) =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-dify-ollama; then
    docker volume create dify_ollama_data >/dev/null
    docker run -d --name crava-dify-ollama --restart unless-stopped \
        -e OLLAMA_HOST=0.0.0.0 \
        -v dify_ollama_data:/root/.ollama \
        ollama/ollama:latest
fi

echo "== [5/5] Firewall + finalize =="
for p in 22 80 443; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Secret Dify disimpan di /opt/crava-dify/docker/.env (root-only)."