#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/palworld"

crava_require_os ubuntu

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/palworld"
chmod 777 "$STACK_DIR/palworld"
umask 077
ADMIN_PWD="$(openssl rand -hex 12)"
SRV_PWD="$(openssl rand -hex 10)"

cat > "$STACK_DIR/.env" <<EOF
PUID=1000
PGID=1000
PORT=8211
PLAYERS=16
MULTITHREADING=true
RCON_ENABLED=true
RCON_PORT=25575
TZ=Asia/Jakarta
ADMIN_PASSWORD=${ADMIN_PWD}
SERVER_PASSWORD=${SRV_PWD}
SERVER_NAME=CravaCloud Palworld Server
UPDATE_ON_BOOT=true
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  palworld:
    image: thijsvanloef/palworld-server-docker:latest
    container_name: crava-palworld
    env_file: .env
    ports:
      - "8211:8211/udp"
      - "27015:27015/udp"
    volumes:
      - ./palworld:/palworld
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8211/udp
crava_ufw_allow 27015/udp
crava_wait_container crava-palworld 180

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Palworld Dedicated Server berjalan (UDP 8211 & 27015). Password dunia tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
