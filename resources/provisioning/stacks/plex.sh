#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/plex"

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

mkdir -p "$STACK_DIR/config" "$STACK_DIR/transcode" "$STACK_DIR/media"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  plex:
    image: plexinc/pms-docker:latest
    environment:
      TZ: "Asia/Jakarta"
      ADVERTISE_IP: "http://0.0.0.0:32400/"
    ports:
      - "32400:32400"
    volumes:
      - ./config:/config
      - ./transcode:/transcode
      - ./media:/data
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 32400/tcp
crava_wait_port 32400 120 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Plex Media Server aktif di http://<IP>:32400/web."
echo "CRAVA_INSTALL_OK {{stack}}"
