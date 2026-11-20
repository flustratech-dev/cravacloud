#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/rust-server"

crava_require_os ubuntu

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/data"
chmod 777 "$STACK_DIR/data"
umask 077
RCON_PWD="$(openssl rand -hex 14)"

cat > "$STACK_DIR/.env" <<EOF
RUST_SERVER_NAME=CravaCloud Rust Server
RUST_SERVER_DESCRIPTION=Hosted on CravaCloud NVMe VPS
RUST_SERVER_WORLD_SIZE=3500
RUST_SERVER_MAXPLAYERS=50
RUST_RCON_PORT=28016
RUST_RCON_PASSWORD=${RCON_PWD}
RUST_SERVER_PORT=28015
RUST_APP_PORT=28017
RUST_UPDATE_CHECKING=1
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  rust:
    image: didstopia/rust-server:latest
    container_name: crava-rust
    env_file: .env
    ports:
      - "28016:28016/tcp"
      - "28015:28015/udp"
      - "28017:28017/udp"
    volumes:
      - ./data:/steamcmd/rust
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 28016/tcp
crava_ufw_allow 28015/udp
crava_ufw_allow 28017/udp
crava_wait_container crava-rust 180

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Rust Dedicated Server berjalan (UDP 28015/28017 & RCON TCP 28016). Password RCON tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
