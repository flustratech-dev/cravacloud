#!/usr/bin/env bash
# CravaCloud stack installer: fastpanel (idempotent, _common.sh hardened).
# Official FASTPANEL installer (D6 zero-echo: fastuser credentials routed to root-only log).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && systemctl is-active --quiet fastpanel2 \
    && ss -tln 2>/dev/null | grep -qE ':8888([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/3] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates wget curl gnupg ufw
crava_ensure_swap 2048

echo "== [2/3] FASTPANEL Setup (Official Installer) =="
umask 077
touch /root/.crava-fastpanel-install.log
chmod 600 /root/.crava-fastpanel-install.log

wget -q https://repo.fastpanel.direct/install_fastpanel.sh -O /tmp/install_fastpanel.sh
bash /tmp/install_fastpanel.sh -f >/root/.crava-fastpanel-install.log 2>&1
rm -f /tmp/install_fastpanel.sh

echo "== [3/3] Readiness Gate & Firewall =="
crava_wait_service fastpanel2 180
crava_wait_port 8888 180
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8888/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial login fastuser FASTPANEL disimpan di /root/.crava-fastpanel-install.log (root-only)."
