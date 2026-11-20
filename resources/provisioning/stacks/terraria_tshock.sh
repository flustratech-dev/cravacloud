#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/terraria"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/worlds" "$STACK_DIR/plugins"
umask 077
WORLD_PWD="$(openssl rand -hex 10)"

cat > "$STACK_DIR/.env" <<EOF
WORLD_FILENAME=CravaWorld.wld
TERRARIA_PASS=${WORLD_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  terraria:
    image: ryshe/terraria:latest
    container_name: crava-terraria
    env_file: .env
    command: ["-world", "/root/.local/share/Terraria/Worlds/CravaWorld.wld", "-autocreate", "2"]
    ports:
      - "7777:7777"
      - "7878:7878"
    volumes:
      - ./worlds:/root/.local/share/Terraria/Worlds
      - ./plugins:/plugins
    stdin_open: true
    tty: true
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 7777/tcp
crava_ufw_allow 7878/tcp
crava_wait_port 7777 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Terraria tShock Server aktif di TCP 7777 (REST API 7878). Password tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
