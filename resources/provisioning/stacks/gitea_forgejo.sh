#!/usr/bin/env bash
# CravaCloud stack installer: gitea_forgejo (idempotent — safe to re-run via TAT retry).
# Admin account is created by the customer on first-run web setup (D6).
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

echo "== [3/4] Forgejo (Gitea-compatible) =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-forgejo; then
    docker volume create forgejo_data >/dev/null
    docker run -d --name crava-forgejo --restart unless-stopped \
        -p 3000:3000 -p 2222:22 \
        -e USER_UID=1000 -e USER_GID=1000 \
        -e FORGEJO__server__ROOT_URL="http://{{hostname}}:3000/" \
        -v forgejo_data:/data \
        codeberg.org/forgejo/forgejo:latest
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 3000 2222; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"