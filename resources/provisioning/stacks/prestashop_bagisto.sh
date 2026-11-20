#!/usr/bin/env bash
# CravaCloud stack installer: prestashop_bagisto (idempotent — safe to re-run via TAT retry).
# Deploys PrestaShop + MySQL via docker compose. DB and admin passwords are
# generated on-server into a root-only .env, NEVER echoed to stdout / TAT
# TaskResult (D6).
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

echo "== [4/4] Firewall + finalize =="
for p in 22 80 443; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial admin & DB disimpan di /opt/crava-prestashop/.env (root-only)."