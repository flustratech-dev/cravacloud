#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/whisper-asr"

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

mkdir -p "$STACK_DIR/cache"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  whisper:
    image: fedirz/faster-whisper-server:latest-cpu
    ports:
      - "8000:8000"
    volumes:
      - ./cache:/root/.cache/huggingface
    environment:
      WHISPER__MODEL: Systran/faster-whisper-small
      WHISPER__INFERENCE_DEVICE: cpu
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8000/tcp
crava_wait_port 8000 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Faster-Whisper ASR Server aktif di port 8000."
echo "CRAVA_INSTALL_OK {{stack}}"
