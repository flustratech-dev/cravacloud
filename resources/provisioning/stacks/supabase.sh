#!/usr/bin/env bash
# CravaCloud stack installer: supabase (idempotent, _common.sh hardened).
# Official supabase/supabase docker stack + official utils/generate-keys.sh (D6 zero-echo).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -d /opt/crava-supabase ] \
    && docker ps --format '{{.Names}}' 2>/dev/null | grep -q supabase-studio \
    && ss -tln 2>/dev/null | grep -qE ':8000([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg git openssl ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] Supabase Self-Hosted (Official Repo + Key Generator) =="
APP_DIR=/opt/crava-supabase
if [ ! -f "$APP_DIR/docker-compose.yml" ]; then
    TMP_REPO="$(mktemp -d)"
    trap 'rm -rf "$TMP_REPO"' EXIT
    git clone --depth 1 --filter=blob:none --sparse https://github.com/supabase/supabase.git "$TMP_REPO"
    git -C "$TMP_REPO" sparse-checkout set docker
    mkdir -p "$APP_DIR"
    cp -rf "$TMP_REPO"/docker/* "$APP_DIR/"
fi

cd "$APP_DIR"
if [ ! -f .env ]; then
    umask 077
    cp .env.example .env
    chmod 600 .env
    if [ -x utils/generate-keys.sh ] || [ -f utils/generate-keys.sh ]; then
        sh utils/generate-keys.sh --update-env >/dev/null 2>&1
    fi
    PUB_IP="$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -n1 || true)"
    if [ -n "$PUB_IP" ]; then
        sed -i "s|^SUPABASE_PUBLIC_URL=.*|SUPABASE_PUBLIC_URL=http://${PUB_IP}:8000|" .env || true
        sed -i "s|^API_EXTERNAL_URL=.*|API_EXTERNAL_URL=http://${PUB_IP}:8000|" .env || true
        sed -i "s|^SITE_URL=.*|SITE_URL=http://${PUB_IP}:8000|" .env || true
    fi
    chmod 600 .env
fi

docker compose pull --quiet || docker compose pull
docker compose up -d

echo "== [4/4] Readiness Gate & Firewall =="
if ! crava_wait_port 8000 240; then
    docker compose logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 80/tcp 443/tcp 8000/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial & JWT secrets Supabase disimpan di /opt/crava-supabase/.env (root-only)."
