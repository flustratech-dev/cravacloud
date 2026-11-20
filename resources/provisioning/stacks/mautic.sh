#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/mautic"

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

mkdir -p "$STACK_DIR/docroot" "$STACK_DIR/db"
umask 077
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
MYSQL_DATABASE=mautic
MYSQL_USER=mautic
MYSQL_PASSWORD=${DB_PWD}
MYSQL_ROOT_PASSWORD=${DB_ROOT}
MAUTIC_DB_HOST=db
MAUTIC_DB_PORT=3306
MAUTIC_DB_NAME=mautic
MAUTIC_DB_USER=mautic
MAUTIC_DB_PASSWORD=${DB_PWD}
MAUTIC_TRUSTED_PROXIES=["0.0.0.0/0"]
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

  mautic:
    image: mautic/mautic:5-apache
    env_file: .env
    ports:
      - "8080:80"
    volumes:
      - ./docroot:/var/www/html/docroot
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/" 180

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Mautic Marketing aktif di port 8080. Kredensial DB tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
