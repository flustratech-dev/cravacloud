#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/firefly-iii"

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

mkdir -p "$STACK_DIR/upload" "$STACK_DIR/db"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT="$(openssl rand -hex 18)"
APP_KEY="$(openssl rand -hex 16)"

cat > "$STACK_DIR/.env" <<EOF
APP_ENV=production
APP_DEBUG=false
SITE_OWNER=admin@cravacloud.local
APP_KEY=${APP_KEY}
DEFAULT_LANGUAGE=id_ID
DEFAULT_LOCALE=id_ID
TZ=Asia/Jakarta
TRUSTED_PROXIES=**
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=firefly
DB_USERNAME=firefly
DB_PASSWORD=${DB_PWD}
MYSQL_DATABASE=firefly
MYSQL_USER=firefly
MYSQL_PASSWORD=${DB_PWD}
MYSQL_ROOT_PASSWORD=${DB_ROOT}
APP_URL=http://${PUBLIC_IP}:8080
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

  app:
    image: fireflyiii/core:latest
    env_file: .env
    ports:
      - "8080:8080"
    volumes:
      - ./upload:/var/www/html/storage/upload
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
echo "[cravacloud] Firefly III aktif di http://${PUBLIC_IP}:8080."
echo "CRAVA_INSTALL_OK {{stack}}"
