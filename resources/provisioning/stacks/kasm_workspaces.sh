#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/kasm"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl tar
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR"
umask 077
ADMIN_PWD="$(openssl rand -hex 14)"
USER_PWD="$(openssl rand -hex 14)"

cat > "$STACK_DIR/.env" <<EOF
KASM_ADMIN_EMAIL=admin@kasm.local
KASM_ADMIN_PASSWORD=${ADMIN_PWD}
KASM_USER_PASSWORD=${USER_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cd /tmp
curl -fsSL "https://kasm-static-content.s3.amazonaws.com/kasm_release_1.17.0.bbc15c.tar.gz" -o kasm_release.tar.gz
tar -xf kasm_release.tar.gz
bash kasm_release/install.sh -e -L 443 -U "${USER_PWD}" -P "${ADMIN_PWD}" || true
rm -rf /tmp/kasm_release /tmp/kasm_release.tar.gz

crava_ufw_allow 22/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 3000/tcp
crava_wait_port 443 240 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Kasm Workspaces aktif di https://<IP>. Kredensial tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
