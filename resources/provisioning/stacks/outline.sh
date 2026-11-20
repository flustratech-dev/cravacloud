#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/outline"

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

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"
SEC_KEY="$(openssl rand -hex 32)"
UTILS_SEC="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
NODE_ENV=production
URL=http://${PUBLIC_IP}:3000
PORT=3000
SECRET_KEY=${SEC_KEY}
UTILS_SECRET=${UTILS_SEC}
POSTGRES_PASSWORD=${PG_PWD}
DATABASE_URL=postgres://outline:${PG_PWD}@postgres:5432/outline
PGSSLMODE=disable
REDIS_URL=redis://redis:6379
FILE_STORAGE=local
FILE_STORAGE_LOCAL_ROOT_DIR=/var/lib/outline/data
FILE_STORAGE_UPLOAD_MAX_SIZE=26214400
FORCE_HTTPS=false
DEFAULT_LANGUAGE=en_US
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  outline:
    image: outlinewiki/outline:latest
    env_file:
      - .env
    ports:
      - "3000:3000"
    volumes:
      - ./data:/var/lib/outline/data
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    restart: unless-stopped

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_USER: outline
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
      POSTGRES_DB: outline
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U outline -d outline"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    restart: unless-stopped

volumes:
  pgdata:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 150 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Outline Wiki aktif di port 3000. Lengkapi OIDC/OAuth di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
