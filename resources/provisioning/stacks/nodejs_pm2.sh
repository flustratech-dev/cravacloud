#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
APP_DIR="/opt/node-app"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl nginx
crava_ensure_swap 2048

curl -fsSL "https://deb.nodesource.com/setup_22.x" | bash -
crava_wait_pkg_lock
crava_pkg_install nodejs
npm install -g pm2 pnpm

mkdir -p "$APP_DIR"
cat > "$APP_DIR/server.js" <<'JS'
const http = require('http');
const server = http.createServer((req, res) => {
  res.writeHead(200, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify({ status: 'ok', runtime: 'Node.js 22 + PM2 + Nginx' }));
});
server.listen(3000, '0.0.0.0');
JS

pm2 delete crava-node >/dev/null 2>&1 || true
pm2 start "$APP_DIR/server.js" --name crava-node
pm2 save
pm2 startup systemd -u root --hp /root >/dev/null 2>&1 || true

mkdir -p /etc/nginx/ssl
if [[ ! -f /etc/nginx/ssl/node.key ]]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/node.key \
        -out /etc/nginx/ssl/node.crt \
        -subj "/CN=cravacloud.local" >/dev/null 2>&1
fi

cat > /etc/nginx/sites-available/node-app <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/node.crt;
    ssl_certificate_key /etc/nginx/ssl/node.key;
    server_name _;

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/node-app /etc/nginx/sites-enabled/node-app
nginx -t
systemctl enable --now nginx
systemctl reload nginx

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 30 tcp
crava_wait_http "http://127.0.0.1/" 30

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Node.js 22 LTS + PM2 + Nginx aktif di $APP_DIR (port 80/443 & 3000)."
echo "CRAVA_INSTALL_OK {{stack}}"
