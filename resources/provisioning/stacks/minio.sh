#!/usr/bin/env bash
# CravaCloud stack installer: minio (idempotent, _common.sh hardened).
# Official MinIO S3-compatible object storage (port 9000 API, 9001 Console UI). D6 zero-echo.
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-minio/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':9000([[:space:]]|$)' \
    && ss -tln 2>/dev/null | grep -qE ':9001([[:space:]]|$)'; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg openssl ufw
crava_ensure_swap 2048

echo "== [2/4] Docker Engine =="
crava_ensure_docker

echo "== [3/4] MinIO S3 Object Storage =="
APP_DIR=/opt/crava-minio
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'MINIO_ROOT_USER=cravaadmin\n'
        printf 'MINIO_ROOT_PASSWORD=%s\n' "$(openssl rand -hex 20)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  minio:
    image: alpine/minio:latest-release
    container_name: crava-minio
    restart: unless-stopped
    command: server /data --console-address ":9001"
    ports:
      - "9000:9000"
      - "9001:9001"
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD}
    volumes:
      - minio_data:/data
volumes:
  minio_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 9000 120
if ! crava_wait_port 9001 120; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 9000/tcp 9001/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial MinIO root disimpan di /opt/crava-minio/.env (root-only)."
