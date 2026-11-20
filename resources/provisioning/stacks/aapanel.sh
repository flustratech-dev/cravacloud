#!/usr/bin/env bash
# CravaCloud stack installer: aapanel (idempotent — safe to re-run via TAT retry).
# Admin credentials are set by the customer on first-run web setup (D6: no
# plaintext panel credentials travel through TAT).
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

echo "== [2/3] aaPanel =="
if [ ! -d /www/server/panel ]; then
    curl -sSO https://www.aapanel.com/script/install_7.0_en.sh
    bash install_7.0_en.sh aapanel
    rm -f install_7.0_en.sh
fi

echo "== [3/3] Firewall + finalize =="
for p in 22 80 443 8888 888 20 21; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"