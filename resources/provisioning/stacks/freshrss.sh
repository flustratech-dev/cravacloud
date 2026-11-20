#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/freshrss"

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

mkdir -p "$STACK_DIR/config"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  freshrss:
    image: lscr.io/linuxserver/freshrss:latest
    environment:
      PUID: "1000"
      PGID: "1000"
      TZ: "Asia/Jakarta"
    ports:
      - "8080:80"
    volumes:
      - ./config:/config
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] FreshRSS Reader aktif di port 8080."
echo "CRAVA_INSTALL_OK {{stack}}"
