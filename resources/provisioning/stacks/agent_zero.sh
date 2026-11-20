#!/usr/bin/env bash
# CravaCloud stack installer: agent_zero (idempotent, _common.sh hardened).
# API keys are entered by the customer in the web UI after install (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' crava-agent-zero 2>/dev/null | grep -qx true; then
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

echo "== [3/4] Agent Zero =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-agent-zero; then
    docker volume create agent_zero_data >/dev/null
    docker run -d --name crava-agent-zero --restart unless-stopped \
        -p 50001:80 \
        -v agent_zero_data:/a0/usr \
        frdel/agent-zero-run:latest
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-agent-zero 120
crava_wait_port 50001 120
crava_ufw_allow 22/tcp 50001/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
