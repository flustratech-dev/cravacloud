#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/pihole"

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

# Bebaskan port 53 dari systemd-resolved agar container Pi-hole dapat bind TCP/UDP 53
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

mkdir -p "$STACK_DIR/etc-pihole" "$STACK_DIR/etc-dnsmasq.d"
umask 077
WEB_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
TZ=Asia/Jakarta
FTLCONF_webserver_api_password=${WEB_PWD}
WEBPASSWORD=${WEB_PWD}
FTLCONF_dns_listeningMode=all
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  pihole:
    image: pihole/pihole:latest
    container_name: pihole
    env_file: .env
    ports:
      - "53:53/tcp"
      - "53:53/udp"
      - "80:80/tcp"
    volumes:
      - ./etc-pihole:/etc/pihole
      - ./etc-dnsmasq.d:/etc/dnsmasq.d
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 53/tcp
crava_ufw_allow 53/udp
crava_ufw_allow 80/tcp

crava_wait_port 80 120 tcp
crava_wait_port 53 60 udp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Pi-hole aktif di http://<IP>/admin. Kredensial web tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
