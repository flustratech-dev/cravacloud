#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/openspeedtest"

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

mkdir -p "$STACK_DIR/tracker-config"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  openspeedtest:
    image: openspeedtest/latest:latest
    ports:
      - "3000:3000"
    restart: unless-stopped

  speedtest-tracker:
    image: lscr.io/linuxserver/speedtest-tracker:latest
    ports:
      - "127.0.0.1:8085:80"
    environment:
      PUID: "1000"
      PGID: "1000"
    volumes:
      - ./tracker-config:/config
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_http "http://127.0.0.1:3000/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] OpenSpeedTest (:3000) & Speedtest Tracker (:127.0.0.1:8085) aktif."
echo "CRAVA_INSTALL_OK {{stack}}"
