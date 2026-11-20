#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/mastodon"

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

mkdir -p "$STACK_DIR/postgres14" "$STACK_DIR/redis" "$STACK_DIR/public/system" "$STACK_DIR/ssl"
chown -R 991:991 "$STACK_DIR/public/system"
umask 077

PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"
SEC_KEY="$(openssl rand -hex 64)"
OTP_SEC="$(openssl rand -hex 64)"
ACT_PRI="$(openssl rand -hex 16)"
ACT_DET="$(openssl rand -hex 16)"
ACT_SALT="$(openssl rand -hex 16)"

cat > "$STACK_DIR/.env.production" <<EOF
LOCAL_DOMAIN=${PUBLIC_IP}
SINGLE_USER_MODE=false
SECRET_KEY_BASE=${SEC_KEY}
OTP_SECRET=${OTP_SEC}
ACTIVE_RECORD_ENCRYPTION_DETERMINISTIC_KEY=${ACT_DET}
ACTIVE_RECORD_ENCRYPTION_KEY_DERIVATION_SALT=${ACT_SALT}
ACTIVE_RECORD_ENCRYPTION_PRIMARY_KEY=${ACT_PRI}
DB_HOST=db
DB_USER=postgres
DB_NAME=postgres
DB_PASS=${PG_PWD}
DB_PORT=5432
REDIS_HOST=redis
REDIS_PORT=6379
TRUSTED_PROXY_IP=0.0.0.0/0
EOF
chmod 600 "$STACK_DIR/.env.production"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=postgres
POSTGRES_PASSWORD=${PG_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$STACK_DIR/ssl/mastodon.key" \
    -out "$STACK_DIR/ssl/mastodon.crt" \
    -subj "/CN=cravacloud.local" >/dev/null 2>&1

curl -fsSL "https://raw.githubusercontent.com/tootsuite/mastodon/main/docker-compose.yml" \
    -o "$STACK_DIR/docker-compose.yml"

cat > "$STACK_DIR/nginx.conf" <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/mastodon.crt;
    ssl_certificate_key /etc/nginx/ssl/mastodon.key;
    server_name _;

    location /api/v1/streaming {
        proxy_pass http://streaming:4000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $http_host;
    }

    location / {
        proxy_pass http://web:3000;
        proxy_set_header Host $http_host;
        proxy_set_header X-Forwarded-Proto https;
    }
}
NGINX

cat > "$STACK_DIR/docker-compose.override.yml" <<'YAML'
services:
  db:
    env_file: .env
  web:
    env_file: .env.production
  streaming:
    env_file: .env.production
  sidekiq:
    env_file: .env.production
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - ./ssl:/etc/nginx/ssl:ro
    depends_on:
      - web
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" -f "$STACK_DIR/docker-compose.override.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" -f "$STACK_DIR/docker-compose.override.yml" up -d db redis
sleep 5
docker compose -f "$STACK_DIR/docker-compose.yml" -f "$STACK_DIR/docker-compose.override.yml" run --rm web bundle exec rails db:migrate || true
docker compose -f "$STACK_DIR/docker-compose.yml" -f "$STACK_DIR/docker-compose.override.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_port 80 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Mastodon Social Server aktif. Sesuaikan LOCAL_DOMAIN & SMTP di $STACK_DIR/.env.production."
echo "CRAVA_INSTALL_OK {{stack}}"
