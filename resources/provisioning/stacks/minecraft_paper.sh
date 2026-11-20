#!/usr/bin/env bash
# CravaCloud stack installer: minecraft_paper (idempotent, _common.sh hardened).
# PaperMC dedicated Minecraft Java server (itzg/minecraft-server TYPE=PAPER). D6 zero-echo RCON.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-minecraft/compose.yml ] \
    && docker inspect --format '{{.State.Running}}' crava-minecraft 2>/dev/null | grep -qx true \
    && ss -tln 2>/dev/null | grep -qE ':25565([[:space:]]|$)'; then
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

echo "== [3/4] Minecraft PaperMC Dedicated Server =="
APP_DIR=/opt/crava-minecraft
mkdir -p "$APP_DIR/data"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    printf 'RCON_PASSWORD=%s\n' "$(openssl rand -hex 16)" > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  minecraft:
    image: itzg/minecraft-server:latest
    container_name: crava-minecraft
    restart: unless-stopped
    ports:
      - "25565:25565/tcp"
      - "25565:25565/udp"
      - "25575:25575/tcp"
    environment:
      EULA: "TRUE"
      TYPE: "PAPER"
      MEMORY: "2G"
      ENABLE_RCON: "true"
      RCON_PORT: "25575"
      RCON_PASSWORD: ${RCON_PASSWORD}
    volumes:
      - ./data:/data
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-minecraft 120
if ! crava_wait_port 25565 240 tcp; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 25565/tcp 25575/tcp 25565/udp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "RCON_PASSWORD PaperMC disimpan di /opt/crava-minecraft/.env (root-only)."
