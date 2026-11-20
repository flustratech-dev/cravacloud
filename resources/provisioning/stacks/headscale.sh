#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/headscale"

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

mkdir -p "$STACK_DIR/config" "$STACK_DIR/data" "$STACK_DIR/headplane"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
COOKIE_SEC="$(openssl rand -hex 16)"

cat > "$STACK_DIR/config/config.yaml" <<YAML
server_url: http://${PUBLIC_IP}:8080
listen_addr: 0.0.0.0:8080
metrics_listen_addr: 127.0.0.1:9090
grpc_listen_addr: 127.0.0.1:50443
grpc_allow_insecure: false
noise:
  private_key_path: /var/lib/headscale/noise_private.key
prefixes:
  v4: 100.64.0.0/10
  v6: fd7a:115c:a1e0::/48
derp:
  server:
    enabled: true
    region_id: 999
    region_code: "crava"
    region_name: "CravaCloud Embedded DERP"
    stun_listen_addr: 0.0.0.0:3478
    private_key_path: /var/lib/headscale/derp_server_private.key
    ipv4: ${PUBLIC_IP}
  urls: []
  paths: []
  auto_update_enabled: false
database:
  type: sqlite
  sqlite:
    path: /var/lib/headscale/db.sqlite
unix_socket: /var/run/headscale/headscale.sock
dns:
  magic_dns: true
  base_domain: tailnet.cravacloud.local
  nameservers:
    global:
      - 1.1.1.1
      - 8.8.8.8
YAML

cat > "$STACK_DIR/headplane/config.yaml" <<YAML
server:
  host: "0.0.0.0"
  port: 3000
  cookie_secret: "${COOKIE_SEC}"
  cookie_secure: false
headscale:
  url: "http://headscale:8080"
  config_path: "/etc/headscale/config.yaml"
  config_strict: false
integration:
  docker:
    enabled: true
    container_name: "headscale"
YAML

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  headscale:
    image: headscale/headscale:latest
    container_name: headscale
    command: ["serve"]
    ports:
      - "8080:8080/tcp"
      - "3478:3478/udp"
    volumes:
      - ./config:/etc/headscale
      - ./data:/var/lib/headscale
      - headscale-run:/var/run/headscale
    restart: unless-stopped

  headplane:
    image: ghcr.io/tale/headplane:latest
    container_name: headplane
    ports:
      - "3000:3000"
    volumes:
      - ./headplane/config.yaml:/etc/headplane/config.yaml:ro
      - ./config:/etc/headscale
      - ./data:/var/lib/headscale
      - /var/run/docker.sock:/var/run/docker.sock:ro
    depends_on:
      - headscale
    restart: unless-stopped

volumes:
  headscale-run:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_ufw_allow 3000/tcp
crava_ufw_allow 3478/udp

crava_wait_port 8080 120 tcp
crava_wait_port 3000 120 tcp
crava_wait_port 3478 60 udp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Headscale (:8080), DERP (:3478/udp), & Headplane UI (:3000) aktif."
echo "CRAVA_INSTALL_OK {{stack}}"
