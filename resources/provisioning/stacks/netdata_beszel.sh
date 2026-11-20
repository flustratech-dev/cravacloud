#!/usr/bin/env bash
# CravaCloud stack installer: netdata_beszel (idempotent, _common.sh hardened).
# Netdata (:19999) + Beszel hub (:8090). Admin account is created on first-run setup (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && docker inspect --format '{{.State.Running}}' crava-netdata 2>/dev/null | grep -qx true \
    && docker inspect --format '{{.State.Running}}' crava-beszel 2>/dev/null | grep -qx true; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

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

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-netdata 120
crava_wait_container crava-beszel 120
crava_wait_port 19999 120
crava_wait_port 8090 120
crava_ufw_allow 22/tcp 19999/tcp 8090/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
