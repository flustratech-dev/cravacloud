#!/usr/bin/env bash
# CravaCloud stack installer: casaos (idempotent, _common.sh hardened).
# Official IceWhaleTech CasaOS installer script.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && systemctl is-active --quiet casaos-gateway.service \
    && ss -tln 2>/dev/null | grep -qE ':(80|8080)([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] CasaOS (Official Installer) =="
curl -fsSL https://get.casaos.io | bash

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_service casaos-gateway.service 120
CASA_PORT="$(grep -E '^port=' /etc/casaos/gateway.ini 2>/dev/null | cut -d= -f2 | tr -d '[:space:]' || true)"
crava_wait_port "${CASA_PORT:-80}" 120
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8080/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
