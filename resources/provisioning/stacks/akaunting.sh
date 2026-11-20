#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/akaunting"

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

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
DB_PWD="$(openssl rand -hex 18)"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
APP_URL=http://${PUBLIC_IP}
LOCALE=en-US
DB_HOST=akaunting-db
DB_PORT=3306
DB_NAME=akaunting
DB_USERNAME=akaunting
DB_PASSWORD=${DB_PWD}
DB_PREFIX=crv_
COMPANY_NAME=CravaCloud Business
COMPANY_EMAIL=admin@cravacloud.local
ADMIN_EMAIL=admin@cravacloud.local
ADMIN_PASSWORD=${ADMIN_PWD}
MYSQL_DATABASE=akaunting
MYSQL_USER=akaunting
MYSQL_PASSWORD=${DB_PWD}
MYSQL_RANDOM_ROOT_PASSWORD=yes
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  akaunting:
    image: akaunting/akaunting:latest
    container_name: akaunting
    ports:
      - "80:80"
    volumes:
      - akaunting-data:/var/www/html
    restart: unless-stopped
    env_file:
      - .env
    environment:
      AKAUNTING_SETUP: "true"
    depends_on:
      akaunting-db:
        condition: service_healthy

  akaunting-db:
    image: mariadb:11
    container_name: akaunting-db
    volumes:
      - akaunting-db:/var/lib/mysql
    restart: unless-stopped
    env_file:
      - .env
    healthcheck:
      test: ["CMD-SHELL", "healthcheck.sh --connect --innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 15

volumes:
  akaunting-data:
  akaunting-db:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_wait_port 80 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Akaunting aktif di http://${PUBLIC_IP}. Kredensial tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
