#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/paperless-ngx"

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

mkdir -p "$STACK_DIR/export" "$STACK_DIR/consume"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 20)"
PAPERLESS_SEC="$(openssl rand -hex 32)"
ADMIN_PWD="$(openssl rand -hex 12)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_DB=paperless
POSTGRES_USER=paperless
POSTGRES_PASSWORD=${PG_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.env" <<EOF
PAPERLESS_SECRET_KEY=${PAPERLESS_SEC}
PAPERLESS_ADMIN_USER=admin
PAPERLESS_ADMIN_PASSWORD=${ADMIN_PWD}
PAPERLESS_URL=http://${PUBLIC_IP}:8000
PAPERLESS_OCR_LANGUAGE=eng+ind
PAPERLESS_DBPASS=${PG_PWD}
EOF
chmod 600 "$STACK_DIR/docker-compose.env"

curl -fsSL "https://raw.githubusercontent.com/paperless-ngx/paperless-ngx/main/docker/compose/docker-compose.postgres-tika.yml" \
    -o "$STACK_DIR/docker-compose.yml"
sed -i 's/POSTGRES_PASSWORD: paperless/POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}/g' "$STACK_DIR/docker-compose.yml"
sed -i 's/PAPERLESS_DBPASS: paperless/PAPERLESS_DBPASS: ${POSTGRES_PASSWORD}/g' "$STACK_DIR/docker-compose.yml"

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8000/tcp
crava_wait_http "http://127.0.0.1:8000/" 180

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Paperless-ngx aktif di http://${PUBLIC_IP}:8000. Login admin tersimpan di $STACK_DIR/docker-compose.env."
echo "CRAVA_INSTALL_OK {{stack}}"
