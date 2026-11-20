#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/glpi"

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

mkdir -p "$STACK_DIR/html" "$STACK_DIR/db"
umask 077
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
MARIADB_DATABASE=glpidb
MARIADB_USER=glpi_user
MARIADB_PASSWORD=${DB_PWD}
MARIADB_ROOT_PASSWORD=${DB_ROOT}
TIMEZONE=Asia/Jakarta
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

  glpi:
    image: diouxx/glpi:latest
    env_file: .env
    ports:
      - "80:80"
    volumes:
      - ./html:/var/www/html/glpi
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_wait_http "http://127.0.0.1/" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] GLPI ITSM & Helpdesk aktif di port 80. Kredensial DB tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
