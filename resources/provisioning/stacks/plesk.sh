#!/usr/bin/env bash
# CravaCloud stack installer: plesk (BYOL Linux Edition, idempotent, _common.sh hardened).
# Official Plesk Obsidian one-click installer. Customer enters trial/BYOL key in Plesk UI (:8443).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && command -v plesk >/dev/null 2>&1 \
    && ss -tln 2>/dev/null | grep -qE ':8443([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/3] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl wget ufw
crava_ensure_swap 2048

echo "== [2/3] Plesk Obsidian Official One-Click Installer =="
umask 077
touch /var/log/cravacloud-plesk-setup.log
chmod 600 /var/log/cravacloud-plesk-setup.log

curl -fsSL https://autoinstall.plesk.com/one-click-installer -o /tmp/plesk-one-click-installer.sh
sh /tmp/plesk-one-click-installer.sh >/var/log/cravacloud-plesk-setup.log 2>&1
rm -f /tmp/plesk-one-click-installer.sh

echo "== [3/3] Readiness Gate & Firewall =="
crava_wait_service sw-cp-server 120
crava_wait_port 8443 180
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8443/tcp 8880/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Plesk Obsidian (BYOL) siap pada port 8443. Jalankan 'plesk login' via SSH untuk link login perdana."
