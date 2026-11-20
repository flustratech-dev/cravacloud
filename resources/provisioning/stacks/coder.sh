#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/coder"

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

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=coder
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=coder
CODER_ACCESS_URL=http://${PUBLIC_IP}:7080
CODER_HTTP_ADDRESS=0.0.0.0:7080
CODER_PG_CONNECTION_URL=postgresql://coder:${PG_PWD}@database:5432/coder?sslmode=disable
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  database:
    image: postgres:16-alpine
    env_file: .env
    volumes:
      - coder-db:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  coder:
    image: ghcr.io/coder/coder:latest
    env_file: .env
    ports:
      - "7080:7080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    depends_on:
      database:
        condition: service_healthy
    restart: unless-stopped
volumes:
  coder-db:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 7080/tcp
crava_wait_http "http://127.0.0.1:7080/api/v2/buildinfo" 150

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Coder Cloud Dev aktif di http://${PUBLIC_IP}:7080."
echo "CRAVA_INSTALL_OK {{stack}}"
