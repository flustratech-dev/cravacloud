#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/cryptpad"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/blob" "$STACK_DIR/block" "$STACK_DIR/data" "$STACK_DIR/datastore" "$STACK_DIR/customize"
chown -R 4001:4001 "$STACK_DIR"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  cryptpad:
    image: cryptpad/cryptpad:latest
    ports:
      - "3000:3000"
    volumes:
      - ./blob:/cryptpad/blob
      - ./block:/cryptpad/block
      - ./customize:/cryptpad/customize
      - ./data:/cryptpad/data
      - ./datastore:/cryptpad/datastore
    environment:
      CPAD_MAIN_DOMAIN: http://localhost:3000
      CPAD_SANDBOX_DOMAIN: http://localhost:3001
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_http "http://127.0.0.1:3000/" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] CryptPad aktif di port 3000."
echo "CRAVA_INSTALL_OK {{stack}}"
