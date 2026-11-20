#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/stirling-pdf"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/trainingData" "$STACK_DIR/extraConfigs" "$STACK_DIR/logs"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  stirling-pdf:
    image: stirlingtools/stirling-pdf:latest
    ports:
      - "8080:8080"
    volumes:
      - ./trainingData:/usr/share/tessdata
      - ./extraConfigs:/configs
      - ./logs:/logs
    environment:
      DOCKER_ENABLE_SECURITY: "false"
      SYSTEM_DEFAULTLOCALE: en-US
      LANGS: en_GB,id_ID
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/" 120

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Stirling PDF aktif di port 8080."
echo "CRAVA_INSTALL_OK {{stack}}"
