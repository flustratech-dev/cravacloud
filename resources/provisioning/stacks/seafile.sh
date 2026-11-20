#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/seafile"

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

mkdir -p "$STACK_DIR/data" "$STACK_DIR/db" "$STACK_DIR/ssl"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
DB_ROOT="$(openssl rand -hex 18)"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
DB_HOST=db
DB_ROOT_PASSWD=${DB_ROOT}
TIME_ZONE=Asia/Jakarta
SEAFILE_ADMIN_EMAIL=admin@cravacloud.local
SEAFILE_ADMIN_PASSWORD=${ADMIN_PWD}
SEAFILE_SERVER_HOSTNAME=${PUBLIC_IP}
MYSQL_ROOT_PASSWORD=${DB_ROOT}
EOF
chmod 600 "$STACK_DIR/.env"

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$STACK_DIR/ssl/seafile.key" \
    -out "$STACK_DIR/ssl/seafile.crt" \
    -subj "/CN=cravacloud.local" >/dev/null 2>&1

cat > "$STACK_DIR/nginx.conf" <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/seafile.crt;
    ssl_certificate_key /etc/nginx/ssl/seafile.key;
    server_name _;
    client_max_body_size 0;

    location / {
        proxy_pass http://seafile:80;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
NGINX

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

  memcached:
    image: memcached:alpine
    entrypoint: memcached -m 256
    restart: unless-stopped

  seafile:
    image: seafileltd/seafile-mc:latest
    env_file: .env
    volumes:
      - ./data:/shared
    depends_on:
      db:
        condition: service_healthy
      memcached:
        condition: service_started
    restart: unless-stopped

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - ./ssl:/etc/nginx/ssl:ro
    depends_on:
      - seafile
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_port 80 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Seafile Cloud Drive aktif di http://${PUBLIC_IP}. Login tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
