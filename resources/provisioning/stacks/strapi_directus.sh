#!/usr/bin/env bash
# CravaCloud stack installer: strapi_directus (idempotent, _common.sh hardened).
# Deploys Directus (headless CMS) + PostgreSQL via docker compose (D6 zero-echo secrets).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-directus/compose.yml ] \
    && docker compose -f /opt/crava-directus/compose.yml ps --status running 2>/dev/null | grep -q directus \
    && ss -tln 2>/dev/null | grep -qE ':8055([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw openssl
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Directus + PostgreSQL =="
APP_DIR=/opt/crava-directus
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'DB_PASSWORD=%s\n' "$(openssl rand -hex 24)"
        printf 'APP_KEY=%s\n' "$(openssl rand -hex 32)"
        printf 'APP_SECRET=%s\n' "$(openssl rand -hex 32)"
        printf 'ADMIN_EMAIL=admin@{{hostname}}\n'
        printf 'ADMIN_PASSWORD=%s\n' "$(openssl rand -hex 16)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi
cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  db:
    image: postgres:16
    restart: unless-stopped
    environment:
      POSTGRES_USER: directus
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      POSTGRES_DB: directus
    volumes:
      - db_data:/var/lib/postgresql/data
  directus:
    image: directus/directus:latest
    restart: unless-stopped
    depends_on: [db]
    ports: ["8055:8055"]
    environment:
      KEY: ${APP_KEY}
      SECRET: ${APP_SECRET}
      DB_CLIENT: pg
      DB_HOST: db
      DB_PORT: 5432
      DB_DATABASE: directus
      DB_USER: directus
      DB_PASSWORD: ${DB_PASSWORD}
      ADMIN_EMAIL: ${ADMIN_EMAIL}
      ADMIN_PASSWORD: ${ADMIN_PASSWORD}
    volumes:
      - uploads:/directus/uploads
volumes:
  db_data:
  uploads:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 8055 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 8055/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial admin & DB disimpan di /opt/crava-directus/.env (root-only)."
