#!/usr/bin/env bash
# CravaCloud stack installer: fivem_txadmin (idempotent, _common.sh hardened).
# Official Cfx.re FiveM Linux artifacts via spritsail/fivem in txAdmin web wizard mode (:40120).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-fivem/compose.yml ] \
    && docker inspect --format '{{.State.Running}}' crava-fivem 2>/dev/null | grep -qx true \
    && ss -tln 2>/dev/null | grep -qE ':40120([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] FiveM + txAdmin Web Wizard =="
APP_DIR=/opt/crava-fivem
mkdir -p "$APP_DIR/data" "$APP_DIR/txData"

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  fivem:
    image: spritsail/fivem:latest
    container_name: crava-fivem
    restart: unless-stopped
    stdin_open: true
    tty: true
    environment:
      NO_DEFAULT_CONFIG: "1"
    ports:
      - "30120:30120/tcp"
      - "30120:30120/udp"
      - "40120:40120/tcp"
    volumes:
      - ./data:/config
      - ./txData:/txData
YAML
docker compose -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-fivem 120
if ! crava_wait_port 40120 180 tcp; then
    docker compose -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 30120/tcp 40120/tcp 30120/udp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Buka txAdmin pada port 40120 dan ambil PIN 4-digit perdana lewat SSH: docker logs crava-fivem | tail -n 30"
