#!/usr/bin/env bash
# CravaCloud stack installer: hestiacp (idempotent, _common.sh hardened).
# Admin password is generated on-server into /root/.crava-hestia (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && [ -d /usr/local/hestia ] && ss -tln 2>/dev/null | grep -qE ':8083([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl wget gnupg ufw openssl
crava_ensure_swap 2048

echo "== [2/4] HestiaCP =="
if [ ! -d /usr/local/hestia ]; then
    crava_wait_pkg_lock 120
    H_PASS="$(openssl rand -hex 16)"
    umask 077
    printf 'hestia_admin_password=%s\n' "$H_PASS" > /root/.crava-hestia
    chmod 600 /root/.crava-hestia
    curl -fsSL https://raw.githubusercontent.com/hestiacp/hestiacp/release/install/hst-install.sh -o /tmp/hst-install.sh
    bash /tmp/hst-install.sh --interactive no --force \
        --email "admin@{{hostname}}" --password "$H_PASS"
    rm -f /tmp/hst-install.sh
    unset H_PASS
fi

echo "== [3/4] Readiness Gate =="
crava_wait_port 8083 180

echo "== [4/4] Firewall & Finalize =="
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8083/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Admin password disimpan di /root/.crava-hestia (root-only)."
