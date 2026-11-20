#!/usr/bin/env bash
# CravaCloud stack installer: nocodb (idempotent, _common.sh hardened).
# Official NocoDB + PostgreSQL 16 compose stack on port 8080 (D6 zero-echo secrets).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-nocodb/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':8080([[:space:]]|$)'; then
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

echo "== [3/4] NocoDB + PostgreSQL =="
APP_DIR=/opt/crava-nocodb
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'POSTGRES_PASSWORD=%s\n' "$(openssl rand -hex 20)"
        printf 'NC_AUTH_JWT_SECRET=%s\n' "$(openssl rand -hex 32)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  db:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: nocodb
      POSTGRES_USER: nocodb
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - nocodb_pg:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U nocodb -d nocodb"]
      interval: 5s
      timeout: 5s
      retries: 10
  nocodb:
    image: nocodb/nocodb:latest
    container_name: crava-nocodb
    restart: unless-stopped
    depends_on:
      db:
        condition: service_healthy
    ports: ["8080:8080"]
    environment:
      NC_DB: pg://db:5432?u=nocodb&p=${POSTGRES_PASSWORD}&d=nocodb
      NC_AUTH_JWT_SECRET: ${NC_AUTH_JWT_SECRET}
    volumes:
      - nocodb_data:/usr/app/data
volumes:
  nocodb_pg:
  nocodb_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 8080 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 8080/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial database NocoDB disimpan di /opt/crava-nocodb/.env (root-only)."
