#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/owncast"

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

mkdir -p "$STACK_DIR/data"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  owncast:
    image: owncast/owncast:latest
    ports:
      - "8080:8080"
      - "1935:1935"
    volumes:
      - ./data:/app/data
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_ufw_allow 1935/tcp
crava_wait_http "http://127.0.0.1:8080/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Owncast Live Streaming aktif (Web :8080, RTMP :1935)."
echo "CRAVA_INSTALL_OK {{stack}}"
