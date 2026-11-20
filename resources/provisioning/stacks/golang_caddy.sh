#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
APP_DIR="/opt/golang-app"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates golang-go
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$APP_DIR"
cat > "$APP_DIR/main.go" <<'GO'
package main

import (
	"fmt"
	"net/http"
)

func main() {
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		fmt.Fprint(w, `{"status":"ok","stack":"Go + Caddy"}`)
	})
	_ = http.ListenAndServe("0.0.0.0:8080", nil)
}
GO

cd "$APP_DIR"
go mod init crava-go-starter >/dev/null 2>&1 || true
go build -o "$APP_DIR/server" main.go

cat > /etc/systemd/system/crava-golang.service <<'SERVICE'
[Unit]
Description=CravaCloud Go Web Service
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/golang-app
ExecStart=/opt/golang-app/server
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
SERVICE

systemctl daemon-reload
systemctl enable --now crava-golang

cat > "$APP_DIR/Caddyfile" <<'CADDY'
:80 {
    reverse_proxy host.docker.internal:8080
}
:443 {
    tls internal
    reverse_proxy host.docker.internal:8080
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
crava_ufw_allow 8080/tcp
crava_wait_http "http://127.0.0.1:8080/" 30
crava_wait_http "http://127.0.0.1/" 30

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Go + Caddy aktif di $APP_DIR (port 80/443 & 8080)."
echo "CRAVA_INSTALL_OK {{stack}}"
