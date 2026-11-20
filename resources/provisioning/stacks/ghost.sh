#!/usr/bin/env bash
# CravaCloud stack installer: ghost (idempotent — safe to re-run via TAT retry).
# Ghost + MySQL via docker compose. DB passwords generated on-server into a
# root-only .env, NEVER echoed to stdout / TAT TaskResult (D6). The Ghost owner
# account is created on first-run web setup (/ghost).
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

echo "== [3/4] Ghost + MySQL =="
APP_DIR=/opt/crava-ghost
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'MYSQL_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 24)"
        printf 'MYSQL_PASSWORD=%s\n' "$(openssl rand -hex 24)"
    } > "$APP_DIR/.env"
fi
cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  db:
    image: mysql:8
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: ghost
      MYSQL_USER: ghost
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
  ghost:
    image: ghost:5
    restart: unless-stopped
    depends_on: [db]
    ports: ["2368:2368"]
    environment:
      url: http://{{hostname}}:2368
      database__client: mysql
      database__connection__host: db
      database__connection__user: ghost
      database__connection__password: ${MYSQL_PASSWORD}
      database__connection__database: ghost
    volumes:
      - ghost_data:/var/lib/ghost/content
volumes:
  db_data:
  ghost_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Firewall + finalize =="
for p in 22 2368; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial DB disimpan di /opt/crava-ghost/.env (root-only)."