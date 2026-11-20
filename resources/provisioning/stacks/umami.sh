#!/usr/bin/env bash
# CravaCloud stack installer: umami (idempotent, _common.sh hardened).
# Official Umami Analytics + PostgreSQL 16 compose stack (D6 zero-echo secrets).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-umami/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':3000([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg openssl ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Umami Analytics + PostgreSQL =="
APP_DIR=/opt/crava-umami
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'POSTGRES_PASSWORD=%s\n' "$(openssl rand -hex 20)"
        printf 'APP_SECRET=%s\n' "$(openssl rand -hex 32)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  db:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: umami
      POSTGRES_USER: umami
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - umami_db:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U umami -d umami"]
      interval: 5s
      timeout: 5s
      retries: 10
  umami:
    image: ghcr.io/umami-software/umami:latest
    container_name: crava-umami
    restart: unless-stopped
    depends_on:
      db:
        condition: service_healthy
    ports: ["3000:3000"]
    environment:
      DATABASE_URL: postgresql://umami:${POSTGRES_PASSWORD}@db:5432/umami
      APP_SECRET: ${APP_SECRET}
volumes:
  umami_db:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 3000 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 3000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial database & APP_SECRET Umami disimpan di /opt/crava-umami/.env (root-only)."
