#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/authentik"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/media" "$STACK_DIR/custom-templates" "$STACK_DIR/certs"
umask 077

PG_PWD="$(openssl rand -hex 18)"
AUTHENTIK_SEC="$(openssl rand -base64 48 | tr -d '\n')"

cat > "$STACK_DIR/.env" <<EOF
PG_PASS=${PG_PWD}
PG_USER=authentik
PG_DB=authentik
AUTHENTIK_SECRET_KEY=${AUTHENTIK_SEC}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  postgresql:
    image: postgres:16-alpine
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -d $${POSTGRES_DB} -U $${POSTGRES_USER}"]
      interval: 5s
      retries: 10
      timeout: 5s
    volumes:
      - database:/var/lib/postgresql/data
    environment:
      POSTGRES_PASSWORD: ${PG_PASS}
      POSTGRES_USER: ${PG_USER}
      POSTGRES_DB: ${PG_DB}
    env_file:
      - .env

  server:
    image: ghcr.io/goauthentik/server:2026.8.5
    restart: unless-stopped
    command: server
    environment:
      AUTHENTIK_POSTGRESQL__HOST: postgresql
      AUTHENTIK_POSTGRESQL__USER: ${PG_USER}
      AUTHENTIK_POSTGRESQL__NAME: ${PG_DB}
      AUTHENTIK_POSTGRESQL__PASSWORD: ${PG_PASS}
    volumes:
      - ./media:/media
      - ./custom-templates:/templates
    env_file:
      - .env
    ports:
      - "9000:9000"
      - "9443:9443"
    depends_on:
      postgresql:
        condition: service_healthy

  worker:
    image: ghcr.io/goauthentik/server:2026.8.5
    restart: unless-stopped
    command: worker
    environment:
      AUTHENTIK_POSTGRESQL__HOST: postgresql
      AUTHENTIK_POSTGRESQL__USER: ${PG_USER}
      AUTHENTIK_POSTGRESQL__NAME: ${PG_DB}
      AUTHENTIK_POSTGRESQL__PASSWORD: ${PG_PASS}
    user: root
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./media:/media
      - ./certs:/certs
      - ./custom-templates:/templates
    env_file:
      - .env
    depends_on:
      postgresql:
        condition: service_healthy

volumes:
  database:
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 9000/tcp
crava_ufw_allow 9443/tcp
crava_wait_port 9000 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Authentik SSO aktif di port 9000 (/if/flow/initial-setup/)."
echo "CRAVA_INSTALL_OK {{stack}}"
