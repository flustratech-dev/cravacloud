#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/meilisearch"

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

mkdir -p "$STACK_DIR/meili_data"
umask 077

MASTER_KEY="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
MEILI_ENV=production
MEILI_MASTER_KEY=${MASTER_KEY}
MEILI_NO_ANALYTICS=true
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  meilisearch:
    image: getmeili/meilisearch:latest
    env_file: .env
    ports:
      - "7700:7700"
    volumes:
      - ./meili_data:/meili_data
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 7700/tcp
crava_wait_http "http://127.0.0.1:7700/health" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Meilisearch aktif di port 7700. Master key disimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
