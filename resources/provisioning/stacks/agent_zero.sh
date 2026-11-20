#!/usr/bin/env bash
# CravaCloud stack installer: agent_zero (idempotent — safe to re-run via TAT retry).
# API keys are entered by the customer in the web UI after install (D6: no
# plaintext secrets travel through TAT).
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

echo "== [3/4] Agent Zero =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-agent-zero; then
    docker volume create agent_zero_data >/dev/null
    docker run -d --name crava-agent-zero --restart unless-stopped \
        -p 50001:80 \
        -v agent_zero_data:/a0/usr \
        frdel/agent-zero-run:latest
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 50001; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"