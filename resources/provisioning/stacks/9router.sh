#!/usr/bin/env bash
# CravaCloud stack installer: 9router (idempotent — safe to re-run via TAT retry).
# Proxy connector: ALL traffic leaves OUTBOUND through the central CravaCloud
# gateway 43.173.51.90:20129. NO additional inbound firewall port is opened on
# the customer VPS (only SSH 22 remains). The connector token is provisioned
# on-server into a root-only file and NEVER echoed to stdout / TAT TaskResult (D6).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

GATEWAY_HOST=43.173.51.90
GATEWAY_PORT=20129

echo "== [1/4] Base dependencies =="
if command -v apt-get >/dev/null 2>&1; then
    apt-get update -y
    apt-get install -y ca-certificates curl gnupg ufw openssl
else
    dnf install -y ca-certificates curl openssl || yum install -y ca-certificates curl openssl
fi

echo "== [2/4] Connector binary =="
APP_DIR=/opt/crava-9router
mkdir -p "$APP_DIR"
[ -f "$APP_DIR/release.env" ] || printf 'NINEROUTER_URL=https://registry.cravacloud.id/releases/9router-linux-amd64\n' > "$APP_DIR/release.env"
# shellcheck disable=SC1091
. "$APP_DIR/release.env"
if [ ! -x "$APP_DIR/9router" ]; then
    curl -fsSL "$NINEROUTER_URL" -o "$APP_DIR/9router"
    chmod 0755 "$APP_DIR/9router"
fi

echo "== [3/4] Outbound systemd service =="
if [ ! -f "$APP_DIR/connector.env" ]; then
    umask 077
    printf 'CONNECTOR_TOKEN=%s\n' "$(openssl rand -hex 24)" > "$APP_DIR/connector.env"
fi
cat > /etc/systemd/system/crava-9router.service <<UNIT
[Unit]
Description=CravaCloud 9router outbound proxy connector
After=network-online.target
Wants=network-online.target

[Service]
EnvironmentFile=$APP_DIR/connector.env
ExecStart=$APP_DIR/9router --gateway ${GATEWAY_HOST}:${GATEWAY_PORT} --mode outbound
Restart=always
RestartSec=5
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now crava-9router.service

echo "== [4/4] Firewall (SSH only — no extra inbound) + finalize =="
ufw allow 22/tcp || true
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Connector token disimpan di /opt/crava-9router/connector.env (root-only)."