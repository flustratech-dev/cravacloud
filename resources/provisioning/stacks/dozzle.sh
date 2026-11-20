#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/dozzle"

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

mkdir -p "$STACK_DIR"
cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  dozzle:
    image: amir20/dozzle:latest
    ports:
      - "8080:8080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/" 60

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Dozzle Log Viewer aktif di port 8080."
echo "CRAVA_INSTALL_OK {{stack}}"
