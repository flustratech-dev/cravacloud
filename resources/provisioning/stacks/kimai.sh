#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/kimai"

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

mkdir -p "$STACK_DIR/data" "$STACK_DIR/db"
umask 077
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT="$(openssl rand -hex 18)"
ADMIN_PWD="$(openssl rand -hex 12)"
APP_SEC="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
APP_ENV=prod
APP_SECRET=${APP_SEC}
MYSQL_DATABASE=kimai
MYSQL_USER=kimai
MYSQL_PASSWORD=${DB_PWD}
MYSQL_ROOT_PASSWORD=${DB_ROOT}
DATABASE_URL=mysql://kimai:${DB_PWD}@db:3306/kimai?charset=utf8mb4&serverVersion=11.0
ADMINMAIL=admin@cravacloud.local
ADMINPASS=${ADMIN_PWD}
TZ=Asia/Jakarta
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  db:
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

  kimai:
    image: kimai/kimai2:apache
    env_file: .env
    ports:
      - "8001:8001"
    volumes:
      - ./data:/opt/kimai/var/data
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8001/tcp
crava_wait_http "http://127.0.0.1:8001/" 180

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Kimai Time Tracker aktif di port 8001. Kredensial tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
