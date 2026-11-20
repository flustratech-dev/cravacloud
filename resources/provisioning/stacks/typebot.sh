#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/typebot"

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
ENC_SEC="$(openssl rand -base64 24 | tr -d '\n' | cut -c1-32)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_DB=typebot
POSTGRES_USER=postgres
POSTGRES_PASSWORD=${PG_PWD}
DATABASE_URL=postgresql://postgres:${PG_PWD}@typebot-db:5432/typebot
ENCRYPTION_SECRET=${ENC_SEC}
NEXTAUTH_URL=http://${PUBLIC_IP}:8081
NEXT_PUBLIC_VIEWER_URL=http://${PUBLIC_IP}:8082
REDIS_URL=redis://typebot-redis:6379
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  typebot-db:
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

  typebot-redis:
    image: redis:7-alpine
    restart: unless-stopped

  typebot-builder:
    image: baptistearno/typebot-builder:latest
    env_file: .env
    ports:
      - "8081:3000"
    depends_on:
      typebot-db:
        condition: service_healthy
    restart: unless-stopped

  typebot-viewer:
    image: baptistearno/typebot-viewer:latest
    env_file: .env
    ports:
      - "8082:3000"
    depends_on:
      typebot-db:
        condition: service_healthy
    restart: unless-stopped

volumes:
  db-data:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8081/tcp
crava_ufw_allow 8082/tcp
crava_wait_port 8081 180 tcp
crava_wait_port 8082 120 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Typebot Builder (:8081) & Viewer (:8082) aktif."
echo "CRAVA_INSTALL_OK {{stack}}"
