#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/dockge"

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

mkdir -p "$STACK_DIR/data" /opt/stacks

cat > "$STACK_DIR/compose.yaml" <<'YAML'
services:
  dockge:
    image: louislam/dockge:1
    restart: unless-stopped
    ports:
      - "5001:5001"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./data:/app/data
      - /opt/stacks:/opt/stacks
    environment:
      - DOCKGE_STACKS_DIR=/opt/stacks
YAML

docker compose -f "$STACK_DIR/compose.yaml" pull
docker compose -f "$STACK_DIR/compose.yaml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 5001/tcp
crava_wait_http "http://127.0.0.1:5001/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Dockge aktif di http://<IP>:5001."
echo "CRAVA_INSTALL_OK {{stack}}"
