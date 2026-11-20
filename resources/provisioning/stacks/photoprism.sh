#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/photoprism"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/originals" "$STACK_DIR/storage" "$STACK_DIR/db"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT="$(openssl rand -hex 18)"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
PHOTOPRISM_ADMIN_USER=admin
PHOTOPRISM_ADMIN_PASSWORD=${ADMIN_PWD}
PHOTOPRISM_SITE_URL=http://${PUBLIC_IP}:2342/
PHOTOPRISM_DATABASE_DRIVER=mysql
PHOTOPRISM_DATABASE_SERVER=mariadb:3306
PHOTOPRISM_DATABASE_NAME=photoprism
PHOTOPRISM_DATABASE_USER=photoprism
PHOTOPRISM_DATABASE_PASSWORD=${DB_PWD}
MARIADB_DATABASE=photoprism
MARIADB_USER=photoprism
MARIADB_PASSWORD=${DB_PWD}
MARIADB_ROOT_PASSWORD=${DB_ROOT}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  mariadb:
    image: mariadb:11
    env_file: .env
    volumes:
      - ./db:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "healthcheck.sh --connect --innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 15
    restart: unless-stopped

  photoprism:
    image: photoprism/photoprism:latest
    env_file: .env
    ports:
      - "2342:2342"
    volumes:
      - ./originals:/photoprism/originals
      - ./storage:/photoprism/storage
    depends_on:
      mariadb:
        condition: service_healthy
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 2342/tcp
crava_wait_http "http://127.0.0.1:2342/" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] PhotoPrism aktif di http://${PUBLIC_IP}:2342. Login admin tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
