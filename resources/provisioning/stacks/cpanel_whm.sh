#!/usr/bin/env bash
# CravaCloud stack installer: cpanel_whm (BYOL, idempotent, _common.sh hardened).
# Official cPanel & WHM installer. Customer activates trial/BYOL license in WHM (:2087).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -x /usr/local/cpanel/cpkeyclt ] \
    && ss -tln 2>/dev/null | grep -qE ':2087([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu rocky

echo "== [1/4] Dependencies, FQDN Hostname & 2 GB Swap =="
crava_pkg_install ca-certificates curl perl wget ufw
crava_ensure_swap 2048

CURRENT_HOST="$(hostname -f 2>/dev/null || hostname)"
case "$CURRENT_HOST" in
    *.*) ;;
    *)
        hostnamectl set-hostname "${CURRENT_HOST}.cravacloud.local" >/dev/null 2>&1 || true
        printf '127.0.1.1 %s.cravacloud.local %s\n' "$CURRENT_HOST" "$CURRENT_HOST" >> /etc/hosts || true
        ;;
esac

echo "== [2/4] Disable Conflicting NetworkManager (RHEL-family) =="
if [ "$CRAVA_PKG_MGR" != "apt" ] && systemctl is-active --quiet NetworkManager 2>/dev/null; then
    systemctl stop NetworkManager || true
    systemctl disable NetworkManager || true
fi

echo "== [3/4] cPanel & WHM Official Installer =="
umask 077
touch /var/log/cravacloud-cpanel-setup.log
chmod 600 /var/log/cravacloud-cpanel-setup.log

mkdir -p /home
cd /home
curl -fsSL https://securedownloads.cpanel.net/latest -o /home/latest
sh /home/latest >/var/log/cravacloud-cpanel-setup.log 2>&1
rm -f /home/latest

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 2087 180
crava_ufw_allow 22/tcp 80/tcp 443/tcp 2083/tcp 2087/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "cPanel/WHM (BYOL) siap pada port 2087. Aktivasi trial/license key dilakukan mandiri di WHM."
