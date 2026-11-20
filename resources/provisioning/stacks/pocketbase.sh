#!/usr/bin/env bash
# CravaCloud stack installer: pocketbase (idempotent, _common.sh hardened).
# Official PocketBase binary release via GitHub API + systemd service on port 8090.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && systemctl is-active --quiet pocketbase.service \
    && ss -tln 2>/dev/null | grep -qE ':8090([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian rocky centos

echo "== [1/3] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl unzip ufw
crava_ensure_swap 2048

echo "== [2/3] PocketBase Official Binary & Systemd =="
ARCH="amd64"
case "$(uname -m)" in
    aarch64|arm64) ARCH="arm64" ;;
esac

PB_TAG="$(curl -fsSL https://api.github.com/repos/pocketbase/pocketbase/releases/latest | grep -oE '"tag_name":\s*"v[^"]+"' | head -n1 | cut -d'"' -f4)"
PB_VER="${PB_TAG#v}"
PB_URL="https://github.com/pocketbase/pocketbase/releases/download/${PB_TAG}/pocketbase_${PB_VER}_linux_${ARCH}.zip"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
curl -fsSL "$PB_URL" -o "${TMP_DIR}/pb.zip"
unzip -oq "${TMP_DIR}/pb.zip" pocketbase -d /usr/local/bin
chmod +x /usr/local/bin/pocketbase

mkdir -p /opt/crava-pocketbase/pb_data
cat > /etc/systemd/system/pocketbase.service <<'UNIT'
[Unit]
Description=CravaCloud PocketBase BaaS Server
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
WorkingDirectory=/opt/crava-pocketbase
ExecStart=/usr/local/bin/pocketbase serve --http=0.0.0.0:8090 --dir=/opt/crava-pocketbase/pb_data
Restart=always
RestartSec=5
LimitNOFILE=4096

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now pocketbase.service

echo "== [3/3] Readiness Gate & Firewall =="
crava_wait_service pocketbase.service 120
crava_wait_port 8090 120
crava_ufw_allow 22/tcp 8090/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
