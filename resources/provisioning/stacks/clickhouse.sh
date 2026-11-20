#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/clickhouse"

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

mkdir -p "$STACK_DIR/data" "$STACK_DIR/logs"
umask 077
CH_PWD="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
CLICKHOUSE_DB=default
CLICKHOUSE_USER=crava
CLICKHOUSE_PASSWORD=${CH_PWD}
CLICKHOUSE_DEFAULT_ACCESS_MANAGEMENT=1
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  clickhouse:
    image: clickhouse/clickhouse-server:24-alpine
    env_file: .env
    ports:
      - "8123:8123"
      - "9000:9000"
    volumes:
      - ./data:/var/lib/clickhouse
      - ./logs:/var/log/clickhouse-server
    ulimits:
      nofile:
        soft: 262144
        hard: 262144
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8123/tcp
crava_ufw_allow 9000/tcp
crava_wait_http "http://127.0.0.1:8123/ping" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] ClickHouse aktif (HTTP :8123/play & Native :9000). Kredensial tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
