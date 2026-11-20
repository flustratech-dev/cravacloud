#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/bookstack"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl nginx
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/app_data" "$STACK_DIR/db_data"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT_PWD="$(openssl rand -hex 18)"
APP_KEY_B64="base64:$(openssl rand -base64 32 | tr -d '\n')"

cat > "$STACK_DIR/.env" <<EOF
APP_URL=http://${PUBLIC_IP}:6875
APP_KEY=${APP_KEY_B64}
DB_HOST=bookstack_db
DB_PORT=3306
DB_DATABASE=bookstackapp
DB_USERNAME=bookstack
DB_PASSWORD=${DB_PWD}
MYSQL_ROOT_PASSWORD=${DB_ROOT_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  bookstack:
    image: lscr.io/linuxserver/bookstack:latest
    container_name: bookstack
    env_file:
      - .env
    environment:
      PUID: "1000"
      PGID: "1000"
      TZ: "Asia/Jakarta"
      DB_PASS: ${DB_PASSWORD}
      DB_USER: ${DB_USERNAME}
    volumes:
      - ./app_data:/config
    ports:
      - "6875:80"
    restart: unless-stopped
    depends_on:
      bookstack_db:
        condition: service_healthy

  bookstack_db:
    image: mariadb:11
    container_name: bookstack_db
    env_file:
      - .env
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    volumes:
      - ./db_data:/var/lib/mysql
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "healthcheck.sh --connect --innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 15
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

mkdir -p /etc/nginx/ssl
if [[ ! -f /etc/nginx/ssl/bookstack.key ]]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/bookstack.key \
        -out /etc/nginx/ssl/bookstack.crt \
        -subj "/CN=cravacloud.local" >/dev/null 2>&1
fi

cat > /etc/nginx/sites-available/bookstack <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/bookstack.crt;
    ssl_certificate_key /etc/nginx/ssl/bookstack.key;
    server_name _;

    location / {
        proxy_pass http://127.0.0.1:6875;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/bookstack /etc/nginx/sites-enabled/bookstack
nginx -t
systemctl enable --now nginx
systemctl reload nginx

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 6875/tcp
crava_wait_http "http://127.0.0.1:6875/" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] BookStack aktif di http://${PUBLIC_IP}:6875 dan port 80/443."
echo "CRAVA_INSTALL_OK {{stack}}"
