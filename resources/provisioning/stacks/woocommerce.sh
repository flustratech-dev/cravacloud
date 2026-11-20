#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/woocommerce"

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

mkdir -p "$STACK_DIR/html" "$STACK_DIR/db" "$STACK_DIR/ssl"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
WORDPRESS_DB_HOST=db:3306
WORDPRESS_DB_USER=wordpress
WORDPRESS_DB_PASSWORD=${DB_PWD}
WORDPRESS_DB_NAME=woocommerce
MYSQL_DATABASE=woocommerce
MYSQL_USER=wordpress
MYSQL_PASSWORD=${DB_PWD}
MYSQL_ROOT_PASSWORD=${DB_ROOT}
WORDPRESS_CONFIG_EXTRA=define('WP_REDIS_HOST', 'redis');
EOF
chmod 600 "$STACK_DIR/.env"

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$STACK_DIR/ssl/woo.key" \
    -out "$STACK_DIR/ssl/woo.crt" \
    -subj "/CN=cravacloud.local" >/dev/null 2>&1

cat > "$STACK_DIR/nginx.conf" <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/woo.crt;
    ssl_certificate_key /etc/nginx/ssl/woo.key;
    server_name _;
    client_max_body_size 100M;

    location / {
        proxy_pass http://wordpress:80;
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

  redis:
    image: redis:7-alpine
    restart: unless-stopped

  wordpress:
    image: wordpress:latest
    env_file: .env
    volumes:
      - ./html:/var/www/html
    depends_on:
      db:
        condition: service_healthy
      redis:
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
      - wordpress
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

# Pra-unduh plugin WooCommerce ke direktori plugin WordPress
sleep 5
docker compose -f "$STACK_DIR/docker-compose.yml" exec -T wordpress bash -c \
    'mkdir -p /var/www/html/wp-content/plugins && curl -fsSL https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip -o /tmp/woo.zip && python3 -c "import zipfile; zipfile.ZipFile(\"/tmp/woo.zip\").extractall(\"/var/www/html/wp-content/plugins\")" && rm -f /tmp/woo.zip && chown -R www-data:www-data /var/www/html/wp-content/plugins' || true

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_http "http://127.0.0.1/" 120

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] WordPress + WooCommerce siap di http://${PUBLIC_IP}."
echo "CRAVA_INSTALL_OK {{stack}}"
