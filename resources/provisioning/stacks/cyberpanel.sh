#!/usr/bin/env bash
# CravaCloud stack installer: cyberpanel (idempotent, _common.sh hardened).
# Admin password is generated on-server into /root/.crava-cyberpanel (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && [ -d /usr/local/CyberCP ] && ss -tln 2>/dev/null | grep -qE ':8090([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu rocky centos

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl wget openssl
if [ "$CRAVA_PKG_MGR" = "apt" ]; then
    crava_pkg_install gnupg ufw
fi
crava_ensure_swap 2048

echo "== [2/4] CyberPanel (OpenLiteSpeed) =="
if [ ! -d /usr/local/CyberCP ]; then
    crava_wait_pkg_lock 120
    CP_PASS="$(openssl rand -hex 16)"
    umask 077
    printf 'cyberpanel_admin_password=%s\n' "$CP_PASS" > /root/.crava-cyberpanel
    chmod 600 /root/.crava-cyberpanel
    curl -fsSL https://cyberpanel.net/install.sh -o /tmp/cyberpanel-install.sh
    sh /tmp/cyberpanel-install.sh --password "$CP_PASS"
    rm -f /tmp/cyberpanel-install.sh
    unset CP_PASS
fi

echo "== [3/4] Readiness Gate =="
crava_wait_port 8090 180

echo "== [4/4] Firewall & Finalize =="
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8090/tcp 7080/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Admin password disimpan di /root/.crava-cyberpanel (root-only)."
