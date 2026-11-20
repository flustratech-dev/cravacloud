#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/langfuse"

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

mkdir -p "$STACK_DIR"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"
NA_SEC="$(openssl rand -hex 32)"
SALT_KEY="$(openssl rand -hex 24)"
ENC_KEY="$(openssl rand -hex 32)"
MINIO_PWD="$(openssl rand -hex 18)"
CH_PWD="$(openssl rand -hex 18)"
REDIS_PWD="$(openssl rand -hex 18)"

curl -fsSL "https://raw.githubusercontent.com/langfuse/langfuse/main/docker-compose.yml" \
    -o "$STACK_DIR/docker-compose.yml"

cat > "$STACK_DIR/.env" <<EOF
NEXTAUTH_URL=http://${PUBLIC_IP}:3000
NEXTAUTH_SECRET=${NA_SEC}
SALT=${SALT_KEY}
ENCRYPTION_KEY=${ENC_KEY}
POSTGRES_USER=postgres
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=postgres
CLICKHOUSE_PASSWORD=${CH_PWD}
MINIO_ROOT_PASSWORD=${MINIO_PWD}
REDIS_AUTH=${REDIS_PWD}
LANGFUSE_S3_EVENT_UPLOAD_ACCESS_KEY_ID=minio
LANGFUSE_S3_EVENT_UPLOAD_SECRET_ACCESS_KEY=${MINIO_PWD}
LANGFUSE_S3_MEDIA_UPLOAD_ACCESS_KEY_ID=minio
LANGFUSE_S3_MEDIA_UPLOAD_SECRET_ACCESS_KEY=${MINIO_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

docker compose -f "$STACK_DIR/docker-compose.yml" --env-file "$STACK_DIR/.env" pull
docker compose -f "$STACK_DIR/docker-compose.yml" --env-file "$STACK_DIR/.env" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 3000 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Langfuse LLM Observability aktif di http://${PUBLIC_IP}:3000."
echo "CRAVA_INSTALL_OK {{stack}}"
