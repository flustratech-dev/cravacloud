#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/changedetection"

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

mkdir -p "$STACK_DIR/datastore"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  changedetection:
    image: dgtlmoon/changedetection.io:latest
    ports:
      - "5000:5000"
    volumes:
      - ./datastore:/datastore
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 5000/tcp
crava_wait_http "http://127.0.0.1:5000/" 120

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Changedetection.io aktif di port 5000."
echo "CRAVA_INSTALL_OK {{stack}}"
