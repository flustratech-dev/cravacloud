#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/listmonk"

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

PG_PWD="$(openssl rand -hex 18)"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=listmonk
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=listmonk
LISTMONK_app__address=0.0.0.0:9000
LISTMONK_app__admin_username=admin
LISTMONK_app__admin_password=${ADMIN_PWD}
LISTMONK_db__host=db
LISTMONK_db__port=5432
LISTMONK_db__user=listmonk
LISTMONK_db__password=${PG_PWD}
LISTMONK_db__database=listmonk
LISTMONK_db__ssl_mode=disable
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  db:
    image: postgres:16-alpine
    env_file: .env
    volumes:
      - db-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  app:
    image: listmonk/listmonk:latest
    env_file: .env
    ports:
      - "9000:9000"
    volumes:
      - ./uploads:/listmonk/uploads
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped

volumes:
  db-data:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d db
sleep 4
docker compose -f "$STACK_DIR/docker-compose.yml" run --rm app ./listmonk --install --idempotent --yes
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 9000/tcp
crava_wait_http "http://127.0.0.1:9000/" 120

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Listmonk aktif di port 9000. Login tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
