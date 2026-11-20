#!/usr/bin/env bash
# CravaCloud stack installer: lemp_laravel (idempotent, _common.sh hardened).
# Installs Nginx + PHP 8.3 FPM + MariaDB + Redis + Composer 2 + Node.js 22 + Supervisor.
# MariaDB credentials generated randomly into root-only /root/.crava-lemp.env (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && systemctl is-active --quiet nginx \
    && systemctl is-active --quiet mariadb \
    && ss -tln 2>/dev/null | grep -qE ':80([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/5] Base Packages, PHP Repository & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg unzip git openssl ufw \
    nginx mariadb-server redis-server supervisor lsb-release
crava_ensure_swap 2048

source /etc/os-release
if [ "${ID:-}" = "ubuntu" ] && [ "${VERSION_ID:-}" = "22.04" ]; then
    crava_pkg_install software-properties-common
    LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
    crava_wait_pkg_lock 120
    apt-get update -y
fi

if apt-cache show php8.3-fpm >/dev/null 2>&1; then
    PHP_VER="8.3"
elif apt-cache show php8.4-fpm >/dev/null 2>&1; then
    PHP_VER="8.4"
else
    PHP_VER="8.2"
fi

echo "== [2/5] PHP ${PHP_VER}-FPM + Laravel Extensions & Node 22 =="
crava_pkg_install \
    "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-mysql" "php${PHP_VER}-pgsql" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" "php${PHP_VER}-zip" \
    "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" "php${PHP_VER}-gd" "php${PHP_VER}-redis"

if [ ! -x /usr/local/bin/composer ]; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
fi

NODE_MAJOR=0
if command -v node >/dev/null 2>&1; then
    NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
fi
if [ "$NODE_MAJOR" -lt 22 ]; then
    crava_wait_pkg_lock 120
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi

echo "== [3/5] MariaDB & Redis Hardening =="
systemctl enable --now mariadb redis-server supervisor "php${PHP_VER}-fpm"

CREDS_FILE=/root/.crava-lemp.env
if [ ! -f "$CREDS_FILE" ]; then
    umask 077
    DB_NAME="laravel"
    DB_USER="laravel"
    DB_PASS="$(openssl rand -hex 20)"
    mariadb -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    mariadb -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
    cat > "$CREDS_FILE" <<ENV
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}
ENV
    chmod 600 "$CREDS_FILE"
fi

echo "== [4/5] Nginx Laravel Webroot Configuration =="
mkdir -p /var/www/laravel/public
if [ ! -f /var/www/laravel/public/index.php ]; then
    cat > /var/www/laravel/public/index.php <<'PHP'
<?php
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo "CravaCloud LEMP / Laravel Optimized Ready (PHP " . PHP_VERSION . ")\n";
PHP
fi
chown -R www-data:www-data /var/www/laravel

PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"
cat > /etc/nginx/sites-available/laravel <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root /var/www/laravel/public;
    index index.php index.html;

    charset utf-8;
    client_max_body_size 64M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/laravel
nginx -t
systemctl enable --now nginx
systemctl reload nginx

echo "== [5/5] Readiness Gate & Firewall =="
crava_wait_service "php${PHP_VER}-fpm" 120
crava_wait_service nginx 120
crava_wait_port 80 120
crava_wait_http "http://127.0.0.1" 120
crava_ufw_allow 22/tcp 80/tcp 443/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial MariaDB Laravel disimpan di /root/.crava-lemp.env (root-only)."
