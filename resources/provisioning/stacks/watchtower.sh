#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/watchtower"

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

mkdir -p "$STACK_DIR"
umask 077
API_TOK="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
WATCHTOWER_HTTP_API_METRICS=true
WATCHTOWER_HTTP_API_UPDATE=true
WATCHTOWER_HTTP_API_TOKEN=${API_TOK}
WATCHTOWER_CLEANUP=true
WATCHTOWER_POLL_INTERVAL=21600
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  watchtower:
    image: containrrr/watchtower:latest
    env_file: .env
    ports:
      - "8080:8080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_port 8080 60 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Watchtower aktif (metrics API :8080/v1/metrics). Token API tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
