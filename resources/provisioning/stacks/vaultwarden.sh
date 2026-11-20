#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/vaultwarden"

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

mkdir -p "$STACK_DIR/vw-data" "$STACK_DIR/caddy-data" "$STACK_DIR/caddy-config"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
ADMIN_TOK="$(openssl rand -base64 32 | tr -d '\n')"

cat > "$STACK_DIR/.env" <<EOF
DOMAIN=https://${PUBLIC_IP}
ADMIN_TOKEN=${ADMIN_TOK}
WEBSOCKET_ENABLED=true
SIGNUPS_ALLOWED=true
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/Caddyfile" <<'CADDY'
:443 {
    tls internal
    reverse_proxy vaultwarden:80
}
:80 {
    reverse_proxy vaultwarden:80
}
CADDY

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  vaultwarden:
    image: vaultwarden/server:latest
    env_file: .env
    ports:
      - "8080:80"
    volumes:
      - ./vw-data:/data
    restart: unless-stopped

  caddy:
    image: caddy:2-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - ./caddy-data:/data
      - ./caddy-config:/config
    depends_on:
      - vaultwarden
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 8080/tcp
crava_wait_port 8080 120 tcp
crava_wait_port 443 60 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Vaultwarden aktif (HTTPS internal TLS di port 443 & direct 8080). Token disimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
