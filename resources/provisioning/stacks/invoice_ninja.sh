#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/invoice-ninja"

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

mkdir -p "$STACK_DIR/nginx" "$STACK_DIR/ssl"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT_PWD="$(openssl rand -hex 18)"
APP_KEY_B64="base64:$(openssl rand -base64 32 | tr -d '\n')"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
APP_URL=http://${PUBLIC_IP}
APP_KEY=${APP_KEY_B64}
APP_ENV=production
APP_DEBUG=false
REQUIRE_HTTPS=false
PHANTOMJS_PDF_GENERATION=false
PDF_GENERATOR=snappdf
TRUSTED_PROXIES=*
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379
FILESYSTEM_DISK=debian_docker
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=ninja
DB_USERNAME=ninja
DB_PASSWORD=${DB_PWD}
DB_ROOT_PASSWORD=${DB_ROOT_PWD}
DB_CONNECTION=mysql
MYSQL_ROOT_PASSWORD=${DB_ROOT_PWD}
MYSQL_USER=ninja
MYSQL_PASSWORD=${DB_PWD}
MYSQL_DATABASE=ninja
IN_USER_EMAIL=admin@cravacloud.local
IN_PASSWORD=${ADMIN_PWD}
IS_DOCKER=true
EOF
chmod 600 "$STACK_DIR/.env"

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$STACK_DIR/ssl/invoiceninja.key" \
    -out "$STACK_DIR/ssl/invoiceninja.crt" \
    -subj "/CN=cravacloud.local" >/dev/null 2>&1

cat > "$STACK_DIR/nginx/in-vhost.conf" <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/invoiceninja.crt;
    ssl_certificate_key /etc/nginx/ssl/invoiceninja.key;
    server_name _;
    root /var/www/html/public;
    index index.php;
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
NGINX

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  app:
    image: invoiceninja/invoiceninja-debian:latest
    restart: unless-stopped
    env_file:
      - .env
    volumes:
      - app_public:/var/www/html/public
      - app_storage:/var/www/html/storage
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy

  nginx:
    image: nginx:alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/in-vhost.conf:/etc/nginx/conf.d/default.conf:ro
      - ./ssl:/etc/nginx/ssl:ro
      - app_public:/var/www/html/public:ro
      - app_storage:/var/www/html/storage:ro
    depends_on:
      - app

  mysql:
    image: mysql:8
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -u$$MYSQL_USER -p$$MYSQL_PASSWORD"]
      interval: 5s
      timeout: 5s
      retries: 15

  redis:
    image: redis:alpine
    restart: unless-stopped
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10

volumes:
  app_public:
  app_storage:
  mysql_data:
  redis_data:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_http "http://127.0.0.1/" 180

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Invoice Ninja aktif di http://${PUBLIC_IP}. Kredensial login tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
