#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/hoppscotch"

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
JWT_SEC="$(openssl rand -hex 32)"
SESSION_SEC="$(openssl rand -hex 32)"
ENC_KEY="$(openssl rand -hex 16)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=hoppscotch
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=hoppscotch
DATABASE_URL=postgresql://hoppscotch:${PG_PWD}@db:5432/hoppscotch
JWT_SECRET=${JWT_SEC}
SESSION_SECRET=${SESSION_SEC}
DATA_ENCRYPTION_KEY=${ENC_KEY}
REDIRECT_URL=http://${PUBLIC_IP}:3000
WHITELISTED_ORIGINS=http://${PUBLIC_IP}:3000,http://${PUBLIC_IP}:3100,http://${PUBLIC_IP}:3170
VITE_BASE_URL=http://${PUBLIC_IP}:3000
VITE_SHORTCODE_BASE_URL=http://${PUBLIC_IP}:3000
VITE_ADMIN_URL=http://${PUBLIC_IP}:3100
VITE_BACKEND_GQL_URL=http://${PUBLIC_IP}:3170/graphql
VITE_BACKEND_WS_URL=ws://${PUBLIC_IP}:3170/graphql
VITE_BACKEND_API_URL=http://${PUBLIC_IP}:3170/v1
ENABLE_SUBPATH_BASED_ACCESS=false
ALLOW_SECURE_COOKIES=false
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  db:
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

  hoppscotch:
    image: hoppscotch/hoppscotch:latest
    env_file: .env
    ports:
      - "3000:3000"
      - "3100:3100"
      - "3170:3170"
    depends_on:
      db:
        condition: service_healthy
    restart: unless-stopped
volumes:
  pgdata:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" run --rm hoppscotch pnpm exec prisma migrate deploy || true
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_ufw_allow 3100/tcp
crava_ufw_allow 3170/tcp
crava_wait_port 3000 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Hoppscotch aktif di http://${PUBLIC_IP}:3000 (Admin :3100, API :3170)."
echo "CRAVA_INSTALL_OK {{stack}}"
