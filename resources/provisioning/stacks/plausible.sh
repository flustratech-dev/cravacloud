#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/plausible"

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

mkdir -p "$STACK_DIR/clickhouse"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
SEC_KEY="$(openssl rand -base64 48 | tr -d '\n')"
PG_PWD="$(openssl rand -hex 18)"

curl -fsSL "https://raw.githubusercontent.com/plausible/community-edition/master/compose.yml" \
    -o "$STACK_DIR/compose.yml"
curl -fsSL "https://raw.githubusercontent.com/plausible/community-edition/master/clickhouse/logs.xml" \
    -o "$STACK_DIR/clickhouse/logs.xml"
curl -fsSL "https://raw.githubusercontent.com/plausible/community-edition/master/clickhouse/ipv4-only.xml" \
    -o "$STACK_DIR/clickhouse/ipv4-only.xml"

cat > "$STACK_DIR/.env" <<EOF
BASE_URL=http://${PUBLIC_IP}:8000
SECRET_KEY_BASE=${SEC_KEY}
HTTP_PORT=8000
POSTGRES_PASSWORD=${PG_PWD}
DATABASE_URL=postgres://postgres:${PG_PWD}@plausible_db:5432/plausible_db
EOF
chmod 600 "$STACK_DIR/.env"

sed -i 's/POSTGRES_PASSWORD=postgres/POSTGRES_PASSWORD=${POSTGRES_PASSWORD}/g' "$STACK_DIR/compose.yml"

docker compose -f "$STACK_DIR/compose.yml" --env-file "$STACK_DIR/.env" pull
docker compose -f "$STACK_DIR/compose.yml" --env-file "$STACK_DIR/.env" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8000/tcp
crava_wait_port 8000 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Plausible Analytics CE aktif di http://${PUBLIC_IP}:8000."
echo "CRAVA_INSTALL_OK {{stack}}"
