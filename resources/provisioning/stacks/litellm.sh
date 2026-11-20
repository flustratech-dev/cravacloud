#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/litellm"

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

mkdir -p "$STACK_DIR"
umask 077
PG_PWD="$(openssl rand -hex 18)"
MASTER_KEY="sk-crava-$(openssl rand -hex 16)"
SALT_KEY="$(openssl rand -hex 16)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_DB=litellm
POSTGRES_USER=llmproxy
POSTGRES_PASSWORD=${PG_PWD}
DATABASE_URL=postgresql://llmproxy:${PG_PWD}@db:5432/litellm
LITELLM_MASTER_KEY=${MASTER_KEY}
LITELLM_SALT_KEY=${SALT_KEY}
STORE_MODEL_IN_DB=True
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  db:
    image: postgres:16-alpine
    env_file: .env
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  litellm:
    image: ghcr.io/berriai/litellm:main-stable
    env_file: .env
    args: []
    command: ["--port", "4000", "--host", "0.0.0.0"]
    ports:
      - "4000:4000"
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
volumes:
  pgdata:
YAML

sed -i '/args: \[\]/d' "$STACK_DIR/docker-compose.yml"
docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 4000/tcp
crava_wait_port 4000 150 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] LiteLLM Proxy aktif di port 4000 (/ui). Master key tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
