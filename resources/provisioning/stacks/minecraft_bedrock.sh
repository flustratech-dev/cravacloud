#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/minecraft-bedrock"

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

mkdir -p "$STACK_DIR/data"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  bedrock:
    image: itzg/minecraft-bedrock-server:latest
    container_name: crava-mc-bedrock
    environment:
      EULA: "TRUE"
      GAMEMODE: survival
      DIFFICULTY: normal
      SERVER_NAME: "CravaCloud Bedrock"
      ENABLE_COMMAND_BLOCK: "true"
    ports:
      - "19132:19132/udp"
      - "19133:19133/udp"
    volumes:
      - ./data:/data
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 19132/udp
crava_ufw_allow 19133/udp
crava_wait_port 19132 180 udp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Minecraft Bedrock Dedicated Server aktif di port UDP 19132 & 19133."
echo "CRAVA_INSTALL_OK {{stack}}"
