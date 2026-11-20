#!/usr/bin/env bash
# CravaCloud stack installer: vscode_server (idempotent, _common.sh hardened).
# code-server password is generated on-server into root-only file (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' crava-code-server 2>/dev/null | grep -qx true; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw openssl
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] code-server =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-code-server; then
    CS_PASS="$(openssl rand -hex 16)"
    umask 077
    printf 'code_server_password=%s\n' "$CS_PASS" > /root/.crava-code-server
    chmod 600 /root/.crava-code-server
    docker run -d --name crava-code-server --restart unless-stopped \
        -p 8080:8080 \
        -e PASSWORD="$CS_PASS" -e SUDO_PASSWORD="$CS_PASS" \
        -v code-server-data:/config \
        lscr.io/linuxserver/code-server:latest
    unset CS_PASS
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-code-server 120
crava_wait_port 8080 120
crava_ufw_allow 22/tcp 8080/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Password code-server disimpan di /root/.crava-code-server (root-only)."
