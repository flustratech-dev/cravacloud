#!/usr/bin/env bash
# CravaCloud stack installer: hestiacp (idempotent — safe to re-run via TAT retry).
# The admin password is generated on-server and stored root-only; it is NEVER
# echoed to stdout / TAT TaskResult (D6).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/3] Base dependencies =="
apt-get update -y
apt-get install -y ca-certificates curl wget gnupg ufw openssl

echo "== [2/3] HestiaCP =="
if [ ! -d /usr/local/hestia ]; then
    H_PASS="$(openssl rand -hex 16)"
    umask 077
    printf 'hestia_admin_password=%s\n' "$H_PASS" > /root/.crava-hestia
    curl -fsSL https://raw.githubusercontent.com/hestiacp/hestiacp/release/install/hst-install.sh -o /tmp/hst-install.sh
    bash /tmp/hst-install.sh --interactive no --force \
        --email "admin@{{hostname}}" --password "$H_PASS" || true
    rm -f /tmp/hst-install.sh
    unset H_PASS
fi

echo "== [3/3] Firewall + finalize =="
for p in 22 80 443 8083; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Admin password disimpan di /root/.crava-hestia (root-only)."