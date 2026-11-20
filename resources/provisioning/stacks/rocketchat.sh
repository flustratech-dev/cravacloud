#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/rocketchat"

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

mkdir -p "$STACK_DIR/uploads" "$STACK_DIR/db"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"

cat > "$STACK_DIR/.env" <<EOF
ROOT_URL=http://${PUBLIC_IP}:3000
PORT=3000
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  mongodb:
    image: mongo:7
    command: mongod --oplogSize 128 --replSet rs0
    volumes:
      - ./db:/data/db
    restart: unless-stopped

  rocketchat:
    image: rocket.chat:latest
    env_file: .env
    environment:
      MONGO_URL: mongodb://mongodb:27017/rocketchat?replicaSet=rs0
      MONGO_OPLOG_URL: mongodb://mongodb:27017/local?replicaSet=rs0
      DEPLOY_METHOD: docker
    ports:
      - "3000:3000"
    volumes:
      - ./uploads:/app/uploads
    depends_on:
      - mongodb
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d mongodb
sleep 6
docker compose -f "$STACK_DIR/docker-compose.yml" exec -T mongodb mongosh --eval "rs.initiate({_id:'rs0',members:[{_id:0,host:'mongodb:27017'}]})" || true
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Rocket.Chat aktif di http://${PUBLIC_IP}:3000."
echo "CRAVA_INSTALL_OK {{stack}}"
