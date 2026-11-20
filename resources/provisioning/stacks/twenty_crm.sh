#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/twenty-crm"

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

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 20)"
APP_SEC="$(openssl rand -base64 32 | tr -d '\n')"

cat > "$STACK_DIR/.env" <<EOF
TAG=latest
PG_DATABASE_USER=postgres
PG_DATABASE_PASSWORD=${PG_PWD}
PG_DATABASE_HOST=db
PG_DATABASE_PORT=5432
PG_DATABASE_NAME=default
SERVER_URL=http://${PUBLIC_IP}:3000
REDIS_URL=redis://redis:6379
APP_SECRET=${APP_SEC}
STORAGE_TYPE=local
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
name: twenty
services:
  server:
    image: twentycrm/twenty:${TAG:-latest}
    volumes:
      - server-local-data:/app/packages/twenty-server/.local-storage
    ports:
      - "3000:3000"
    env_file:
      - .env
    environment:
      PG_DATABASE_URL: postgres://${PG_DATABASE_USER}:${PG_DATABASE_PASSWORD}@${PG_DATABASE_HOST}:${PG_DATABASE_PORT}/${PG_DATABASE_NAME}
    depends_on:
      db:
        condition: service_healthy
    restart: always

  worker:
    image: twentycrm/twenty:${TAG:-latest}
    volumes:
      - server-local-data:/app/packages/twenty-server/.local-storage
    command: ["yarn", "worker:prod"]
    env_file:
      - .env
    environment:
      PG_DATABASE_URL: postgres://${PG_DATABASE_USER}:${PG_DATABASE_PASSWORD}@${PG_DATABASE_HOST}:${PG_DATABASE_PORT}/${PG_DATABASE_NAME}
      DISABLE_DB_MIGRATIONS: "true"
      DISABLE_CRON_JOBS_REGISTRATION: "true"
    depends_on:
      db:
        condition: service_healthy
      server:
        condition: service_started
    restart: always

  db:
    image: postgres:16-alpine
    volumes:
      - db-data:/var/lib/postgresql/data
    environment:
      POSTGRES_USER: ${PG_DATABASE_USER}
      POSTGRES_PASSWORD: ${PG_DATABASE_PASSWORD}
      POSTGRES_DB: ${PG_DATABASE_NAME}
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${PG_DATABASE_USER} -d ${PG_DATABASE_NAME}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: always

  redis:
    image: redis:7-alpine
    command: ["--maxmemory-policy", "noeviction"]
    restart: always

volumes:
  db-data:
  server-local-data:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Twenty CRM aktif di http://${PUBLIC_IP}:3000. Rahasia disimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
