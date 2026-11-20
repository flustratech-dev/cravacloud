#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/openvpn"

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

modprobe tun || true
mkdir -p "$STACK_DIR/data"
umask 077

ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
OVPN_ADMIN_USER=openvpn
OVPN_ADMIN_PWD=${ADMIN_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  openvpn-as:
    image: openvpn/openvpn-as:latest
    container_name: openvpn-as
    cap_add:
      - NET_ADMIN
      - MKNOD
    devices:
      - /dev/net/tun:/dev/net/tun
    ports:
      - "943:943/tcp"
      - "443:443/tcp"
      - "1194:1194/udp"
    volumes:
      - ./data:/openvpn
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 943/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 1194/udp

crava_wait_port 943 150 tcp
sleep 5
if docker exec openvpn-as test -x /usr/local/openvpn_as/scripts/sacli; then
    docker exec -e PASS="$ADMIN_PWD" openvpn-as /bin/sh -c '/usr/local/openvpn_as/scripts/sacli --user openvpn --new_pass "$PASS" SetLocalPassword' >/dev/null 2>&1 || true
fi
crava_wait_port 1194 60 udp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] OpenVPN Access Server aktif (HTTPS Admin :943/admin & UDP :1194). Kredensial tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
