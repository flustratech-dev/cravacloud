#!/usr/bin/env bash
# CravaCloud stack installer: vscode_server (idempotent — safe to re-run via TAT retry).
# code-server password is generated on-server, stored root-only, NEVER echoed
# to stdout / TAT TaskResult (D6).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/4] Base dependencies =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw openssl

echo "== [2/4] Docker =="
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

echo "== [3/4] code-server =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-code-server; then
    CS_PASS="$(openssl rand -hex 16)"
    umask 077
    printf 'code_server_password=%s\n' "$CS_PASS" > /root/.crava-code-server
    docker run -d --name crava-code-server --restart unless-stopped \
        -p 8080:8080 \
        -e PASSWORD="$CS_PASS" -e SUDO_PASSWORD="$CS_PASS" \
        -v code-server-data:/config \
        lscr.io/linuxserver/code-server:latest
    unset CS_PASS
fi

echo "== [4/4] Firewall + finalize =="
for p in 22 8080; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Password code-server disimpan di /root/.crava-code-server (root-only)."