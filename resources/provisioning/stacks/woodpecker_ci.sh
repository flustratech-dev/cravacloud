#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/woodpecker"

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

mkdir -p "$STACK_DIR/data"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
AGENT_SEC="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
WOODPECKER_OPEN=true
WOODPECKER_HOST=http://${PUBLIC_IP}:8000
WOODPECKER_AGENT_SECRET=${AGENT_SEC}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  woodpecker-server:
    image: woodpeckerci/woodpecker-server:latest
    env_file: .env
    ports:
      - "8000:8000"
    volumes:
      - ./data:/var/lib/woodpecker
    restart: unless-stopped

  woodpecker-agent:
    image: woodpeckerci/woodpecker-agent:latest
    env_file: .env
    environment:
      WOODPECKER_SERVER: woodpecker-server:9000
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    depends_on:
      - woodpecker-server
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8000/tcp
crava_wait_port 8000 120 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Woodpecker CI aktif di http://${PUBLIC_IP}:8000."
echo "CRAVA_INSTALL_OK {{stack}}"
