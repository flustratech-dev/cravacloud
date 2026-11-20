#!/usr/bin/env bash
# CravaCloud stack installer: coolify (idempotent — safe to re-run via TAT retry).
# Admin account is created by the customer on first-run web setup (D6: no
# plaintext panel credentials travel through TAT).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/4] Base dependencies =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw

echo "== [2/4] Docker (required by Coolify) =="
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

echo "== [3/4] Coolify =="
if [ ! -d /data/coolify ]; then
    curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
fi

echo "== [4/4] Firewall + finalize =="
ufw allow 22/tcp || true
ufw allow 80/tcp || true
ufw allow 443/tcp || true
ufw allow 8000/tcp || true
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"