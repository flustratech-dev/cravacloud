#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/qdrant"

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

mkdir -p "$STACK_DIR/storage"
umask 077

API_KEY="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
QDRANT__SERVICE__API_KEY=${API_KEY}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  qdrant:
    image: qdrant/qdrant:latest
    env_file: .env
    ports:
      - "6333:6333"
      - "6334:6334"
    volumes:
      - ./storage:/qdrant/storage
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 6333/tcp
crava_ufw_allow 6334/tcp
crava_wait_http "http://127.0.0.1:6333/healthz" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Qdrant Vector DB aktif di port 6333 (/dashboard) & gRPC 6334. API key tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
