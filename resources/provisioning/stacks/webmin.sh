#!/usr/bin/env bash
# CravaCloud stack installer: webmin (idempotent, _common.sh hardened).
# Official Webmin repository setup script + Webmin package on port 10000.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && systemctl is-active --quiet webmin \
    && ss -tln 2>/dev/null | grep -qE ':10000([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian rocky

echo "== [1/3] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw
crava_ensure_swap 2048

echo "== [2/3] Webmin Official Repository & Package =="
curl -fsSL https://raw.githubusercontent.com/webmin/webmin/master/webmin-setup-repo.sh -o /tmp/webmin-setup-repo.sh
sh /tmp/webmin-setup-repo.sh -f
rm -f /tmp/webmin-setup-repo.sh

crava_wait_pkg_lock 120
if [ "$CRAVA_PKG_MGR" = "apt" ]; then
    apt-get update -y
    apt-get install -y --install-recommends webmin
else
    dnf install -y webmin
fi
systemctl enable --now webmin

echo "== [3/3] Readiness Gate & Firewall =="
crava_wait_service webmin 120
crava_wait_port 10000 120
crava_ufw_allow 22/tcp 80/tcp 443/tcp 10000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
