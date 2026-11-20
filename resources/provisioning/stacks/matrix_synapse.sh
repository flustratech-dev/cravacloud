#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/matrix-synapse"

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

mkdir -p "$STACK_DIR/data" "$STACK_DIR/db" "$STACK_DIR/ssl"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
PG_PWD="$(openssl rand -hex 18)"

cat > "$STACK_DIR/.env" <<EOF
POSTGRES_USER=synapse
POSTGRES_PASSWORD=${PG_PWD}
POSTGRES_DB=synapse
POSTGRES_INITDB_ARGS=--encoding=UTF-8 --lc-collate=C --lc-ctype=C
EOF
chmod 600 "$STACK_DIR/.env"

if [[ ! -f "$STACK_DIR/data/homeserver.yaml" ]]; then
    docker run --rm -v "$STACK_DIR/data:/data" \
        -e SYNAPSE_SERVER_NAME="matrix.cravacloud.local" \
        -e SYNAPSE_REPORT_STATS=no \
        matrixdotorg/synapse:latest generate
fi

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout "$STACK_DIR/ssl/matrix.key" \
    -out "$STACK_DIR/ssl/matrix.crt" \
    -subj "/CN=cravacloud.local" >/dev/null 2>&1

cat > "$STACK_DIR/element-config.json" <<JSON
{
  "default_server_config": {
    "m.homeserver": {
      "base_url": "http://${PUBLIC_IP}:8008",
      "server_name": "matrix.cravacloud.local"
    }
  }
}
JSON

cat > "$STACK_DIR/nginx.conf" <<'NGINX'
server {
    listen 80 default_server;
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/ssl/matrix.crt;
    ssl_certificate_key /etc/nginx/ssl/matrix.key;
    server_name _;

    location /_matrix {
        proxy_pass http://synapse:8008;
        proxy_set_header Host $http_host;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location / {
        proxy_pass http://element:80;
        proxy_set_header Host $http_host;
    }
}
NGINX

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  postgres:
    image: postgres:16-alpine
    env_file: .env
    volumes:
      - ./db:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER} -d ${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 10
    restart: unless-stopped

  synapse:
    image: matrixdotorg/synapse:latest
    ports:
      - "8008:8008"
      - "8448:8008"
    volumes:
      - ./data:/data
    depends_on:
      postgres:
        condition: service_healthy
    restart: unless-stopped

  element:
    image: vectorim/element-web:latest
    volumes:
      - ./element-config.json:/app/config.json:ro
    restart: unless-stopped

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - ./ssl:/etc/nginx/ssl:ro
    depends_on:
      - synapse
      - element
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 8008/tcp
crava_ufw_allow 8448/tcp
crava_wait_port 8008 150 tcp
crava_wait_http "http://127.0.0.1/" 60

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Matrix Synapse (:8008/:8448) + Element Web aktif di port 80/443."
echo "CRAVA_INSTALL_OK {{stack}}"
