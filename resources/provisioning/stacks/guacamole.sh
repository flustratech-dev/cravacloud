#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/guacamole"

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

mkdir -p "$STACK_DIR/init"
umask 077

PG_PWD="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_DB=guacamole_db
POSTGRES_USER=guacamole_user
POSTGRES_PASSWORD=${PG_PWD}
POSTGRESQL_DATABASE=guacamole_db
POSTGRESQL_USER=guacamole_user
POSTGRESQL_PASSWORD=${PG_PWD}
POSTGRESQL_HOSTNAME=postgres
GUACD_HOSTNAME=guacd
EOF
chmod 600 "$STACK_DIR/.env"

docker pull guacamole/guacamole:latest
docker run --rm guacamole/guacamole:latest /opt/guacamole/bin/initdb.sh --postgresql > "$STACK_DIR/init/initdb.sql"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  postgres:
    image: postgres:16-alpine
    env_file: .env
    volumes:
      - ./init:/docker-entrypoint-initdb.d:ro
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  guacd:
    image: guacamole/guacd:latest
    restart: unless-stopped

  guacamole:
    image: guacamole/guacamole:latest
    env_file: .env
    ports:
      - "8080:8080"
    depends_on:
      postgres:
        condition: service_healthy
      guacd:
        condition: service_started
    restart: unless-stopped

volumes:
  pgdata:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/guacamole/" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Apache Guacamole aktif di port 8080 (/guacamole)."
echo "CRAVA_INSTALL_OK {{stack}}"
