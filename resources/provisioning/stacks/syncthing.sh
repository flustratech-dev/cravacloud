#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/syncthing"

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
  syncthing:
    image: syncthing/syncthing:latest
    environment:
      PUID: "1000"
      PGID: "1000"
    ports:
      - "8384:8384"
      - "22000:22000/tcp"
      - "22000:22000/udp"
    volumes:
      - ./data:/var/syncthing
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8384/tcp
crava_ufw_allow 22000/tcp
crava_ufw_allow 22000/udp

crava_wait_http "http://127.0.0.1:8384/" 90
crava_wait_port 22000 60 udp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Syncthing Sync Server aktif (Web GUI :8384 & Sync :22000 TCP/UDP)."
echo "CRAVA_INSTALL_OK {{stack}}"
