#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/gatus"

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

mkdir -p "$STACK_DIR/gatus-config" "$STACK_DIR/hc-data"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
HC_SEC="$(openssl rand -hex 24)"

cat > "$STACK_DIR/.env" <<EOF
PUID=1000
PGID=1000
SITE_ROOT=http://${PUBLIC_IP}:8000
SITE_NAME=CravaCloud Healthchecks
SECRET_KEY=${HC_SEC}
REGISTRATION_OPEN=True
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/gatus-config/config.yaml" <<'GATUS'
endpoints:
  - name: Local-Health
    url: "http://127.0.0.1:8080/health"
    interval: 60s
    conditions:
      - "[STATUS] == 200"
GATUS

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  gatus:
    image: twinproduction/gatus:latest
    ports:
      - "8080:8080"
    volumes:
      - ./gatus-config:/config
    restart: unless-stopped

  healthchecks:
    image: lscr.io/linuxserver/healthchecks:latest
    env_file: .env
    ports:
      - "8000:8000"
    volumes:
      - ./hc-data:/config
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_ufw_allow 8000/tcp
crava_wait_http "http://127.0.0.1:8080/" 90
crava_wait_port 8000 90 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Gatus (:8080) & Healthchecks (:8000) aktif."
echo "CRAVA_INSTALL_OK {{stack}}"
