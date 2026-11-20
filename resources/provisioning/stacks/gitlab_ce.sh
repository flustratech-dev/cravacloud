#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/gitlab"

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

mkdir -p "$STACK_DIR/config" "$STACK_DIR/logs" "$STACK_DIR/data"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
ROOT_PWD="$(openssl rand -hex 14)"

cat > "$STACK_DIR/.env" <<EOF
GITLAB_ROOT_USER=root
GITLAB_ROOT_PASSWORD=${ROOT_PWD}
EOF
chmod 600 "$STACK_DIR/.env"

cat > "$STACK_DIR/docker-compose.yml" <<YAML
services:
  gitlab:
    image: gitlab/gitlab-ce:latest
    restart: unless-stopped
    hostname: '${PUBLIC_IP}'
    environment:
      GITLAB_OMNIBUS_CONFIG: |
        external_url 'http://${PUBLIC_IP}'
        gitlab_rails['gitlab_shell_ssh_port'] = 2222
        gitlab_rails['initial_root_password'] = "${ROOT_PWD}"
    ports:
      - "80:80"
      - "443:443"
      - "2222:22"
    volumes:
      - ./config:/etc/gitlab
      - ./logs:/var/log/gitlab
      - ./data:/var/opt/gitlab
    shm_size: '256m'
YAML

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 2222/tcp
crava_wait_port 80 300 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] GitLab CE aktif di http://${PUBLIC_IP}. Password root tersimpan di $STACK_DIR/.env."
echo "CRAVA_INSTALL_OK {{stack}}"
