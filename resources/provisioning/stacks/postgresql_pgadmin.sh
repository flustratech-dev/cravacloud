#!/usr/bin/env bash
# CravaCloud stack installer: postgresql_pgadmin (idempotent, _common.sh hardened).
# Official PostgreSQL 16 (port 5432) + pgAdmin 4 Web UI (port 5050). D6 zero-echo.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-postgresql/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':5432([[:space:]]|$)' \
    && ss -tln 2>/dev/null | grep -qE ':5050([[:space:]]|$)'; then
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

echo "== [3/4] PostgreSQL 16 + pgAdmin 4 =="
APP_DIR=/opt/crava-postgresql
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'POSTGRES_USER=crava_admin\n'
        printf 'POSTGRES_DB=cravadb\n'
        printf 'POSTGRES_PASSWORD=%s\n' "$(openssl rand -hex 20)"
        printf 'PGADMIN_DEFAULT_EMAIL=admin@cravacloud.local\n'
        printf 'PGADMIN_DEFAULT_PASSWORD=%s\n' "$(openssl rand -hex 16)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  postgres:
    image: postgres:16
    container_name: crava-postgres
    restart: unless-stopped
    ports: ["5432:5432"]
    environment:
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_DB: ${POSTGRES_DB}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - pg_data:/var/lib/postgresql/data
  pgadmin:
    image: dpage/pgadmin4:latest
    container_name: crava-pgadmin
    restart: unless-stopped
    depends_on: [postgres]
    ports: ["5050:80"]
    environment:
      PGADMIN_DEFAULT_EMAIL: ${PGADMIN_DEFAULT_EMAIL}
      PGADMIN_DEFAULT_PASSWORD: ${PGADMIN_DEFAULT_PASSWORD}
    volumes:
      - pgadmin_data:/var/lib/pgadmin
volumes:
  pg_data:
  pgadmin_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 5432 120
if ! crava_wait_port 5050 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 5432/tcp 5050/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial PostgreSQL & pgAdmin 4 disimpan di /opt/crava-postgresql/.env (root-only)."
