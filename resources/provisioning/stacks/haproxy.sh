#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/haproxy"

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

mkdir -p "$STACK_DIR/certs"
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /tmp/haproxy.key -out /tmp/haproxy.crt \
    -subj "/CN=cravacloud.local" >/dev/null 2>&1
cat /tmp/haproxy.crt /tmp/haproxy.key > "$STACK_DIR/certs/default.pem"
chmod 600 "$STACK_DIR/certs/default.pem"
rm -f /tmp/haproxy.key /tmp/haproxy.crt

cat > "$STACK_DIR/haproxy.cfg" <<'CFG'
global
    maxconn 4096

defaults
    mode http
    timeout connect 5s
    timeout client  50s
    timeout server  50s

frontend http_in
    bind *:80
    bind *:443 ssl crt /usr/local/etc/haproxy/certs/default.pem
    http-request return status 200 content-type text/plain string "HAProxy Load Balancer Active\n"

frontend stats
    bind *:8404
    stats enable
    stats uri /stats
    stats refresh 10s
CFG

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  haproxy:
    image: haproxy:lts-alpine
    ports:
      - "80:80"
      - "443:443"
      - "8404:8404"
    volumes:
      - ./haproxy.cfg:/usr/local/etc/haproxy/haproxy.cfg:ro
      - ./certs:/usr/local/etc/haproxy/certs:ro
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 8404/tcp
crava_wait_http "http://127.0.0.1:8404/stats" 60

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] HAProxy aktif (port 80/443 & stats 8404/stats)."
echo "CRAVA_INSTALL_OK {{stack}}"
