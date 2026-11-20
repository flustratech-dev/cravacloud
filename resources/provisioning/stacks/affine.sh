#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/affine"

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

mkdir -p "$STACK_DIR/storage" "$STACK_DIR/config" "$STACK_DIR/postgres"
umask 077
PG_PWD="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
AFFINE_REVISION=stable
PORT=3010
DB_USERNAME=affine
DB_PASSWORD=${PG_PWD}
DB_DATABASE=affine
UPLOAD_LOCATION=${STACK_DIR}/storage
CONFIG_LOCATION=${STACK_DIR}/config
DB_DATA_LOCATION=${STACK_DIR}/postgres
EOF
chmod 600 "$STACK_DIR/.env"

curl -fsSL "https://raw.githubusercontent.com/toeverything/AFFiNE/canary/.docker/selfhost/compose.yml" \
    -o "$STACK_DIR/compose.yml"

docker compose -f "$STACK_DIR/compose.yml" --env-file "$STACK_DIR/.env" pull
docker compose -f "$STACK_DIR/compose.yml" --env-file "$STACK_DIR/.env" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3010/tcp
crava_wait_port 3010 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] AFFiNE Workspace aktif di port 3010."
echo "CRAVA_INSTALL_OK {{stack}}"
