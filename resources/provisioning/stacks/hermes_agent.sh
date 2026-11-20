#!/usr/bin/env bash
# CravaCloud stack installer: hermes_agent (idempotent — safe to re-run via TAT retry).
# White-label AI agent bundle published by CravaCloud. The image reference is
# read from /opt/crava-hermes/image.env so it can be pinned per release.
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

echo "== [3/4] Hermes Agent =="
APP_DIR=/opt/crava-hermes
mkdir -p "$APP_DIR"
[ -f "$APP_DIR/image.env" ] || printf 'HERMES_IMAGE=registry.cravacloud.id/hermes-agent:latest\n' > "$APP_DIR/image.env"
# shellcheck disable=SC1091
. "$APP_DIR/image.env"
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-hermes; then
    docker volume create hermes_data >/dev/null
    docker run -d --name crava-hermes --restart unless-stopped \
        -p 8000:8000 \
        -v hermes_data:/app/data \
        "$HERMES_IMAGE"
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 8000; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"