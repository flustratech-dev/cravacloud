#!/usr/bin/env bash
# CravaCloud stack installer: moodle (idempotent, _common.sh hardened).
# Lightweight Moodle LMS via erseco/alpine-moodle + PostgreSQL 16 (port 80). D6 zero-echo.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-moodle/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':80([[:space:]]|$)'; then
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

echo "== [3/4] Moodle LMS + PostgreSQL =="
APP_DIR=/opt/crava-moodle
mkdir -p "$APP_DIR"
PUB_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -n1 || echo 127.0.0.1)"

if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'SITE_URL=http://%s\n' "$PUB_IP"
        printf 'POSTGRES_PASSWORD=%s\n' "$(openssl rand -hex 20)"
        printf 'MOODLE_USERNAME=admin\n'
        printf 'MOODLE_PASSWORD=%sA!1\n' "$(openssl rand -hex 10)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: moodle
      POSTGRES_USER: moodle
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - moodle_pg:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U moodle -d moodle"]
      interval: 5s
      timeout: 5s
      retries: 10
  moodle:
    image: erseco/alpine-moodle:latest
    container_name: crava-moodle
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    ports: ["80:8080"]
    environment:
      SITE_URL: ${SITE_URL}
      DB_TYPE: pgsql
      DB_HOST: postgres
      DB_NAME: moodle
      DB_USER: moodle
      DB_PASS: ${POSTGRES_PASSWORD}
      MOODLE_USERNAME: ${MOODLE_USERNAME}
      MOODLE_PASSWORD: ${MOODLE_PASSWORD}
      MOODLE_EMAIL: admin@cravacloud.local
      MOODLE_SITENAME: CravaCloud LMS
    volumes:
      - moodle_data:/var/www/moodledata
      - moodle_html:/var/www/html
volumes:
  moodle_pg:
  moodle_data:
  moodle_html:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 80 240; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 80/tcp 443/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial admin Moodle disimpan di /opt/crava-moodle/.env (root-only)."
