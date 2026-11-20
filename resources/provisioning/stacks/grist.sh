#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/grist"

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

mkdir -p "$STACK_DIR/persist"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
SEC_KEY="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
APP_HOME_URL=http://${PUBLIC_IP}:8484
GRIST_SESSION_SECRET=${SEC_KEY}
GRIST_SANDBOX_FLAVOR=gvisor
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  grist:
    image: gristlabs/grist:latest
    env_file: .env
    ports:
      - "8484:8484"
    volumes:
      - ./persist:/persist
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8484/tcp
crava_wait_http "http://127.0.0.1:8484/" 120

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Grist aktif di http://${PUBLIC_IP}:8484."
echo "CRAVA_INSTALL_OK {{stack}}"
