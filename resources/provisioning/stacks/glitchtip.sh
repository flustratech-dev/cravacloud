#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/glitchtip"

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

mkdir -p "$STACK_DIR/uploads"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"
SEC_KEY="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_DB=glitchtip
POSTGRES_USER=glitchtip
POSTGRES_PASSWORD=${PG_PWD}
DATABASE_URL=postgres://glitchtip:${PG_PWD}@postgres:5432/glitchtip
SECRET_KEY=${SEC_KEY}
PORT=8000
GLITCHTIP_DOMAIN=http://${PUBLIC_IP}:8000
DEFAULT_FROM_EMAIL=noreply@cravacloud.local
CELERY_WORKER_AUTOSCALE=1,3
CELERY_WORKER_MAX_TASKS_PER_CHILD=10000
ENABLE_USER_REGISTRATION=true
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

  web:
    image: glitchtip/glitchtip:latest
    env_file: .env
    environment:
      REDIS_URL: redis://redis:6379/0
    ports:
      - "8000:8000"
    volumes:
      - ./uploads:/code/uploads
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    restart: unless-stopped

  worker:
    image: glitchtip/glitchtip:latest
    command: ./bin/run-celery-with-beat.sh
    env_file: .env
    environment:
      REDIS_URL: redis://redis:6379/0
    volumes:
      - ./uploads:/code/uploads
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
docker compose -f "$STACK_DIR/docker-compose.yml" up -d postgres redis
sleep 5
docker compose -f "$STACK_DIR/docker-compose.yml" run --rm web ./manage.py migrate
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8000/tcp
crava_wait_port 8000 150 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] GlitchTip aktif di http://${PUBLIC_IP}:8000."
echo "CRAVA_INSTALL_OK {{stack}}"
