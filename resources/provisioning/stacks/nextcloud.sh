#!/usr/bin/env bash
# CravaCloud stack installer: nextcloud (idempotent, _common.sh hardened).
# Official Nextcloud Apache + MariaDB 11 + Redis stack on HTTP port 8080. D6 zero-echo.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-nextcloud/compose.yml ] \
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

echo "== [3/4] Nextcloud + MariaDB + Redis =="
APP_DIR=/opt/crava-nextcloud
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'MYSQL_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 20)"
        printf 'MYSQL_PASSWORD=%s\n' "$(openssl rand -hex 20)"
        printf 'NEXTCLOUD_ADMIN_USER=admin\n'
        printf 'NEXTCLOUD_ADMIN_PASSWORD=%s\n' "$(openssl rand -hex 16)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  db:
    image: mariadb:11
    restart: unless-stopped
    command: --transaction-isolation=READ-COMMITTED --log-bin=binlog --binlog-format=ROW
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: nextcloud
      MYSQL_USER: nextcloud
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - nc_db:/var/lib/mysql
  redis:
    image: redis:7-alpine
    restart: unless-stopped
  nextcloud:
    image: nextcloud:apache
    container_name: crava-nextcloud
    restart: unless-stopped
    depends_on: [db, redis]
    ports: ["8080:80"]
    environment:
      MYSQL_HOST: db
      MYSQL_DATABASE: nextcloud
      MYSQL_USER: nextcloud
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
      REDIS_HOST: redis
      NEXTCLOUD_ADMIN_USER: ${NEXTCLOUD_ADMIN_USER}
      NEXTCLOUD_ADMIN_PASSWORD: ${NEXTCLOUD_ADMIN_PASSWORD}
    volumes:
      - nc_html:/var/www/html
volumes:
  nc_db:
  nc_html:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 8080 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8080/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial Nextcloud & MariaDB disimpan di /opt/crava-nextcloud/.env (root-only)."
