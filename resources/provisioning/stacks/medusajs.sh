#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/medusajs"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl git python3 build-base 2>/dev/null || crava_pkg_install curl ca-certificates openssl git python3 build-essential
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/app"
umask 077
PG_PWD="$(openssl rand -hex 18)"
JWT_SEC="$(openssl rand -hex 32)"
COOKIE_SEC="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=medusa
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=medusa
DATABASE_URL=postgres://medusa:${PG_PWD}@postgres:5432/medusa
REDIS_URL=redis://redis:6379
JWT_SECRET=${JWT_SEC}
COOKIE_SECRET=${COOKIE_SEC}
STORE_CORS=http://localhost:8000,http://127.0.0.1:7001
ADMIN_CORS=http://localhost:7001,http://127.0.0.1:9000
AUTH_CORS=http://localhost:7001,http://127.0.0.1:9000
MEDUSA_ADMIN_ONBOARDING_TYPE=nextjs
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  postgres:
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

  redis:
    image: redis:7-alpine
    restart: unless-stopped

  medusa:
    image: node:22-alpine
    working_dir: /app
    env_file: .env
    ports:
      - "9000:9000"
      - "7001:7001"
    volumes:
      - ./app:/app
    command: >
      sh -c "if [ ! -f package.json ]; then
        npx --yes create-medusa-app@latest --skip-db --no-browser --directory-path /app;
      fi &&
      npx medusa db:migrate &&
      npm run dev"
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    restart: unless-stopped

volumes:
  pgdata:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 9000/tcp
crava_ufw_allow 7001/tcp
crava_wait_port 9000 300 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] MedusaJS Commerce aktif (API/Admin :9000/app & :7001)."
echo "CRAVA_INSTALL_OK {{stack}}"
