#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/mongodb"

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

mkdir -p "$STACK_DIR/data"
umask 077

MONGO_PWD="$(openssl rand -hex 18)"
UI_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
MONGO_INITDB_ROOT_USERNAME=admin
MONGO_INITDB_ROOT_PASSWORD=${MONGO_PWD}
ME_CONFIG_MONGODB_ADMINUSERNAME=admin
ME_CONFIG_MONGODB_ADMINPASSWORD=${MONGO_PWD}
ME_CONFIG_MONGODB_SERVER=mongodb
ME_CONFIG_MONGODB_PORT=27017
ME_CONFIG_BASICAUTH_USERNAME=admin
ME_CONFIG_BASICAUTH_PASSWORD=${UI_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  mongodb:
    image: mongo:7
    env_file: .env
    ports:
      - "27017:27017"
    volumes:
      - ./data:/data/db
    restart: unless-stopped

  mongo-express:
    image: mongo-express:latest
    env_file: .env
    ports:
      - "8081:8081"
    depends_on:
      - mongodb
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 27017/tcp
crava_ufw_allow 8081/tcp
crava_wait_port 27017 90 tcp
crava_wait_port 8081 90 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] MongoDB (:27017) & Mongo Express (:8081) aktif. Kredensial tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
