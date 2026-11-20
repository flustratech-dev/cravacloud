#!/usr/bin/env bash
# CravaCloud stack installer: dokploy (idempotent — safe to re-run via TAT retry).
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

echo "== [3/4] Dokploy =="
if ! docker ps -a --format '{{.Names}}' | grep -qx dokploy; then
    docker run -d --name dokploy --restart unless-stopped \
        -p 3000:3000 -p 80:80 -p 443:443 \
        -v /var/run/docker.sock:/var/run/docker.sock \
        -v dokploy-data:/etc/dokploy \
        dokploy/dokploy:latest
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 80 443 3000; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"