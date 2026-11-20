#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/redmine"

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
PG_PWD="$(openssl rand -hex 18)"
SEC_KEY="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=redmine
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=redmine
REDMINE_DB_POSTGRES=db
REDMINE_DB_USERNAME=redmine
REDMINE_DB_PASSWORD=${PG_PWD}
REDMINE_DB_DATABASE=redmine
REDMINE_SECRET_KEY_BASE=${SEC_KEY}
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

  redmine:
    image: redmine:latest
    env_file: .env
    ports:
      - "3000:3000"
    volumes:
      - ./files:/usr/src/redmine/files
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_http "http://127.0.0.1:3000/" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Redmine aktif di port 3000."
echo "CRAVA_INSTALL_OK {{stack}}"
