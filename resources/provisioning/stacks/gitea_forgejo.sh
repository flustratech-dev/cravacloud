#!/usr/bin/env bash
# CravaCloud stack installer: gitea_forgejo (idempotent, _common.sh hardened).
# Admin account is created by the customer on first-run web setup (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' crava-forgejo 2>/dev/null | grep -qx true; then
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

echo "== [3/4] Forgejo (Gitea-compatible) =="
if ! docker ps -a --format '{{.Names}}' | grep -qx crava-forgejo; then
    docker volume create forgejo_data >/dev/null
    docker run -d --name crava-forgejo --restart unless-stopped \
        -p 3000:3000 -p 2222:22 \
        -e USER_UID=1000 -e USER_GID=1000 \
        -e FORGEJO__server__ROOT_URL="http://{{hostname}}:3000/" \
        -v forgejo_data:/data \
        codeberg.org/forgejo/forgejo:15
fi

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-forgejo 120
crava_wait_port 3000 120
crava_ufw_allow 22/tcp 3000/tcp 2222/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
