#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/vikunja"

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

mkdir -p "$STACK_DIR/files" "$STACK_DIR/db"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"
JWT_SEC="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=vikunja
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=vikunja
VIKUNJA_SERVICE_PUBLICURL=http://${PUBLIC_IP}:3456
VIKUNJA_SERVICE_JWTSECRET=${JWT_SEC}
VIKUNJA_DATABASE_HOST=db
VIKUNJA_DATABASE_TYPE=postgres
VIKUNJA_DATABASE_USER=vikunja
VIKUNJA_DATABASE_PASSWORD=${PG_PWD}
VIKUNJA_DATABASE_DATABASE=vikunja
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  db:
    image: postgres:16-alpine
    env_file: .env
    volumes:
      - ./db:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  vikunja:
    image: vikunja/vikunja:latest
    env_file: .env
    ports:
      - "3456:3456"
    volumes:
      - ./files:/app/vikunja/files
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3456/tcp
crava_wait_http "http://127.0.0.1:3456/" 120

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Vikunja Tasks aktif di http://${PUBLIC_IP}:3456."
echo "CRAVA_INSTALL_OK {{stack}}"
