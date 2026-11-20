#!/usr/bin/env bash
# CravaCloud stack installer: cloudpanel (idempotent — safe to re-run via TAT retry).
# Admin account is created by the customer on first-run web setup (D6: no
# plaintext panel credentials travel through TAT).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/4] Base dependencies =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw

echo "== [2/4] CloudPanel CE =="
if [ ! -d /home/clp ]; then
    curl -fsSL https://installer.cloudpanel.io/ce/v2/install.sh -o /tmp/cloudpanel-install.sh
    bash /tmp/cloudpanel-install.sh
    rm -f /tmp/cloudpanel-install.sh
fi

echo "== [3/4] Firewall =="
ufw allow 22/tcp || true
ufw allow 80/tcp || true
ufw allow 443/tcp || true
ufw allow 8443/tcp || true

echo "== [4/4] Finalize =="
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"