#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/zulip"

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
REDIS_PWD="$(openssl rand -hex 18)"
ZULIP_SEC="$(openssl rand -hex 32)"

cat > "$STACK_DIR/.env" <<EOF
DB_HOST=database
DB_HOST_PORT=5432
DB_USER=zulip
SECRETS_postgres_password=${PG_PWD}
SECRETS_rabbitmq_password=${RMQ_PWD}
SECRETS_redis_password=${REDIS_PWD}
SECRETS_secret_key=${ZULIP_SEC}
SETTING_EXTERNAL_HOST=${PUBLIC_IP}
SETTING_ZULIP_ADMINISTRATOR=admin@cravacloud.local
ZULIP_AUTH_BACKENDS=EmailAuthBackend
DISABLE_HTTPS=True
SSL_CERTIFICATE_GENERATION=self-signed
EOF
chmod 600 "$STACK_DIR/.env"

curl -fsSL "https://raw.githubusercontent.com/zulip/docker-zulip/main/compose.yaml" \
    -o "$STACK_DIR/compose.yaml"

docker compose -f "$STACK_DIR/compose.yaml" --env-file "$STACK_DIR/.env" pull
docker compose -f "$STACK_DIR/compose.yaml" --env-file "$STACK_DIR/.env" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_port 80 300 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Zulip Threaded Chat aktif di http://${PUBLIC_IP}."
echo "CRAVA_INSTALL_OK {{stack}}"
