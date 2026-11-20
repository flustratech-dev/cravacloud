#!/usr/bin/env bash
# CravaCloud stack installer: 1panel (idempotent, _common.sh hardened).
# Official 1Panel v2 tarball with non-interactive flags on port 10086 (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && systemctl is-active --quiet 1panel-core \
    && ss -tln 2>/dev/null | grep -qE ':10086([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian rocky centos

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl tar openssl ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] 1Panel v2 Non-Interactive Install =="
ARCH="amd64"
case "$(uname -m)" in
    aarch64|arm64) ARCH="arm64" ;;
esac

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

VERSION="$(curl -fsSL https://resource.1panel.pro/v2/stable/latest | tr -d '[:space:]')"
TARBALL="1panel-${VERSION}-linux-${ARCH}.tar.gz"
curl -fsSL "https://resource.1panel.pro/v2/stable/${VERSION}/release/${TARBALL}" -o "${TMP_DIR}/${TARBALL}"
tar -zxf "${TMP_DIR}/${TARBALL}" -C "${TMP_DIR}"
EXTRACT_DIR="${TMP_DIR}/1panel-${VERSION}-linux-${ARCH}"

CREDS_FILE=/root/.crava-1panel.env
if [ ! -f "$CREDS_FILE" ]; then
    umask 077
    RAND_PASS="$(openssl rand -hex 10)"
    RAND_ENTRANCE="crava$(openssl rand -hex 3)"
    cat > "$CREDS_FILE" <<ENV
PANEL_PORT=10086
PANEL_USERNAME=cravaadmin
PANEL_PASSWORD=${RAND_PASS}
PANEL_ENTRANCE=${RAND_ENTRANCE}
ENV
    chmod 600 "$CREDS_FILE"
fi
# shellcheck disable=SC1090
. "$CREDS_FILE"

umask 077
touch /var/log/cravacloud-1panel-setup.log
chmod 600 /var/log/cravacloud-1panel-setup.log

cd "$EXTRACT_DIR"
PANEL_NON_INTERACTIVE=true \
PANEL_LANG=en \
PANEL_INSTALL_DIR=/opt \
PANEL_PORT=10086 \
PANEL_ENTRANCE="$PANEL_ENTRANCE" \
PANEL_USERNAME="$PANEL_USERNAME" \
PANEL_PASSWORD="$PANEL_PASSWORD" \
PANEL_INSTALL_DOCKER=n \
bash install.sh --non-interactive --lang en --port 10086 --install-docker n >/var/log/cravacloud-1panel-setup.log 2>&1

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_service 1panel-core 120
crava_wait_port 10086 120
crava_ufw_allow 22/tcp 80/tcp 443/tcp 10086/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial & URL secure entrance 1Panel disimpan di /root/.crava-1panel.env (root-only)."
