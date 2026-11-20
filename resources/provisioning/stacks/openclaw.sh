#!/usr/bin/env bash
# CravaCloud stack installer: openclaw (idempotent — safe to re-run via TAT retry).
# White-label agent bundle published by CravaCloud. The image reference is read
# from /opt/crava-openclaw/image.env so it can be pinned per release.
# Provider API keys are entered by the customer in the web UI after install (D6).
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

echo "== [3/4] OpenClaw =="
APP_DIR=/opt/crava-openclaw
mkdir -p "$APP_DIR"
[ -f "$APP_DIR/image.env" ] || printf 'OPENCLAW_IMAGE=registry.cravacloud.id/openclaw:latest\n' > "$APP_DIR/image.env"
# shellcheck disable=SC1091
. "$APP_DIR/image.env"
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-openclaw; then
    docker volume create openclaw_data >/dev/null
    docker run -d --name crava-openclaw --restart unless-stopped \
        -p 8080:8080 \
        -v openclaw_data:/app/data \
        "$OPENCLAW_IMAGE"
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 8080; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"