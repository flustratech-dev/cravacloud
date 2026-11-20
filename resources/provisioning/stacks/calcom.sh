#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/calcom"

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
NEXTAUTH_SEC="$(openssl rand -base64 32 | tr -d '\n')"
ENC_KEY="$(openssl rand -hex 16)"

cat > "$STACK_DIR/.env" <<EOF
NEXT_PUBLIC_LICENSE_CONSENT=agree
LICENSE=AGPLv3
NEXT_PUBLIC_WEBAPP_URL=http://${PUBLIC_IP}:3000
WEB_APP_URL=http://${PUBLIC_IP}:3000/
NEXTAUTH_SECRET=${NEXTAUTH_SEC}
CALENDSO_ENCRYPTION_KEY=${ENC_KEY}
POSTGRES_USER=calcom
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=calendso
DATABASE_HOST=database:5432
DATABASE_URL=postgresql://calcom:${PG_PWD}@database:5432/calendso
DATABASE_DIRECT_URL=postgresql://calcom:${PG_PWD}@database:5432/calendso
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_URL=redis://redis:6379
CALCOM_TELEMETRY_DISABLED=1
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  database:
    image: postgres:16-alpine
    restart: always
    env_file: .env
    volumes:
      - database-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10

  redis:
    image: redis:7-alpine
    restart: always

  calcom:
    image: calcom/cal.com:latest
    restart: always
    ports:
      - "3000:3000"
    env_file: .env
    depends_on:
      database:
        condition: service_healthy
      redis:
        condition: service_started

volumes:
  database-data:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Cal.com aktif di http://${PUBLIC_IP}:3000."
echo "CRAVA_INSTALL_OK {{stack}}"
