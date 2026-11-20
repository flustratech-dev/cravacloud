#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/libretranslate"

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

mkdir -p "$STACK_DIR/db" "$STACK_DIR/models"
umask 077
API_KEY="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
LT_LOAD_ONLY=en,id
LT_UPDATE_MODELS=false
LT_API_KEYS=true
LT_DEFAULT_KEY=${API_KEY}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  libretranslate:
    image: libretranslate/libretranslate:latest
    env_file: .env
    ports:
      - "5000:5000"
    volumes:
      - ./db:/app/db
      - ./models:/home/libretranslate/.local
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 5000/tcp
crava_wait_port 5000 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] LibreTranslate aktif di port 5000 (en<->id). API key tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
