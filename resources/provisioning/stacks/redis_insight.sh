#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/redis-insight"

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

mkdir -p "$STACK_DIR/redis-data" "$STACK_DIR/insight-data"
chmod 777 "$STACK_DIR/insight-data"
umask 077

REDIS_PWD="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
REDIS_PASSWORD=${REDIS_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  redis:
    image: redis:7-alpine
    env_file: .env
    command: ["sh", "-c", "exec redis-server --appendonly yes --requirepass \"$REDIS_PASSWORD\""]
    ports:
      - "6379:6379"
    volumes:
      - ./redis-data:/data
    restart: unless-stopped

  redisinsight:
    image: redis/redisinsight:latest
    ports:
      - "5540:5540"
    volumes:
      - ./insight-data:/data
    depends_on:
      - redis
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 6379/tcp
crava_ufw_allow 5540/tcp
crava_wait_port 6379 60 tcp
crava_wait_port 5540 90 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Redis (:6379) & Redis Insight GUI (:5540) aktif. Password tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
