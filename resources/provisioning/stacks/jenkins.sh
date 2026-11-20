#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/jenkins"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/jenkins_home"
chown -R 1000:1000 "$STACK_DIR/jenkins_home"

cat > "$STACK_DIR/docker-compose.yml" <<'YAML'
services:
  jenkins:
    image: jenkins/jenkins:lts
    ports:
      - "8080:8080"
      - "127.0.0.1:50000:50000"
    volumes:
      - ./jenkins_home:/var/jenkins_home
    restart: unless-stopped
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 8080/tcp
crava_wait_port 8080 180 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] Jenkins CI/CD aktif di port 8080. Initial password di $STACK_DIR/jenkins_home/secrets/initialAdminPassword."
echo "CRAVA_INSTALL_OK {{stack}}"
