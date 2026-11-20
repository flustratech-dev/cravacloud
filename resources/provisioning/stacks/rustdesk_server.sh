#!/usr/bin/env bash
# CravaCloud stack installer: rustdesk_server (idempotent, _common.sh hardened).
# Official rustdesk/rustdesk-server-s6 (hbbs + hbbr) with encrypted key auto-generation.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-rustdesk/compose.yml ] \
    && docker inspect --format '{{.State.Running}}' crava-rustdesk 2>/dev/null | grep -qx true \
    && ss -tln 2>/dev/null | grep -qE ':21116([[:space:]]|$)'; then
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

echo "== [3/4] RustDesk Server (s6-overlay hbbs + hbbr) =="
APP_DIR=/opt/crava-rustdesk
mkdir -p "$APP_DIR/data"
PUB_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -n1 || echo 127.0.0.1)"

cat > "$APP_DIR/compose.yml" <<YAML
services:
  rustdesk-server:
    image: rustdesk/rustdesk-server-s6:latest
    container_name: crava-rustdesk
    restart: unless-stopped
    environment:
      RELAY: "${PUB_IP}:21117"
      ENCRYPTED_ONLY: "1"
    ports:
      - "21115:21115"
      - "21116:21116"
      - "21116:21116/udp"
      - "21117:21117"
      - "21118:21118"
      - "21119:21119"
    volumes:
      - ./data:/data
YAML
docker compose -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-rustdesk 120
crava_wait_port 21115 120 tcp
crava_wait_port 21116 120 tcp
crava_ufw_allow 22/tcp 21114/tcp 21115/tcp 21116/tcp 21117/tcp 21118/tcp 21119/tcp 21116/udp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Public key client RustDesk tersimpan di /opt/crava-rustdesk/data/id_ed25519.pub."
