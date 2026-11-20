#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/ntfy"

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

mkdir -p "$STACK_DIR/cache" "$STACK_DIR/etc" "$STACK_DIR/gotify"
umask 077
GOTIFY_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
GOTIFY_DEFAULTUSER_NAME=admin
GOTIFY_DEFAULTUSER_PASS=${GOTIFY_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  ntfy:
    image: binwiederhier/ntfy:latest
    command: ["serve"]
    environment:
      TZ: "Asia/Jakarta"
      NTFY_CACHE_FILE: /var/cache/ntfy/cache.db
    volumes:
      - ./cache:/var/cache/ntfy
      - ./etc:/etc/ntfy
    ports:
      - "80:80"
    restart: unless-stopped

  gotify:
    image: gotify/server:latest
    env_file: .env
    ports:
      - "127.0.0.1:8085:80"
    volumes:
      - ./gotify:/app/data
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_wait_http "http://127.0.0.1/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Ntfy Push Server (:80) + Gotify (:127.0.0.1:8085) aktif."
echo "CRAVA_INSTALL_OK {{stack}}"
