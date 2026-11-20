#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/memos"

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
  memos:
    image: neosmemo/memos:stable
    ports:
      - "5230:5230"
    volumes:
      - ./data:/var/opt/memos
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 5230/tcp
crava_wait_http "http://127.0.0.1:5230/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Memos Notes aktif di port 5230."
echo "CRAVA_INSTALL_OK {{stack}}"
