#!/usr/bin/env bash
# CravaCloud stack installer: mysql_phpmyadmin (idempotent, _common.sh hardened).
# Official MariaDB 11 (MySQL compatible, port 3306) + phpMyAdmin (port 8080). D6 zero-echo.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-mysql/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':3306([[:space:]]|$)' \
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

echo "== [3/4] MariaDB 11 + phpMyAdmin =="
APP_DIR=/opt/crava-mysql
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'MYSQL_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 20)"
        printf 'MYSQL_DATABASE=cravadb\n'
        printf 'MYSQL_USER=crava_admin\n'
        printf 'MYSQL_PASSWORD=%s\n' "$(openssl rand -hex 20)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  db:
    image: mariadb:11
    container_name: crava-mariadb
    restart: unless-stopped
    ports: ["3306:3306"]
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: ${MYSQL_DATABASE}
      MYSQL_USER: ${MYSQL_USER}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
  phpmyadmin:
    image: phpmyadmin:latest
    container_name: crava-phpmyadmin
    restart: unless-stopped
    depends_on: [db]
    ports: ["8080:80"]
    environment:
      PMA_HOST: db
      PMA_PORT: 3306
volumes:
  mysql_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 3306 120
if ! crava_wait_port 8080 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 3306/tcp 8080/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial database MariaDB/MySQL disimpan di /opt/crava-mysql/.env (root-only)."
