#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/dolibarr"

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

mkdir -p "$STACK_DIR/documents" "$STACK_DIR/custom" "$STACK_DIR/db"
umask 077

DB_PWD="$(openssl rand -hex 18)"
DB_ROOT_PWD="$(openssl rand -hex 18)"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
DOLI_DB_TYPE=mysqli
DOLI_DB_HOST=mariadb
DOLI_DB_PORT=3306
DOLI_DB_NAME=dolibarr
DOLI_DB_USER=dolibarr
DOLI_DB_PASSWORD=${DB_PWD}
DOLI_ADMIN_LOGIN=admin
DOLI_ADMIN_PASSWORD=${ADMIN_PWD}
MARIADB_DATABASE=dolibarr
MARIADB_USER=dolibarr
MARIADB_PASSWORD=${DB_PWD}
MARIADB_ROOT_PASSWORD=${DB_ROOT_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  mariadb:
    image: mariadb:11
    env_file: .env
    volumes:
      - ./db:/var/lib/mysql
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "healthcheck.sh --connect --innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 15

  dolibarr:
    image: dolibarr/dolibarr:latest
    env_file: .env
    environment:
      DOLI_INSTALL_AUTO: "1"
    ports:
      - "80:80"
    volumes:
      - ./documents:/var/www/documents
      - ./custom:/var/www/html/custom
    depends_on:
      mariadb:
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
echo "[cravacloud] Dolibarr ERP & CRM aktif di port 80. Kredensial tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
