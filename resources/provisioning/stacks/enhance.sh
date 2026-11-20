#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"

crava_require_os ubuntu

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl nginx
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p /opt/enhance /var/www/enhance-setup /etc/nginx/ssl
curl -fsSL "https://enhance.com/install.sh" -o /opt/enhance/install.sh
chmod +x /opt/enhance/install.sh

if [[ ! -f /etc/nginx/ssl/enhance.key ]]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/enhance.key \
        -out /etc/nginx/ssl/enhance.crt \
        -subj "/CN=cravacloud.local" >/dev/null 2>&1
fi

cat > /var/www/enhance-setup/index.html <<'HTML'
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Enhance Control Panel (BYOL)</title></head>
<body style="font-family:sans-serif;max-width:680px;margin:48px auto;padding:24px;background:#0f172a;color:#e2e8f0">
<h1>Enhance Control Panel Siap Aktivasi</h1>
<p>Installer resmi Enhance sudah diunduh di <code>/opt/enhance/install.sh</code> beserta Docker runtime &amp; swap 2 GB.</p>
<p>Login via SSH, hentikan sementara nginx landing (<code>systemctl disable --now nginx</code>), lalu jalankan <code>/opt/enhance/install.sh</code> menggunakan akun &amp; registration key lisensi Enhance Anda.</p>
</body></html>
HTML

cat > /etc/nginx/sites-available/enhance <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/enhance.crt;
    ssl_certificate_key /etc/nginx/ssl/enhance.key;
    server_name _;
    root /var/www/enhance-setup;
    index index.html;
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/enhance /etc/nginx/sites-enabled/enhance
nginx -t
systemctl enable --now nginx
systemctl reload nginx

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_http "http://127.0.0.1/" 30

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Enhance Control Panel (BYOL) siap aktivasi registrasi via SSH di /opt/enhance/install.sh."
echo "CRAVA_INSTALL_OK {{stack}}"
