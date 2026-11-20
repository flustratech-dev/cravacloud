#!/usr/bin/env bash
# CravaCloud stack installer: odoo (idempotent, _common.sh hardened).
# Official Odoo 18.0 + PostgreSQL 16 compose stack on port 8069 (D6 zero-echo secrets).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-odoo/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':8069([[:space:]]|$)'; then
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

echo "== [3/4] Odoo 18 Community + PostgreSQL 16 =="
APP_DIR=/opt/crava-odoo
mkdir -p "$APP_DIR/config" "$APP_DIR/addons"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    PG_PASS="$(openssl rand -hex 20)"
    ADMIN_PASS="$(openssl rand -hex 16)"
    cat > "$APP_DIR/.env" <<ENV
POSTGRES_USER=odoo
POSTGRES_PASSWORD=${PG_PASS}
ODOO_MASTER_PASSWORD=${ADMIN_PASS}
ENV
    chmod 600 "$APP_DIR/.env"
    cat > "$APP_DIR/config/odoo.conf" <<CONF
[options]
addons_path = /mnt/extra-addons
admin_passwd = ${ADMIN_PASS}
db_host = db
db_port = 5432
db_user = odoo
db_password = ${PG_PASS}
CONF
    chmod 600 "$APP_DIR/config/odoo.conf"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  db:
    image: postgres:16
    restart: unless-stopped
    environment:
      POSTGRES_DB: postgres
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - odoo_db:/var/lib/postgresql/data
  odoo:
    image: odoo:18.0
    container_name: crava-odoo
    restart: unless-stopped
    depends_on: [db]
    ports: ["8069:8069"]
    environment:
      HOST: db
      USER: ${POSTGRES_USER}
      PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - odoo_web:/var/lib/odoo
      - ./config:/etc/odoo
      - ./addons:/mnt/extra-addons
volumes:
  odoo_db:
  odoo_web:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 8069 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 8069/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Master password & kredensial Odoo disimpan di /opt/crava-odoo/.env (root-only)."
