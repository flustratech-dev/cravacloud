#!/usr/bin/env bash
# CravaCloud stack installer: appwrite (idempotent, _common.sh hardened).
# Official appwrite/appwrite multi-container compose + mongo init assets (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-appwrite/docker-compose.yml ] \
    && docker ps --format '{{.Names}}' 2>/dev/null | grep -qx appwrite \
    && ss -tln 2>/dev/null | grep -qE ':80([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg git openssl ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Appwrite Official Compose Setup =="
APP_DIR=/opt/crava-appwrite
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/docker-compose.yml" ]; then
    TMP_REPO="$(mktemp -d)"
    trap 'rm -rf "$TMP_REPO"' EXIT
    git clone --depth 1 --branch 2.7.4 --filter=blob:none --sparse https://github.com/appwrite/appwrite.git "$TMP_REPO"
    git -C "$TMP_REPO" sparse-checkout set docker-compose.yml .env mongo-init.js mongo-entrypoint.sh
    cp -f "$TMP_REPO/docker-compose.yml" "$APP_DIR/docker-compose.yml"
    cp -f "$TMP_REPO/.env" "$APP_DIR/.env.upstream"
    cp -f "$TMP_REPO/mongo-init.js" "$APP_DIR/mongo-init.js"
    cp -f "$TMP_REPO/mongo-entrypoint.sh" "$APP_DIR/mongo-entrypoint.sh"
    chmod +x "$APP_DIR/mongo-entrypoint.sh"
fi

cd "$APP_DIR"
if [ ! -f .env ]; then
    umask 077
    cp .env.upstream .env
    OPENSSL_KEY="$(openssl rand -hex 32)"
    TRACK_SECRET="$(openssl rand -hex 24)"
    DB_PASS="$(openssl rand -hex 18)"
    DB_ROOT_PASS="$(openssl rand -hex 20)"
    REDIS_PASS="$(openssl rand -hex 18)"
    JOBS_SECRET="$(openssl rand -hex 24)"
    REVIEWS_SECRET="$(openssl rand -hex 24)"
    sed -i \
        -e "s|^_APP_ENV=.*|_APP_ENV=production|" \
        -e "s|^_APP_OPENSSL_KEY_V1=.*|_APP_OPENSSL_KEY_V1=${OPENSSL_KEY}|" \
        -e "s|^_APP_NOTIFICATIONS_TRACKING_SECRET=.*|_APP_NOTIFICATIONS_TRACKING_SECRET=${TRACK_SECRET}|" \
        .env
    cat >> .env <<ENV
_APP_DOMAIN=localhost
_APP_CONSOLE_DOMAIN=localhost
_APP_HTTP_PORT=80
_APP_HTTPS_PORT=443
_APP_DB_HOST=mariadb
_APP_DB_SCHEMA=appwrite
_APP_DB_USER=appwrite
_APP_DB_PASS=${DB_PASS}
_APP_DB_ROOT_PASS=${DB_ROOT_PASS}
_APP_REDIS_HOST=redis
_APP_REDIS_PORT=6379
_APP_REDIS_USER=default
_APP_REDIS_PASS=${REDIS_PASS}
_APP_JOBS_SECRET=${JOBS_SECRET}
_APP_REVIEWS_SECRET=${REVIEWS_SECRET}
_APP_REVIEWS_MODEL=none
_APP_REVIEWS_AI_GATEWAY_KEY=none
ENV
    chmod 600 .env
fi

docker compose pull --quiet || docker compose pull
docker compose up -d --remove-orphans

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 80 240; then
    docker compose logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 80/tcp 443/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Konfigurasi & secrets Appwrite disimpan di /opt/crava-appwrite/.env (root-only)."
