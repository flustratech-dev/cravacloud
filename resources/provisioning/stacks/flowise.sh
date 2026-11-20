#!/usr/bin/env bash
# CravaCloud stack installer: flowise (idempotent, _common.sh hardened).
# Official flowiseai/flowise AI agent builder on port 3000 (D6 zero-echo password).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-flowise/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':3000([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg openssl ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Flowise AI Builder =="
APP_DIR=/opt/crava-flowise
mkdir -p "$APP_DIR/data"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'PORT=3000\n'
        printf 'FLOWISE_USERNAME=admin\n'
        printf 'FLOWISE_PASSWORD=%s\n' "$(openssl rand -hex 16)"
        printf 'FLOWISE_SECRETKEY_OVERWRITE=%s\n' "$(openssl rand -hex 24)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  flowise:
    image: flowiseai/flowise:latest
    container_name: crava-flowise
    restart: unless-stopped
    ports: ["3000:3000"]
    environment:
      PORT: 3000
      FLOWISE_USERNAME: ${FLOWISE_USERNAME}
      FLOWISE_PASSWORD: ${FLOWISE_PASSWORD}
      DATABASE_PATH: /root/.flowise
      SECRETKEY_PATH: /root/.flowise
      FLOWISE_SECRETKEY_OVERWRITE: ${FLOWISE_SECRETKEY_OVERWRITE}
      LOG_PATH: /root/.flowise/logs
    volumes:
      - ./data:/root/.flowise
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 3000 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 3000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial admin Flowise disimpan di /opt/crava-flowise/.env (root-only)."
