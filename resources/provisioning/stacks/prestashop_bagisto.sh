#!/usr/bin/env bash
# CravaCloud stack installer: prestashop_bagisto (idempotent, _common.sh hardened).
# Deploys PrestaShop + MySQL via docker compose (D6 zero-echo secrets).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-prestashop/compose.yml ] \
    && docker compose -f /opt/crava-prestashop/compose.yml ps --status running 2>/dev/null | grep -q prestashop \
    && ss -tln 2>/dev/null | grep -qE ':80([[:space:]]|$)'; then
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

echo "== [3/4] PrestaShop + MySQL =="
APP_DIR=/opt/crava-prestashop
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'MYSQL_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 24)"
        printf 'MYSQL_PASSWORD=%s\n' "$(openssl rand -hex 24)"
        printf 'ADMIN_PASSWORD=%s\n' "$(openssl rand -hex 16)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi
cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  db:
    image: mysql:8
    restart: unless-stopped
    command: --default-authentication-plugin=mysql_native_password
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: prestashop
      MYSQL_USER: prestashop
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
  prestashop:
    image: prestashop/prestashop:latest
    restart: unless-stopped
    depends_on: [db]
    ports: ["80:80"]
    environment:
      DB_SERVER: db
      DB_USER: prestashop
      DB_PASSWD: ${MYSQL_PASSWORD}
      DB_NAME: prestashop
      PS_INSTALL_AUTO: 1
      PS_DOMAIN: {{hostname}}
      PS_FOLDER_ADMIN: cravaadmin
      ADMIN_MAIL: admin@{{hostname}}
      ADMIN_PASSWD: ${ADMIN_PASSWORD}
    volumes:
      - ps_data:/var/www/html
volumes:
  db_data:
  ps_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 80 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 80/tcp 443/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial admin & DB disimpan di /opt/crava-prestashop/.env (root-only)."
