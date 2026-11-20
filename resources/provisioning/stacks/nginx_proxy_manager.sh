#!/usr/bin/env bash
# CravaCloud stack installer: nginx_proxy_manager (idempotent, _common.sh hardened).
# Official Nginx Proxy Manager container (jc21/nginx-proxy-manager) on ports 80, 443, 81.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-npm/compose.yml ] \
    && docker compose -f /opt/crava-npm/compose.yml ps --status running 2>/dev/null | grep -q npm \
    && ss -tln 2>/dev/null | grep -qE ':81([[:space:]]|$)'; then
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

echo "== [3/4] Nginx Proxy Manager =="
APP_DIR=/opt/crava-npm
mkdir -p "$APP_DIR/data" "$APP_DIR/letsencrypt"
cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  npm:
    image: jc21/nginx-proxy-manager:latest
    container_name: crava-npm
    restart: unless-stopped
    ports:
      - "80:80"
      - "81:81"
      - "443:443"
    volumes:
      - ./data:/data
      - ./letsencrypt:/etc/letsencrypt
YAML
docker compose -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 81 180; then
    docker compose -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 80/tcp 443/tcp 81/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
