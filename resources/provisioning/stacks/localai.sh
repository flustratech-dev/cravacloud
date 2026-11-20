#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/localai"

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

mkdir -p "$STACK_DIR/models"
umask 077
API_KEY="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
THREADS=2
CONTEXT_SIZE=4096
MODELS_PATH=/build/models
LOCALAI_API_KEY=${API_KEY}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  localai:
    image: localai/localai:latest-aio-cpu
    env_file: .env
    ports:
      - "8080:8080"
    volumes:
      - ./models:/build/models
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_port 8080 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] LocalAI OpenAI-compatible API aktif di port 8080. API key tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
