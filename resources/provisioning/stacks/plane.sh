#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/plane"

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
RMQ_PWD="$(openssl rand -hex 18)"
MINIO_KEY="planeadmin"
MINIO_SEC="$(openssl rand -hex 20)"
APP_SEC="$(openssl rand -hex 32)"
LIVE_SEC="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
APP_RELEASE=stable
LISTEN_HTTP_PORT=80
LISTEN_HTTPS_PORT=443
WEB_URL=http://${PUBLIC_IP}
CORS_ALLOWED_ORIGINS=http://${PUBLIC_IP}
APP_DOMAIN=${PUBLIC_IP}
SITE_ADDRESS=:80
TRUSTED_PROXIES=0.0.0.0/0
PGHOST=plane-db
PGDATABASE=plane
POSTGRES_USER=plane
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=plane
POSTGRES_PORT=5432
REDIS_HOST=plane-redis
REDIS_PORT=6379
REDIS_URL=redis://plane-redis:6379/
RABBITMQ_HOST=plane-mq
RABBITMQ_PORT=5672
RABBITMQ_USER=plane
RABBITMQ_PASSWORD=${RMQ_PWD}
RABBITMQ_VHOST=plane
AWS_REGION=us-east-1
AWS_ACCESS_KEY_ID=${MINIO_KEY}
AWS_SECRET_ACCESS_KEY=${MINIO_SEC}
AWS_S3_ENDPOINT_URL=http://plane-minio:9000
AWS_S3_BUCKET_NAME=uploads
SECRET_KEY=${APP_SEC}
LIVE_SERVER_SECRET_KEY=${LIVE_SEC}
FILE_SIZE_LIMIT=5242880
API_BASE_URL=http://api:8000
DEBUG=0
GUNICORN_WORKERS=1
EOF
chmod 600 "$STACK_DIR/.env"

# Unduh official Plane Community compose
curl -fsSL "https://raw.githubusercontent.com/makeplane/plane/master/deployments/cli/community/docker-compose.yml" \
    -o "$STACK_DIR/docker-compose.yml"

docker compose -f "$STACK_DIR/docker-compose.yml" --env-file "$STACK_DIR/.env" pull
docker compose -f "$STACK_DIR/docker-compose.yml" --env-file "$STACK_DIR/.env" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_port 80 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Plane Project Management aktif di http://${PUBLIC_IP}."
echo "CRAVA_INSTALL_OK {{stack}}"
