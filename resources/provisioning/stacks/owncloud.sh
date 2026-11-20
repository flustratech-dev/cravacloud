#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/owncloud-ocis"

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

mkdir -p "$STACK_DIR/config" "$STACK_DIR/data"
chown -R 1000:1000 "$STACK_DIR"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
OCIS_URL=https://${PUBLIC_IP}:9200
OCIS_INSECURE=true
PROXY_HTTP_ADDR=0.0.0.0:9200
IDM_ADMIN_PASSWORD=${ADMIN_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  ocis:
    image: owncloud/ocis:latest
    env_file: .env
    entrypoint: ["/bin/sh", "-c", "ocis init || true; exec ocis server"]
    ports:
      - "9200:9200"
    volumes:
      - ./config:/etc/ocis
      - ./data:/var/lib/ocis
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 9200/tcp
crava_wait_port 9200 120 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] ownCloud Infinite Scale aktif di https://${PUBLIC_IP}:9200. Password admin tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
