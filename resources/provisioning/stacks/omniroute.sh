#!/usr/bin/env bash
# CravaCloud stack installer: omniroute (idempotent, _common.sh hardened).
# Official npm package `omniroute` (cheaper-inference/OmniRoute) bound strictly
# to loopback 127.0.0.1:20128. NO extra inbound firewall port is opened (SSH 22 only).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && systemctl is-active --quiet crava-omniroute.service; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian rocky centos

echo "== [1/4] Dependencies, Node.js 22 LTS & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg openssl
if command -v ufw >/dev/null 2>&1; then
    true
elif [ "$CRAVA_PKG_MGR" = "apt" ]; then
    crava_pkg_install ufw
fi
crava_ensure_swap 2048

NODE_MAJOR=0
if command -v node >/dev/null 2>&1; then
    NODE_MAJOR="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
fi
if [ "$NODE_MAJOR" -lt 22 ]; then
    crava_wait_pkg_lock 120
    if [ "$CRAVA_PKG_MGR" = "apt" ]; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
        apt-get install -y nodejs
    else
        curl -fsSL https://rpm.nodesource.com/setup_22.x | bash -
        dnf install -y nodejs || yum install -y nodejs
    fi
fi

echo "== [2/4] Install OmniRoute (Official npm) =="
npm install -g omniroute

echo "== [3/4] Loopback Systemd Service =="
APP_DIR=/opt/crava-omniroute
mkdir -p "$APP_DIR/data"
if [ ! -f "$APP_DIR/connector.env" ]; then
    umask 077
    {
        printf 'HOST=127.0.0.1\n'
        printf 'PORT=20128\n'
        printf 'DASHBOARD_PORT=20128\n'
        printf 'DATA_DIR=%s/data\n' "$APP_DIR"
        printf 'JWT_SECRET=%s\n' "$(openssl rand -hex 32)"
        printf 'INITIAL_PASSWORD=%s\n' "$(openssl rand -hex 16)"
    } > "$APP_DIR/connector.env"
    chmod 600 "$APP_DIR/connector.env"
fi

cat > /etc/systemd/system/crava-omniroute.service <<UNIT
[Unit]
Description=CravaCloud OmniRoute LLM Gateway (Loopback Only)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
WorkingDirectory=$APP_DIR
EnvironmentFile=$APP_DIR/connector.env
ExecStart=/usr/bin/env omniroute
Restart=always
RestartSec=5
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now crava-omniroute.service

echo "== [4/4] Readiness Gate & Firewall (SSH only) =="
crava_wait_service crava-omniroute.service 120
crava_wait_port 20128 120
crava_ufw_allow 22/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Konfigurasi loopback & kredensial awal tersimpan di /opt/crava-omniroute/connector.env (root-only)."
