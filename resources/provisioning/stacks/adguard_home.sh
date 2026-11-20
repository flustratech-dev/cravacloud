#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/adguard-home"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates
crava_ensure_swap 2048
crava_ensure_docker

# Bebaskan port 53 dari systemd-resolved dengan aman agar AdGuard dapat bind port 53 TCP/UDP
if systemctl is-active --quiet systemd-resolved; then
    mkdir -p /etc/systemd/resolved.conf.d
    cat > /etc/systemd/resolved.conf.d/crava-dns.conf <<'CONF'
[Resolve]
DNS=1.1.1.1 8.8.8.8
DNSStubListener=no
CONF
    if [[ -L /etc/resolv.conf ]]; then
        rm -f /etc/resolv.conf
        printf "nameserver 1.1.1.1\nnameserver 8.8.8.8\n" > /etc/resolv.conf
    fi
    systemctl restart systemd-resolved
fi

mkdir -p "$STACK_DIR/work" "$STACK_DIR/conf"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  adguardhome:
    image: adguard/adguardhome:latest
    container_name: adguardhome
    ports:
      - "53:53/tcp"
      - "53:53/udp"
      - "80:80/tcp"
      - "443:443/tcp"
      - "3000:3000/tcp"
    volumes:
      - ./work:/opt/adguardhome/work
      - ./conf:/opt/adguardhome/conf
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 53/tcp
crava_ufw_allow 53/udp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 3000/tcp

crava_wait_port 3000 120 tcp
crava_wait_port 53 60 udp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] AdGuard Home aktif. Wizard setup awal tersedia di port 3000."
echo "CRAVA_INSTALL_OK {{stack}}"
