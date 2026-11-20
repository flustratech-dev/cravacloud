#!/usr/bin/env bash
# CravaCloud stack installer: directadmin (BYOL, idempotent, _common.sh hardened).
# Launches official DirectAdmin Web-Based Installer (:35222) inside detached tmux PTY
# so customer enters their BYOL license key directly in browser without TAT timeout.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && ss -tln 2>/dev/null | grep -qE ':(2222|35222)([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian rocky

echo "== [1/3] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl tar perl tmux ufw
crava_ensure_swap 2048

echo "== [2/3] DirectAdmin Web Installer Setup =="
mkdir -p /usr/local/src
curl -fsSL https://download.directadmin.com/setup.sh -o /usr/local/src/crava-da-setup.sh
chmod 0755 /usr/local/src/crava-da-setup.sh

umask 077
touch /var/log/cravacloud-da-setup.log
chmod 600 /var/log/cravacloud-da-setup.log

if ! tmux has-session -t crava-da-setup 2>/dev/null; then
    tmux new-session -d -s crava-da-setup "sh /usr/local/src/crava-da-setup.sh > /var/log/cravacloud-da-setup.log 2>&1"
fi

echo "== [3/3] Readiness Gate (Web Setup Wizard :35222 / Panel :2222) & Firewall =="
DA_READY=0
for _ in $(seq 1 90); do
    if ss -tln 2>/dev/null | grep -qE ':(35222|2222)([[:space:]]|$)'; then
        DA_READY=1
        break
    fi
    sleep 2
done
if [ "$DA_READY" -ne 1 ]; then
    crava_wait_port 35222 10
fi

crava_ufw_allow 22/tcp 80/tcp 443/tcp 2222/tcp 35222/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "DirectAdmin BYOL Web Setup Wizard aktif. Ambil URL setup URL+key via: grep -o 'http://.*:35222/?key=[^ ]*' /var/log/cravacloud-da-setup.log"
