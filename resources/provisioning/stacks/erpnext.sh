#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/erpnext"

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

mkdir -p "$STACK_DIR"
umask 077

DB_ROOT_PWD="$(openssl rand -hex 16)"
ADMIN_PWD="$(openssl rand -hex 14)"

cat > "$STACK_DIR/.env" <<EOF
DB_ROOT_PASSWORD=${DB_ROOT_PWD}
ADMIN_PASSWORD=${ADMIN_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

# Ambil official Frappe/ERPNext production-with-docker compose template
curl -fsSL "https://raw.githubusercontent.com/frappe/frappe_docker/main/pwd.yml" -o "$STACK_DIR/compose.yml"

# Ganti credential default 'admin' pada pwd.yml dengan variabel .env aman
sed -i 's/MYSQL_ROOT_PASSWORD: admin/MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}/g' "$STACK_DIR/compose.yml"
sed -i 's/MARIADB_ROOT_PASSWORD: admin/MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}/g' "$STACK_DIR/compose.yml"
sed -i 's/--mariadb-root-password admin/--mariadb-root-password ${DB_ROOT_PASSWORD}/g' "$STACK_DIR/compose.yml"
sed -i 's/--admin-password admin/--admin-password ${ADMIN_PASSWORD}/g' "$STACK_DIR/compose.yml"
sed -i 's/"8080:8080"/"8080:8080"/g' "$STACK_DIR/compose.yml"

docker compose -f "$STACK_DIR/compose.yml" --env-file "$STACK_DIR/.env" pull
docker compose -f "$STACK_DIR/compose.yml" --env-file "$STACK_DIR/.env" up -d

# Nginx reverse proxy port 80 (+ TLS self-signed siap domain pada port 443) -> frontend :8080
mkdir -p /etc/nginx/ssl
if [[ ! -f /etc/nginx/ssl/erpnext.key ]]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/erpnext.key \
        -out /etc/nginx/ssl/erpnext.crt \
        -subj "/CN=cravacloud.local" >/dev/null 2>&1
fi

cat > /etc/nginx/sites-available/erpnext <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/erpnext.crt;
    ssl_certificate_key /etc/nginx/ssl/erpnext.key;
    server_name _;
    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $http_host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/erpnext /etc/nginx/sites-enabled/erpnext
nginx -t
systemctl enable --now nginx
systemctl reload nginx

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 8080/tcp

crava_wait_port 8080 240 tcp
crava_wait_port 80 30 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] ERPNext siap. Kredensial tersimpan aman di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
