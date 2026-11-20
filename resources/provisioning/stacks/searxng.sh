#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/searxng"

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

mkdir -p "$STACK_DIR/etc" "$STACK_DIR/cache"
umask 077
SEC_KEY="$(openssl rand -hex 32)"

cat > "$STACK_DIR/etc/settings.yml" <<YAML
use_default_settings: true
server:
  secret_key: "${SEC_KEY}"
  limiter: false
search:
  formats:
    - html
    - json
YAML

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  searxng:
    image: searxng/searxng:latest
    ports:
      - "8080:8080"
    volumes:
      - ./etc:/etc/searxng
      - ./cache:/var/cache/searxng
    environment:
      SEARXNG_BASE_URL: "http://127.0.0.1:8080/"
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/" 90

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] SearXNG + JSON Search API aktif di port 8080."
echo "CRAVA_INSTALL_OK {{stack}}"
