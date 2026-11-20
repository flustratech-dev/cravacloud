#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl nginx mariadb-server php-fpm php-mysql php-xml php-mbstring php-curl php-zip php-gd php-json
crava_ensure_swap 2048

systemctl enable --now mariadb
systemctl enable --now nginx

DB_PWD="$(openssl rand -hex 16)"
mysql -e "CREATE DATABASE IF NOT EXISTS froxlor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'froxlor'@'localhost' IDENTIFIED BY '${DB_PWD}'; GRANT ALL PRIVILEGES ON froxlor.* TO 'froxlor'@'localhost'; FLUSH PRIVILEGES;"

umask 077
mkdir -p /root/.cravacloud
cat > /root/.cravacloud/froxlor.env <<EOF
FROXLOR_DB_NAME=froxlor
FROXLOR_DB_USER=froxlor
FROXLOR_DB_PASS=${DB_PWD}
EOF
chmod 600 /root/.cravacloud/froxlor.env

mkdir -p /var/www/html
curl -fsSL "https://files.froxlor.org/releases/froxlor-latest.tar.gz" -o /tmp/froxlor.tar.gz
tar -xzf /tmp/froxlor.tar.gz -C /var/www/html
rm -f /tmp/froxlor.tar.gz
chown -R www-data:www-data /var/www/html/froxlor

mkdir -p /etc/nginx/ssl
if [[ ! -f /etc/nginx/ssl/froxlor.key ]]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/froxlor.key \
        -out /etc/nginx/ssl/froxlor.crt \
        -subj "/CN=cravacloud.local" >/dev/null 2>&1
fi

PHP_SOCK="$(ls /run/php/php*-fpm.sock | head -n1)"
cat > /etc/nginx/sites-available/froxlor <<NGINX
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/froxlor.crt;
    ssl_certificate_key /etc/nginx/ssl/froxlor.key;
    server_name _;
    root /var/www/html;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /froxlor/index.php?\$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
    }
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/froxlor /etc/nginx/sites-enabled/froxlor
nginx -t
systemctl reload nginx

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_http "http://127.0.0.1/froxlor/" 60

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Froxlor siap di http://<IP>/froxlor. Kredensial DB tersimpan di /root/.cravacloud/froxlor.env."
echo "CRAVA_INSTALL_OK {{stack}}"
