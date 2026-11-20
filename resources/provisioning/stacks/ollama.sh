#!/usr/bin/env bash
# CravaCloud stack installer: ollama (idempotent — safe to re-run via TAT retry).
# Model weights are pulled by the customer after install; no secrets needed (D6).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/4] Base dependencies =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw

echo "== [2/4] Docker =="
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

echo "== [3/4] Ollama =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-ollama; then
    docker volume create ollama_data >/dev/null
    docker run -d --name crava-ollama --restart unless-stopped \
        -p 11434:11434 \
        -e OLLAMA_HOST=0.0.0.0 \
        -v ollama_data:/root/.ollama \
        ollama/ollama:latest
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 11434; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"