#!/usr/bin/env bash
# CravaCloud stack installer: hermes_agent (idempotent, _common.sh hardened).
# Official NousResearch Hermes Agent container (nousresearch/hermes-agent:latest).
# API_SERVER_KEY is generated on-server into root-only .env (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' crava-hermes 2>/dev/null | grep -qx true; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw openssl
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Hermes Agent (NousResearch Official) =="
APP_DIR=/opt/crava-hermes
mkdir -p "$APP_DIR/data"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    printf 'API_SERVER_KEY=%s\n' "$(openssl rand -hex 24)" > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  hermes:
    image: nousresearch/hermes-agent:latest
    container_name: crava-hermes
    restart: unless-stopped
    ports:
      - "8000:8642"
    environment:
      HERMES_UID: "1000"
      HERMES_GID: "1000"
      API_SERVER_ENABLED: "true"
      API_SERVER_HOST: "0.0.0.0"
      API_SERVER_PORT: "8642"
      API_SERVER_KEY: ${API_SERVER_KEY}
    volumes:
      - hermes_data:/opt/data
    command: ["gateway", "run"]
volumes:
  hermes_data:
YAML

docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-hermes 120
crava_wait_port 8000 120
crava_ufw_allow 22/tcp 8000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial API server tersimpan di /opt/crava-hermes/.env (root-only)."
