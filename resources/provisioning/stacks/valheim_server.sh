#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/valheim"

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

mkdir -p "$STACK_DIR/config" "$STACK_DIR/data"
umask 077
SRV_PWD="$(openssl rand -hex 8)"

cat > "$STACK_DIR/.env" <<EOF
SERVER_NAME=CravaCloud Valheim
WORLD_NAME=CravaWorld
SERVER_PASS=${SRV_PWD}
SERVER_PORT=2456
SERVER_PUBLIC=true
BACKUPS=true
TZ=Asia/Jakarta
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  valheim:
    image: lloesche/valheim-server:latest
    container_name: crava-valheim
    cap_add:
      - sys_nice
    env_file: .env
    ports:
      - "2456-2458:2456-2458/udp"
    volumes:
      - ./config:/config
      - ./data:/opt/valheim
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 2456-2458/udp
crava_wait_container crava-valheim 180

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Valheim Dedicated Server aktif (UDP 2456-2458). Password tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
