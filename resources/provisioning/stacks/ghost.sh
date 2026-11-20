#!/usr/bin/env bash
# CravaCloud stack installer: ghost (idempotent, _common.sh hardened).
# Ghost + MySQL via docker compose (D6 zero-echo secrets).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-ghost/compose.yml ] \
    && docker compose -f /opt/crava-ghost/compose.yml ps --status running 2>/dev/null | grep -q ghost \
    && ss -tln 2>/dev/null | grep -qE ':2368([[:space:]]|$)'; then
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

echo "== [3/4] Ghost + MySQL =="
APP_DIR=/opt/crava-ghost
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'MYSQL_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 24)"
        printf 'MYSQL_PASSWORD=%s\n' "$(openssl rand -hex 24)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
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

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 2368 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 2368/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial DB disimpan di /opt/crava-ghost/.env (root-only)."
