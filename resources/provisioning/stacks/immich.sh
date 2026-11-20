#!/usr/bin/env bash
# CravaCloud stack installer: immich (idempotent, _common.sh hardened).
# Official Immich docker-compose.yml & example.env from latest GitHub release (port 2283). D6 zero-echo.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-immich/docker-compose.yml ] \
    && docker ps --format '{{.Names}}' 2>/dev/null | grep -q immich \
    && ss -tln 2>/dev/null | grep -qE ':2283([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg openssl ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Immich Photo Cloud (Official Release Compose) =="
APP_DIR=/opt/crava-immich
mkdir -p "$APP_DIR/library" "$APP_DIR/postgres"
cd "$APP_DIR"

curl -fsSL https://github.com/immich-app/immich/releases/latest/download/docker-compose.yml -o docker-compose.yml
if [ ! -f .env ]; then
    umask 077
    curl -fsSL https://github.com/immich-app/immich/releases/latest/download/example.env -o .env
    DB_PASS="$(openssl rand -hex 16)"
    sed -i \
        -e "s|^UPLOAD_LOCATION=.*|UPLOAD_LOCATION=/opt/crava-immich/library|" \
        -e "s|^DB_DATA_LOCATION=.*|DB_DATA_LOCATION=/opt/crava-immich/postgres|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" \
        .env
    chmod 600 .env
fi

docker compose pull --quiet || docker compose pull
docker compose up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 2283 240; then
    docker compose logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 2283/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial database Immich disimpan di /opt/crava-immich/.env (root-only)."
