#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/crafty-controller"

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

mkdir -p "$STACK_DIR/backups" "$STACK_DIR/logs" "$STACK_DIR/servers" "$STACK_DIR/config" "$STACK_DIR/import"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  crafty:
    image: registry.gitlab.com/crafty-controller/crafty-4:latest
    container_name: crafty_container
    environment:
      TZ: "Asia/Jakarta"
    ports:
      - "8443:8443"
      - "25565:25565/tcp"
      - "19132:19132/udp"
    volumes:
      - ./backups:/crafty/backups
      - ./logs:/crafty/logs
      - ./servers:/crafty/servers
      - ./config:/crafty/app/config
      - ./import:/crafty/import
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8443/tcp
crava_ufw_allow 25565/tcp
crava_ufw_allow 19132/udp
crava_wait_port 8443 150 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Crafty Controller aktif di https://<IP>:8443. Kredensial awal tersedia di $STACK_DIR/config/default-creds.txt."
echo "CRAVA_INSTALL_OK {{stack}}"
