#!/usr/bin/env bash
# CravaCloud stack installer: pterodactyl (idempotent, _common.sh hardened).
# Official Pterodactyl Panel compose (ghcr.io/pterodactyl/panel) + native Wings binary. D6 zero-echo.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-pterodactyl/compose.yml ] \
    && [ -x /usr/local/bin/wings ] \
    && ss -tln 2>/dev/null | grep -qE ':80([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/5] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg openssl ufw
crava_ensure_swap 2048

echo "== [2/5] Docker Engine =="
crava_ensure_docker

echo "== [3/5] Pterodactyl Panel (Official Image) =="
APP_DIR=/opt/crava-pterodactyl
mkdir -p "$APP_DIR/database" "$APP_DIR/var" "$APP_DIR/nginx" "$APP_DIR/logs"
PUB_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -n1 || echo 127.0.0.1)"

if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'APP_URL=http://%s\n' "$PUB_IP"
        printf 'APP_TIMEZONE=Asia/Jakarta\n'
        printf 'MYSQL_PASSWORD=%s\n' "$(openssl rand -hex 20)"
        printf 'MYSQL_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 24)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  database:
    image: mariadb:11.4
    restart: always
    environment:
      MYSQL_DATABASE: panel
      MYSQL_USER: pterodactyl
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    volumes:
      - ./database:/var/lib/mysql
  cache:
    image: redis:7.4-alpine
    restart: always
  panel:
    image: ghcr.io/pterodactyl/panel:latest
    container_name: crava-pterodactyl-panel
    restart: always
    depends_on: [database, cache]
    ports:
      - "80:80"
      - "443:443"
    environment:
      APP_URL: ${APP_URL}
      APP_TIMEZONE: ${APP_TIMEZONE}
      APP_SERVICE_AUTHOR: noreply@cravacloud.local
      DB_HOST: database
      DB_PORT: 3306
      DB_DATABASE: panel
      DB_USERNAME: pterodactyl
      DB_PASSWORD: ${MYSQL_PASSWORD}
      CACHE_DRIVER: redis
      SESSION_DRIVER: redis
      QUEUE_DRIVER: redis
      REDIS_HOST: cache
    volumes:
      - ./var:/app/var/
      - ./nginx:/etc/nginx/http.d/
      - ./logs:/app/storage/logs
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/5] Install Official Wings Binary & Systemd Unit =="
ARCH="amd64"
case "$(uname -m)" in
    aarch64|arm64) ARCH="arm64" ;;
esac
mkdir -p /etc/pterodactyl
if [ ! -x /usr/local/bin/wings ]; then
    curl -fsSL -o /usr/local/bin/wings "https://github.com/pterodactyl/wings/releases/latest/download/wings_linux_${ARCH}"
    chmod u+x /usr/local/bin/wings
fi

cat > /etc/systemd/system/wings.service <<'UNIT'
[Unit]
Description=Pterodactyl Wings Daemon
After=docker.service
Requires=docker.service
PartOf=docker.service

[Service]
User=root
WorkingDirectory=/etc/pterodactyl
LimitNOFILE=4096
PIDFile=/var/run/wings/daemon.pid
ExecStart=/usr/local/bin/wings
Restart=on-failure
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload

echo "== [5/5] Readiness Gate & Firewall =="
if ! crava_wait_port 80 240; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8080/tcp 2022/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial database Pterodactyl disimpan di /opt/crava-pterodactyl/.env (root-only). Buat admin via: docker compose -f /opt/crava-pterodactyl/compose.yml exec panel php artisan p:user:make"
