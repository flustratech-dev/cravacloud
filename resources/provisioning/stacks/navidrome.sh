#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/navidrome"

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

mkdir -p "$STACK_DIR/navidrome-data" "$STACK_DIR/music" "$STACK_DIR/audiobooks" "$STACK_DIR/podcasts" "$STACK_DIR/abs-config" "$STACK_DIR/abs-metadata"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  navidrome:
    image: deluan/navidrome:latest
    ports:
      - "4533:4533"
    environment:
      ND_SCANSCHEDULE: 1h
      ND_LOGLEVEL: info
    volumes:
      - ./navidrome-data:/data
      - ./music:/music:ro
    restart: unless-stopped

  audiobookshelf:
    image: ghcr.io/advplyr/audiobookshelf:latest
    ports:
      - "13378:80"
    volumes:
      - ./audiobooks:/audiobooks
      - ./podcasts:/podcasts
      - ./abs-config:/config
      - ./abs-metadata:/metadata
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 4533/tcp
crava_ufw_allow 13378/tcp
crava_wait_http "http://127.0.0.1:4533/" 90
crava_wait_port 13378 90 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Navidrome (:4533) & Audiobookshelf (:13378) aktif."
echo "CRAVA_INSTALL_OK {{stack}}"
