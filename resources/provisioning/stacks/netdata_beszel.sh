#!/usr/bin/env bash
# CravaCloud stack installer: netdata_beszel (idempotent — safe to re-run via TAT retry).
# Netdata (metrics, :19999) + Beszel hub (lightweight monitoring, :8090).
# Beszel admin account is created on first-run web setup (D6).
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

echo "== [3/4] Netdata + Beszel =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-netdata; then
    docker run -d --name crava-netdata --restart unless-stopped \
        -p 19999:19999 \
        --cap-add SYS_PTRACE --security-opt apparmor=unconfined \
        -v /proc:/host/proc:ro -v /sys:/host/sys:ro \
        netdata/netdata:latest
fi
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-beszel; then
    docker volume create beszel_data >/dev/null
    docker run -d --name crava-beszel --restart unless-stopped \
        -p 8090:8090 \
        -v beszel_data:/beszel_data \
        henrygd/beszel:latest
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 19999 8090; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"