#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/homarr"

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

mkdir -p "$STACK_DIR/appdata" "$STACK_DIR/homepage"
umask 077
SEC_ENC="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
SECRET_ENCRYPTION_KEY=${SEC_ENC}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  homarr:
    image: ghcr.io/homarr-labs/homarr:latest
    env_file: .env
    ports:
      - "7575:7575"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./appdata:/appdata
    restart: unless-stopped

  homepage:
    image: ghcr.io/gethomepage/homepage:latest
    ports:
      - "127.0.0.1:3005:3000"
    volumes:
      - ./homepage:/app/config
      - /var/run/docker.sock:/var/run/docker.sock:ro
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 7575/tcp
crava_wait_http "http://127.0.0.1:7575/" 120

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Homarr Dashboard (:7575) + Homepage (:127.0.0.1:3005) aktif."
echo "CRAVA_INSTALL_OK {{stack}}"
