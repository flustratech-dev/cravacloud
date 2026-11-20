#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
APP_DIR="/opt/fastapi-app"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl python3 python3-venv python3-pip
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$APP_DIR"
python3 -m venv "$APP_DIR/.venv"
"$APP_DIR/.venv/bin/pip" install --no-cache-dir --upgrade pip fastapi "uvicorn[standard]" gunicorn

cat > "$APP_DIR/main.py" <<'PY'
from fastapi import FastAPI

app = FastAPI(title="CravaCloud FastAPI Starter")

@app.get("/")
def root():
    return {"status": "ok", "stack": "Python + FastAPI + Caddy"}
PY

cat > /etc/systemd/system/crava-fastapi.service <<'SERVICE'
[Unit]
Description=CravaCloud FastAPI Uvicorn Service
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/fastapi-app
ExecStart=/opt/fastapi-app/.venv/bin/uvicorn main:app --host 0.0.0.0 --port 8000
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable --now crava-fastapi

cat > "$APP_DIR/Caddyfile" <<'CADDY'
:80 {
    reverse_proxy host.docker.internal:8000
}
:443 {
    tls internal
    reverse_proxy host.docker.internal:8000
}
CADDY

cat > "$APP_DIR/docker-compose.yml" <<'YAML'
services:
  caddy:
    image: caddy:2-alpine
    extra_hosts:
      - "host.docker.internal:host-gateway"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy-data:/data
    restart: unless-stopped
volumes:
  caddy-data:
YAML

docker compose -f "$APP_DIR/docker-compose.yml" pull
docker compose -f "$APP_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 8000/tcp
crava_wait_http "http://127.0.0.1:8000/" 30
crava_wait_http "http://127.0.0.1/" 30

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Python + FastAPI + Caddy aktif di $APP_DIR (port 80/443 & 8000)."
echo "CRAVA_INSTALL_OK {{stack}}"
