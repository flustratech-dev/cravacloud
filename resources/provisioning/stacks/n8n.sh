#!/usr/bin/env bash
# CravaCloud stack installer: n8n (idempotent — safe to re-run via TAT retry).
# The owner account is created by the customer on first-run web setup (D6).
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

echo "== [3/4] n8n =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-n8n; then
    docker volume create n8n_data >/dev/null
    docker run -d --name crava-n8n --restart unless-stopped \
        -p 5678:5678 \
        -e N8N_HOST="{{hostname}}" -e N8N_PROTOCOL=http -e WEBHOOK_URL="http://{{hostname}}:5678/" \
        -v n8n_data:/home/node/.n8n \
        docker.n8n.io/n8nio/n8n:latest
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 5678; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"