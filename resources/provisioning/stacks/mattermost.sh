#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/mattermost"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/volumes/app/mattermost/"{config,data,logs,plugins,client/plugins,bleve-indexes}
chown -R 2000:2000 "$STACK_DIR/volumes/app/mattermost"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=mmuser
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=mattermost
MM_SQLSETTINGS_DRIVERNAME=postgres
MM_SQLSETTINGS_DATASOURCE=postgres://mmuser:${PG_PWD}@postgres:5432/mattermost?sslmode=disable&connect_timeout=10
MM_SERVICESETTINGS_SITEURL=http://${PUBLIC_IP}:8065
MM_BLEVESETTINGS_INDEXDIR=/mattermost/bleve-indexes
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  postgres:
    image: postgres:16-alpine
    env_file: .env
    volumes:
      - db-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  mattermost:
    image: mattermost/mattermost-team-edition:latest
    env_file: .env
    ports:
      - "8065:8065"
    volumes:
      - ./volumes/app/mattermost/config:/mattermost/config
      - ./volumes/app/mattermost/data:/mattermost/data
      - ./volumes/app/mattermost/logs:/mattermost/logs
      - ./volumes/app/mattermost/plugins:/mattermost/plugins
      - ./volumes/app/mattermost/client/plugins:/mattermost/client/plugins
      - ./volumes/app/mattermost/bleve-indexes:/mattermost/bleve-indexes
    depends_on:
      postgres:
        condition: service_healthy
    restart: unless-stopped

volumes:
  db-data:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8065/tcp
crava_wait_port 8065 150 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Mattermost aktif di http://${PUBLIC_IP}:8065."
echo "CRAVA_INSTALL_OK {{stack}}"
