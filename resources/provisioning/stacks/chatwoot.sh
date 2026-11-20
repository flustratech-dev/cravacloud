#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/chatwoot"

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

mkdir -p "$STACK_DIR/storage"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"
REDIS_PWD="$(openssl rand -hex 18)"
SECRET_BASE="$(openssl rand -hex 64)"

cat > "$STACK_DIR/.env" <<EOF
RAILS_ENV=production
FRONTEND_URL=http://${PUBLIC_IP}:3000
SECRET_KEY_BASE=${SECRET_BASE}
POSTGRES_HOST=postgres
POSTGRES_PORT=5432
POSTGRES_DATABASE=chatwoot_production
POSTGRES_USERNAME=postgres
POSTGRES_PASSWORD=${PG_PWD}
REDIS_URL=redis://redis:6379
REDIS_PASSWORD=${REDIS_PWD}
ENABLE_ACCOUNT_SIGNUP=true
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  postgres:
    image: pgvector/pgvector:pg16
    env_file: .env
    environment:
      POSTGRES_DB: ${POSTGRES_DATABASE}
      POSTGRES_USER: ${POSTGRES_USERNAME}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USERNAME} -d ${POSTGRES_DATABASE}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    env_file: .env
    command: ["sh", "-c", "exec redis-server --requirepass \"$REDIS_PASSWORD\""]
    volumes:
      - redisdata:/data
    restart: unless-stopped

  rails:
    image: chatwoot/chatwoot:latest
    env_file: .env
    entrypoint: docker/entrypoints/rails.sh
    command: ["bundle", "exec", "rails", "s", "-p", "3000", "-b", "0.0.0.0"]
    ports:
      - "3000:3000"
    volumes:
      - ./storage:/app/storage
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    restart: unless-stopped

  sidekiq:
    image: chatwoot/chatwoot:latest
    env_file: .env
    command: ["bundle", "exec", "sidekiq", "-C", "config/sidekiq.yml"]
    volumes:
      - ./storage:/app/storage
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    restart: unless-stopped

volumes:
  pgdata:
  redisdata:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d postgres redis
sleep 6
docker compose -f "$STACK_DIR/docker-compose.yml" run --rm rails bundle exec rails db:chatwoot_prepare
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Chatwoot aktif di http://${PUBLIC_IP}:3000."
echo "CRAVA_INSTALL_OK {{stack}}"
