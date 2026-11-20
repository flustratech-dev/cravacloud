#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/activepieces"

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
PG_PWD="$(openssl rand -hex 18)"
ENC_KEY="$(openssl rand -hex 16)"
JWT_SEC="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
AP_ENGINE_EXECUTABLE_PATH=dist/packages/engine/main.js
AP_ENVIRONMENT=prod
AP_FRONTEND_URL=http://${PUBLIC_IP}
AP_WEBHOOK_TIMEOUT_SECONDS=30
AP_TRIGGER_DEFAULT_POLL_INTERVAL=5
AP_EXECUTION_MODE=UNSANDBOXED
AP_ENCRYPTION_KEY=${ENC_KEY}
AP_JWT_SECRET=${JWT_SEC}
AP_POSTGRES_DATABASE=activepieces
AP_POSTGRES_HOST=postgres
AP_POSTGRES_PORT=5432
AP_POSTGRES_USERNAME=postgres
AP_POSTGRES_PASSWORD=${PG_PWD}
AP_REDIS_HOST=redis
AP_REDIS_PORT=6379
AP_DB_TYPE=POSTGRES
AP_TELEMETRY_ENABLED=false
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${AP_POSTGRES_DATABASE}
      POSTGRES_USER: ${AP_POSTGRES_USERNAME}
      POSTGRES_PASSWORD: ${AP_POSTGRES_PASSWORD}
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${AP_POSTGRES_USERNAME} -d ${AP_POSTGRES_DATABASE}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    volumes:
      - redisdata:/data
    restart: unless-stopped

  activepieces:
    image: ghcr.io/activepieces/activepieces:latest
    env_file: .env
    ports:
      - "80:80"
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
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_wait_port 80 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Activepieces aktif di http://${PUBLIC_IP}."
echo "CRAVA_INSTALL_OK {{stack}}"
