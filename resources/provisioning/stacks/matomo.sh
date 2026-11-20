#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/matomo"

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

mkdir -p "$STACK_DIR"
umask 077

DB_PWD="$(openssl rand -hex 18)"
DB_ROOT_PWD="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
MARIADB_DATABASE=matomo
MARIADB_USER=matomo
MARIADB_PASSWORD=${DB_PWD}
MARIADB_ROOT_PASSWORD=${DB_ROOT_PWD}
MATOMO_DATABASE_HOST=db
MATOMO_DATABASE_ADAPTER=mysql
MATOMO_DATABASE_TABLES_PREFIX=matomo_
MATOMO_DATABASE_USERNAME=matomo
MATOMO_DATABASE_PASSWORD=${DB_PWD}
MATOMO_DATABASE_DBNAME=matomo
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  db:
    image: mariadb:11
    command: --max-allowed-packet=64MB
    env_file: .env
    volumes:
      - db-data:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "healthcheck.sh --connect --innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 15
    restart: unless-stopped

  app:
    image: matomo:latest
    env_file: .env
    ports:
      - "8080:80"
    volumes:
      - matomo-data:/var/www/html
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped

volumes:
  db-data:
  matomo-data:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Matomo Analytics aktif di port 8080."
echo "CRAVA_INSTALL_OK {{stack}}"
