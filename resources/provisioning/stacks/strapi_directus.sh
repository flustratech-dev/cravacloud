#!/usr/bin/env bash
# CravaCloud stack installer: strapi_directus (idempotent — safe to re-run via TAT retry).
# Deploys Directus (headless CMS) + PostgreSQL via docker compose. All secrets
# (DB password, app KEY/SECRET, admin password) are generated on-server into a
# root-only .env and NEVER echoed to stdout / TAT TaskResult (D6).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/4] Base dependencies =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw openssl

echo "== [2/4] Docker =="
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

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

echo "== [4/4] Firewall + finalize =="
for p in 22 8055; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial admin & DB disimpan di /opt/crava-directus/.env (root-only)."