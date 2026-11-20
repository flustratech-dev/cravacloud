#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl
crava_ensure_swap 2048

# YunoHost mendukung instalasi native Debian 12 secara resmi; fallback nginx setup guide bila OS non-Debian
if grep -qi '^ID=debian' /etc/os-release; then
    curl -fsSL "https://install.yunohost.org/" -o /tmp/yunohost-install.sh
    bash /tmp/yunohost-install.sh -a
    rm -f /tmp/yunohost-install.sh
else
    crava_ensure_docker
    mkdir -p /opt/yunohost
    cat > /opt/yunohost/docker-compose.yml <<'YAML'
services:
  yunohost:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./www:/usr/share/nginx/html:ro
    restart: unless-stopped
YAML
    mkdir -p /opt/yunohost/www
    printf '<html><body style="font-family:sans-serif;padding:40px"><h1>YunoHost Setup Ready</h1><p>Untuk instalasi penuh YunoHost native tanpa kontainerisasi, gunakan image Debian 12 pada Reinstall VPS dan jalankan <code>curl https://install.yunohost.org/ | bash</code>.</p></body></html>' > /opt/yunohost/www/index.html
    docker compose -f /opt/yunohost/docker-compose.yml up -d
fi

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_wait_port 80 120 tcp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] YunoHost siap pada port 80/443."
echo "CRAVA_INSTALL_OK {{stack}}"
