#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/linkwarden"

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

mkdir -p "$STACK_DIR/data" "$STACK_DIR/pgdata" "$STACK_DIR/meili_data"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"
NA_SEC="$(openssl rand -hex 32)"
MEILI_KEY="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
NEXTAUTH_SECRET=${NA_SEC}
NEXTAUTH_URL=http://${PUBLIC_IP}:3000/api/v1/auth
POSTGRES_USER=postgres
POSTGRES_PASSWORD=${PG_PWD}
DATABASE_URL=postgresql://postgres:${PG_PWD}@postgres:5432/postgres
MEILI_MASTER_KEY=${MEILI_KEY}
EOF
chmod 600 "$STACK_DIR/.env"

curl -fsSL "https://raw.githubusercontent.com/linkwarden/linkwarden/main/docker-compose.yml" \
    -o "$STACK_DIR/docker-compose.yml"

docker compose -f "$STACK_DIR/docker-compose.yml" --env-file "$STACK_DIR/.env" pull
docker compose -f "$STACK_DIR/docker-compose.yml" --env-file "$STACK_DIR/.env" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Linkwarden Bookmarks aktif di http://${PUBLIC_IP}:3000."
echo "CRAVA_INSTALL_OK {{stack}}"
