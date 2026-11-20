#!/usr/bin/env bash
# CravaCloud stack installer: open_webui (idempotent, _common.sh hardened).
# Official Open WebUI + Ollama separate compose containers (ports 3000 & 11434). D6 zero-echo.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-open-webui/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':3000([[:space:]]|$)' \
    && ss -tln 2>/dev/null | grep -qE ':11434([[:space:]]|$)'; then
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

echo "== [3/4] Open WebUI + Ollama =="
APP_DIR=/opt/crava-open-webui
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    printf 'WEBUI_SECRET_KEY=%s\n' "$(openssl rand -hex 32)" > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  ollama:
    image: ollama/ollama:latest
    container_name: crava-openwebui-ollama
    restart: unless-stopped
    ports: ["11434:11434"]
    environment:
      OLLAMA_HOST: 0.0.0.0
    volumes:
      - ollama_data:/root/.ollama
  open-webui:
    image: ghcr.io/open-webui/open-webui:main
    container_name: crava-open-webui
    restart: unless-stopped
    depends_on: [ollama]
    ports: ["3000:8080"]
    environment:
      OLLAMA_BASE_URL: http://ollama:11434
      WEBUI_SECRET_KEY: ${WEBUI_SECRET_KEY}
    volumes:
      - webui_data:/app/backend/data
volumes:
  ollama_data:
  webui_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 11434 120
if ! crava_wait_port 3000 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 3000/tcp 11434/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Secret Open WebUI disimpan di /opt/crava-open-webui/.env (root-only)."
