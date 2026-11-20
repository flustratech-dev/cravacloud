#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/caddy"

crava_require_os ubuntu debian rocky centos

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/site"
cat > "$STACK_DIR/site/index.html" <<'HTML'
<!doctype html><html><body style="font-family:sans-serif;padding:40px"><h1>Caddy Web Server Aktif</h1><p>Edit <code>/opt/caddy/Caddyfile</code> untuk menambahkan domain &amp; sertifikat Let's Encrypt otomatis.</p></body></html>
HTML

cat > "$STACK_DIR/Caddyfile" <<'CADDY'
:80 {
    root * /usr/share/caddy
    file_server
}
:443 {
    tls internal
    root * /usr/share/caddy
    file_server
}
CADDY

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  caddy:
    image: caddy:2-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - ./site:/usr/share/caddy:ro
      - caddy_data:/data
      - caddy_config:/config
    restart: unless-stopped
volumes:
  caddy_data:
  caddy_config:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_http "http://127.0.0.1/" 60

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Caddy Web Server aktif di port 80/443."
echo "CRAVA_INSTALL_OK {{stack}}"
