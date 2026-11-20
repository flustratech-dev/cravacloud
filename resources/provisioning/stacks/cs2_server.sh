#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/cs2"

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

mkdir -p "$STACK_DIR/data"
chmod 777 "$STACK_DIR/data"
umask 077
RCON_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
CS2_SERVERNAME=CravaCloud CS2 Server
CS2_PORT=27015
CS2_MAXPLAYERS=16
CS2_GAMEALIAS=competitive
CS2_STARTMAP=de_dust2
CS2_RCONPW=${RCON_PWD}
SRCDS_TOKEN=
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  cs2:
    image: joedwards32/cs2:latest
    container_name: crava-cs2
    env_file: .env
    ports:
      - "27015:27015/tcp"
      - "27015:27015/udp"
      - "27020:27020/udp"
    volumes:
      - ./data:/home/steam/cs2-dedicated
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 27015/tcp
crava_ufw_allow 27015/udp
crava_ufw_allow 27020/udp
crava_wait_container crava-cs2 180

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] CS2 Server berjalan (TCP/UDP 27015 & GOTV UDP 27020). Lengkapi SRCDS_TOKEN (Steam GSLT) di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
