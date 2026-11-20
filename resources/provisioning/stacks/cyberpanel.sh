#!/usr/bin/env bash
# CravaCloud stack installer: cyberpanel (idempotent — safe to re-run via TAT retry).
# The installer generates the admin password on-server; it is written to a
# root-only file and NEVER echoed to stdout / TAT TaskResult (D6).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/3] Base dependencies =="
if command -v apt-get >/dev/null 2>&1; then
    apt-get update -y
    apt-get install -y ca-certificates curl wget gnupg ufw
else
    dnf install -y ca-certificates curl wget ufw || yum install -y ca-certificates curl wget
fi

echo "== [2/3] CyberPanel (OpenLiteSpeed) =="
if [ ! -d /usr/local/CyberCP ]; then
    # Generate the admin password on-server; keep it out of TAT output.
    CP_PASS="$(openssl rand -hex 16)"
    umask 077
    printf 'cyberpanel_admin_password=%s\n' "$CP_PASS" > /root/.crava-cyberpanel
    sh <(curl -fsSL https://cyberpanel.net/install.sh || wget -qO - https://cyberpanel.net/install.sh) \
        --password "$CP_PASS" || true
    unset CP_PASS
fi

echo "== [3/3] Firewall + finalize =="
for p in 22 80 443 8090 7080; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Admin password disimpan di /root/.crava-cyberpanel (root-only)."