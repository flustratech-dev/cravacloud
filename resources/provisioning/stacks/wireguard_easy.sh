#!/usr/bin/env bash
# CravaCloud stack installer: wireguard_easy (idempotent, _common.sh hardened).
# Official ghcr.io/wg-easy/wg-easy:15 (51820/udp tunnel + 51821/tcp Web Admin UI).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-wireguard/compose.yml ] \
    && docker inspect --format '{{.State.Running}}' crava-wg-easy 2>/dev/null | grep -qx true \
    && ss -tln 2>/dev/null | grep -qE ':51821([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies, Kernel IP Forwarding & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw
crava_ensure_swap 2048

cat > /etc/sysctl.d/99-crava-wireguard.conf <<'SYSCTL'
net.ipv4.ip_forward = 1
net.ipv4.conf.all.src_valid_mark = 1
SYSCTL
sysctl --system >/dev/null 2>&1 || true

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] WireGuard (wg-easy v15) =="
APP_DIR=/opt/crava-wireguard
mkdir -p "$APP_DIR"
PUB_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -n1 || echo 127.0.0.1)"

cat > "$APP_DIR/.env" <<ENV
WG_HOST=${PUB_IP}
ENV
chmod 600 "$APP_DIR/.env"

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  wg-easy:
    image: ghcr.io/wg-easy/wg-easy:15
    container_name: crava-wg-easy
    restart: unless-stopped
    environment:
      HOST: 0.0.0.0
      PORT: 51821
      WG_HOST: ${WG_HOST}
      WG_PORT: 51820
    volumes:
      - wg_data:/etc/wireguard
    ports:
      - "51820:51820/udp"
      - "51821:51821/tcp"
    cap_add:
      - NET_ADMIN
      - SYS_MODULE
    sysctls:
      - net.ipv4.ip_forward=1
      - net.ipv4.conf.all.src_valid_mark=1
volumes:
  wg_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_container crava-wg-easy 120
if ! crava_wait_port 51821 120 tcp; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 51821/tcp 51820/udp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
