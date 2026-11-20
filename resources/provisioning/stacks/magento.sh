#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/magento"

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

mkdir -p "$STACK_DIR/html" "$STACK_DIR/db" "$STACK_DIR/opensearch" "$STACK_DIR/ssl"
chmod 777 "$STACK_DIR/html" "$STACK_DIR/opensearch"
umask 077
DB_PWD="$(openssl rand -hex 18)"
DB_ROOT="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
MYSQL_DATABASE=magento
MYSQL_USER=magento
MYSQL_PASSWORD=${DB_PWD}
MYSQL_ROOT_PASSWORD=${DB_ROOT}
EOF
chmod 600 "$STACK_DIR/.env"

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$STACK_DIR/ssl/magento.key" \
    -out "$STACK_DIR/ssl/magento.crt" \
    -subj "/CN=cravacloud.local" >/dev/null 2>&1

cat > "$STACK_DIR/nginx.conf" <<'NGINX'
upstream fastcgi_backend {
    server phpfpm:9000;
}

server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/magento.crt;
    ssl_certificate_key /etc/nginx/ssl/magento.key;
    server_name _;
    root /var/www/html/pub;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass fastcgi_backend;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
NGINX

cat > "$STACK_DIR/html/pub/index.php" <<'PHP'
<?php
// Readiness endpoint sebelum project composer Magento 2 dieksekusi di /var/www/html
echo "Magento PHP 8.3 + MariaDB + OpenSearch Siap Deploy.";
PHP
mkdir -p "$STACK_DIR/html/pub"
mv "$STACK_DIR/html/pub/index.php" "$STACK_DIR/html/pub/index.php" 2>/dev/null || true

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

  opensearch:
    image: opensearchproject/opensearch:2
    environment:
      discovery.type: single-node
      OPENSEARCH_JAVA_OPTS: "-Xms512m -Xmx512m"
      DISABLE_SECURITY_PLUGIN: "true"
    volumes:
      - ./opensearch:/usr/share/opensearch/data
    restart: unless-stopped

  phpfpm:
    image: markoshust/magento-php:8.3-fpm-3
    volumes:
      - ./html:/var/www/html
    depends_on:
      db:
        condition: service_healthy
      opensearch:
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
      - ./html:/var/www/html:ro
    depends_on:
      - phpfpm
    restart: unless-stopped
YAML

mkdir -p "$STACK_DIR/html/pub"
printf "<?php\nheader('Content-Type: text/plain');\necho 'Magento / OpenMage Stack Ready (PHP 8.3 + MariaDB + OpenSearch)';\n" > "$STACK_DIR/html/pub/index.php"

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_http "http://127.0.0.1/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Magento runtime (PHP 8.3 FPM + MariaDB + OpenSearch + Nginx) aktif di port 80/443."
echo "CRAVA_INSTALL_OK {{stack}}"
