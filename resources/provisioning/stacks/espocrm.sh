#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/espocrm"

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
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT="$(openssl rand -hex 18)"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
MYSQL_DATABASE=espocrm
MYSQL_USER=espocrm
MYSQL_PASSWORD=${DB_PWD}
MYSQL_ROOT_PASSWORD=${DB_ROOT}
ESPOCRM_DATABASE_PLATFORM=Mysql
ESPOCRM_DATABASE_HOST=db
ESPOCRM_DATABASE_USER=espocrm
ESPOCRM_DATABASE_PASSWORD=${DB_PWD}
ESPOCRM_ADMIN_USERNAME=admin
ESPOCRM_ADMIN_PASSWORD=${ADMIN_PWD}
ESPOCRM_SITE_URL=http://${PUBLIC_IP}:8080
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

  espocrm:
    image: espocrm/espocrm:latest
    env_file: .env
    ports:
      - "8080:80"
    volumes:
      - ./data:/var/www/html
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] EspoCRM aktif di http://${PUBLIC_IP}:8080. Password tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
