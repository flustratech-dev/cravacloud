#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/payload-cms"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl git
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/app"
umask 077
PG_PWD="$(openssl rand -hex 18)"
PAYLOAD_SEC="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=payload
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=payload
DATABASE_URI=postgres://payload:${PG_PWD}@postgres:5432/payload
PAYLOAD_SECRET=${PAYLOAD_SEC}
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

  payload:
    image: node:22-alpine
    working_dir: /app
    env_file: .env
    ports:
      - "3000:3000"
    volumes:
      - ./app:/app
    command: >
      sh -c "if [ ! -f package.json ]; then
        npx --yes create-payload-app@latest . --template blank --db postgres --no-deps &&
        npm install --no-audit --no-fund;
      fi &&
      npm run build && npm run start"
    depends_on:
      postgres:
        condition: service_healthy
    restart: unless-stopped

volumes:
  pgdata:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 300 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Payload CMS aktif di port 3000 (/admin). Kredensial DB tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
