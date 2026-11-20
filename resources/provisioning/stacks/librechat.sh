#!/usr/bin/env bash
# CravaCloud stack installer: librechat (idempotent — safe to re-run via TAT retry).
# LibreChat + MongoDB + Meilisearch via docker compose. All crypto secrets are
# generated on-server into a root-only .env, NEVER echoed to stdout / TAT
# TaskResult (D6). Provider API keys are entered by the customer in the UI.
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/4] Base dependencies =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw openssl

echo "== [2/4] Docker =="
if ! command -v docker >/dev/null 2>&1; then
    curl -fsSL https://get.docker.com | sh
fi
systemctl enable --now docker

echo "== [3/4] LibreChat + MongoDB + Meilisearch =="
APP_DIR=/opt/crava-librechat
mkdir -p "$APP_DIR"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'CREDS_KEY=%s\n' "$(openssl rand -hex 32)"
        printf 'CREDS_IV=%s\n' "$(openssl rand -hex 16)"
        printf 'JWT_SECRET=%s\n' "$(openssl rand -hex 32)"
        printf 'JWT_REFRESH_SECRET=%s\n' "$(openssl rand -hex 32)"
        printf 'MEILI_MASTER_KEY=%s\n' "$(openssl rand -hex 24)"
    } > "$APP_DIR/.env"
fi
cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  mongodb:
    image: mongo:7
    restart: unless-stopped
    volumes:
      - mongo_data:/data/db
  meilisearch:
    image: getmeili/meilisearch:v1.7
    restart: unless-stopped
    environment:
      MEILI_MASTER_KEY: ${MEILI_MASTER_KEY}
      MEILI_NO_ANALYTICS: "true"
    volumes:
      - meili_data:/meili_data
  librechat:
    image: ghcr.io/danny-avila/librechat:latest
    restart: unless-stopped
    depends_on: [mongodb, meilisearch]
    ports: ["3080:3080"]
    environment:
      HOST: 0.0.0.0
      PORT: 3080
      MONGO_URI: mongodb://mongodb:27017/LibreChat
      MEILI_HOST: http://meilisearch:7700
      MEILI_MASTER_KEY: ${MEILI_MASTER_KEY}
      CREDS_KEY: ${CREDS_KEY}
      CREDS_IV: ${CREDS_IV}
      JWT_SECRET: ${JWT_SECRET}
      JWT_REFRESH_SECRET: ${JWT_REFRESH_SECRET}
      DOMAIN_CLIENT: http://{{hostname}}:3080
      DOMAIN_SERVER: http://{{hostname}}:3080
    volumes:
      - librechat_data:/app/client/public/images
volumes:
  mongo_data:
  meili_data:
  librechat_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Firewall + finalize =="
for p in 22 3080; do ufw allow "$p"/tcp || true; done
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Secret aplikasi disimpan di /opt/crava-librechat/.env (root-only)."