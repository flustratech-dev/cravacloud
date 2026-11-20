#!/usr/bin/env bash
# CravaCloud stack installer: docker (idempotent, _common.sh hardened).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker info >/dev/null 2>&1; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine + Compose Plugin =="
if ! command -v docker >/dev/null 2>&1; then
    crava_wait_pkg_lock 120
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        > /etc/apt/sources.list.d/docker.list
    crava_wait_pkg_lock 120
    apt-get update -y
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
crava_ensure_docker

echo "== [3/4] Readiness Gate & Firewall =="
if ! docker info >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
    journalctl -u docker -n 40 --no-pager >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp

echo "== [4/4] Finalize =="
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
