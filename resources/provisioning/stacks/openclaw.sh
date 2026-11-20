#!/usr/bin/env bash
# CravaCloud stack installer: openclaw (idempotent, _common.sh hardened).
# Official OpenClaw GHCR container (ghcr.io/openclaw/openclaw:latest).
# OPENCLAW_GATEWAY_TOKEN is generated on-server into root-only .env (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && docker inspect --format '{{.State.Running}}' crava-openclaw 2>/dev/null | grep -qx true; then
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

echo "== [3/4] OpenClaw (Official GHCR) =="
APP_DIR=/opt/crava-openclaw
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    printf 'OPENCLAW_GATEWAY_TOKEN=%s\n' "$(openssl rand -hex 24)" > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  openclaw-gateway:
    image: ghcr.io/openclaw/openclaw:latest
    container_name: crava-openclaw
    init: true
    restart: unless-stopped
    ports:
      - "8080:18789"
    environment:
      HOME: /home/node
      OPENCLAW_HOME: /home/node
      TERM: xterm-256color
      OPENCLAW_STATE_DIR: /home/node/.openclaw
      OPENCLAW_CONFIG_PATH: /home/node/.openclaw/openclaw.json
      OPENCLAW_CONFIG_DIR: /home/node/.openclaw
      OPENCLAW_WORKSPACE_DIR: /home/node/.openclaw/workspace
      OPENCLAW_GATEWAY_PORT: "18789"
      OPENCLAW_GATEWAY_TOKEN: ${OPENCLAW_GATEWAY_TOKEN}
      OPENCLAW_DISABLE_BONJOUR: "1"
    volumes:
      - openclaw_data:/home/node/.openclaw
    command: ["node", "dist/index.js", "gateway", "--bind", "lan"]
volumes:
  openclaw_data:
YAML

docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-openclaw 120
crava_wait_port 8080 120
crava_ufw_allow 22/tcp 8080/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Gateway token tersimpan di /opt/crava-openclaw/.env (root-only)."
