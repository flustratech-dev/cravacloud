#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
DISCOURSE_DIR="/var/discourse"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install git curl ca-certificates openssl nginx
crava_ensure_swap 2048
crava_ensure_docker

if [[ ! -d "$DISCOURSE_DIR/.git" ]]; then
    git clone https://github.com/discourse/discourse_docker.git "$DISCOURSE_DIR"
fi

docker pull discourse/base:release

mkdir -p "$DISCOURSE_DIR/containers"
umask 077
DB_PWD="$(openssl rand -hex 20)"
cp "$DISCOURSE_DIR/samples/standalone.yml" "$DISCOURSE_DIR/containers/app.yml"
chmod 600 "$DISCOURSE_DIR/containers/app.yml"

sed -i "s/DISCOURSE_HOSTNAME: .*/DISCOURSE_HOSTNAME: 'forum.cravacloud.local'/" "$DISCOURSE_DIR/containers/app.yml"
grep -q "DISCOURSE_DB_PASSWORD:" "$DISCOURSE_DIR/containers/app.yml" || printf "\n  DISCOURSE_DB_PASSWORD: '%s'\n" "$DB_PWD" >> "$DISCOURSE_DIR/containers/app.yml"

mkdir -p /etc/nginx/ssl
if [[ ! -f /etc/nginx/ssl/discourse.key ]]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/nginx/ssl/discourse.key \
        -out /etc/nginx/ssl/discourse.crt \
        -subj "/CN=cravacloud.local" >/dev/null 2>&1
fi

mkdir -p /var/www/discourse-setup
cat > /var/www/discourse-setup/index.html <<'HTML'
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Discourse Siap Dikonfigurasi</title></head>
<body style="font-family:sans-serif;max-width:680px;margin:48px auto;padding:24px;background:#0f172a;color:#e2e8f0">
<h1>Discourse Docker Siap Dibangun</h1>
<p>Repository resmi <code>/var/discourse</code> dan image dasar sudah terpasang.</p>
<ol>
<li>Arahkan DNS A-record domain Anda ke IP VPS ini.</li>
<li>Edit <code>/var/discourse/containers/app.yml</code> (DISCOURSE_HOSTNAME, DISCOURSE_DEVELOPER_EMAILS, SMTP).</li>
<li>Hentikan landing nginx sementara lalu jalankan bootstrap: <code>systemctl disable --now nginx && /var/discourse/launcher rebuild app</code>.</li>
</ol>
</body></html>
HTML

cat > /etc/nginx/sites-available/discourse <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/discourse.crt;
    ssl_certificate_key /etc/nginx/ssl/discourse.key;
    server_name _;
    root /var/www/discourse-setup;
    index index.html;
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/discourse /etc/nginx/sites-enabled/discourse
nginx -t
systemctl enable --now nginx
systemctl reload nginx

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_http "http://127.0.0.1/" 30

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Discourse siap dikonfigurasi domain & SMTP di $DISCOURSE_DIR/containers/app.yml."
echo "CRAVA_INSTALL_OK {{stack}}"
