#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/posthog"

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

mkdir -p "$STACK_DIR/compose"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
SEC_KEY="$(openssl rand -hex 28)"

cat > "$STACK_DIR/.env" <<EOF
POSTHOG_SECRET=${SEC_KEY}
SITE_URL=http://${PUBLIC_IP}
DOMAIN=${PUBLIC_IP}
TLS_BLOCK=
EOF
chmod 600 "$STACK_DIR/.env"

# Ambil official PostHog hobby docker-compose
curl -fsSL "https://raw.githubusercontent.com/PostHog/posthog/master/docker-compose.hobby.yml" \
    -o "$STACK_DIR/docker-compose.yml"

docker compose -f "$STACK_DIR/docker-compose.yml" --env-file "$STACK_DIR/.env" pull
docker compose -f "$STACK_DIR/docker-compose.yml" --env-file "$STACK_DIR/.env" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_port 80 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] PostHog Product Analytics aktif di http://${PUBLIC_IP}."
echo "CRAVA_INSTALL_OK {{stack}}"
