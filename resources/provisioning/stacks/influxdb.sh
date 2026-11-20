#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/influxdb"

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

mkdir -p "$STACK_DIR/data" "$STACK_DIR/config"
umask 077
ADMIN_PWD="$(openssl rand -hex 14)"
ADMIN_TOK="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
DOCKER_INFLUXDB_INIT_MODE=setup
DOCKER_INFLUXDB_INIT_USERNAME=admin
DOCKER_INFLUXDB_INIT_PASSWORD=${ADMIN_PWD}
DOCKER_INFLUXDB_INIT_ORG=cravacloud
DOCKER_INFLUXDB_INIT_BUCKET=metrics
DOCKER_INFLUXDB_INIT_ADMIN_TOKEN=${ADMIN_TOK}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  influxdb:
    image: influxdb:2-alpine
    env_file: .env
    ports:
      - "8086:8086"
    volumes:
      - ./data:/var/lib/influxdb2
      - ./config:/etc/influxdb2
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8086/tcp
crava_wait_http "http://127.0.0.1:8086/health" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] InfluxDB Time-Series aktif di port 8086. Token & password tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
