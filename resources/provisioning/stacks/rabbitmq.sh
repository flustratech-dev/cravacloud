#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/rabbitmq"

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
RMQ_PWD="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
RABBITMQ_DEFAULT_USER=cravaadmin
RABBITMQ_DEFAULT_PASS=${RMQ_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  rabbitmq:
    image: rabbitmq:3-management-alpine
    env_file: .env
    ports:
      - "5672:5672"
      - "15672:15672"
    volumes:
      - ./data:/var/lib/rabbitmq
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 5672/tcp
crava_ufw_allow 15672/tcp
crava_wait_http "http://127.0.0.1:15672/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] RabbitMQ aktif (AMQP :5672 & Management UI :15672). Kredensial tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
