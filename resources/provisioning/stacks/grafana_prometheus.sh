#!/usr/bin/env bash
# CravaCloud stack installer: grafana_prometheus (idempotent, _common.sh hardened).
# Official Grafana + Prometheus + Node Exporter stack (D6 zero-echo admin password).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] \
    && [ -f /opt/crava-observability/compose.yml ] \
    && ss -tln 2>/dev/null | grep -qE ':3000([[:space:]]|$)' \
    && ss -tln 2>/dev/null | grep -qE ':9090([[:space:]]|$)'; then
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

echo "== [3/4] Grafana + Prometheus + Node Exporter =="
APP_DIR=/opt/crava-observability
mkdir -p "$APP_DIR/prometheus" "$APP_DIR/grafana/provisioning/datasources"
if [ ! -f "$APP_DIR/.env" ]; then
    umask 077
    {
        printf 'GF_SECURITY_ADMIN_USER=admin\n'
        printf 'GF_SECURITY_ADMIN_PASSWORD=%s\n' "$(openssl rand -hex 16)"
    } > "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
fi

cat > "$APP_DIR/prometheus/prometheus.yml" <<'YAML'
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: prometheus
    static_configs:
      - targets: ["localhost:9090"]
  - job_name: node_exporter
    static_configs:
      - targets: ["node-exporter:9100"]
YAML

cat > "$APP_DIR/grafana/provisioning/datasources/prometheus.yml" <<'YAML'
apiVersion: 1
datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    url: http://prometheus:9090
    isDefault: true
YAML

cat > "$APP_DIR/compose.yml" <<'YAML'
services:
  prometheus:
    image: prom/prometheus:latest
    container_name: crava-prometheus
    restart: unless-stopped
    ports: ["9090:9090"]
    volumes:
      - ./prometheus/prometheus.yml:/etc/prometheus/prometheus.yml:ro
      - prom_data:/prometheus
  node-exporter:
    image: prom/node-exporter:latest
    container_name: crava-node-exporter
    restart: unless-stopped
    pid: host
    volumes:
      - /proc:/host/proc:ro
      - /sys:/host/sys:ro
      - /:/rootfs:ro
    command:
      - '--path.procfs=/host/proc'
      - '--path.sysfs=/host/sys'
      - '--path.rootfs=/rootfs'
  grafana:
    image: grafana/grafana:latest
    container_name: crava-grafana
    restart: unless-stopped
    depends_on: [prometheus]
    ports: ["3000:3000"]
    environment:
      GF_SECURITY_ADMIN_USER: ${GF_SECURITY_ADMIN_USER}
      GF_SECURITY_ADMIN_PASSWORD: ${GF_SECURITY_ADMIN_PASSWORD}
    volumes:
      - grafana_data:/var/lib/grafana
      - ./grafana/provisioning:/etc/grafana/provisioning:ro
volumes:
  prom_data:
  grafana_data:
YAML
docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" up -d

echo "== [4/4] Readiness Gate & Firewall =="
crava_wait_port 9090 120
if ! crava_wait_port 3000 180; then
    docker compose --env-file "$APP_DIR/.env" -f "$APP_DIR/compose.yml" logs --tail 40 >&2 || true
    exit 1
fi
crava_ufw_allow 22/tcp 3000/tcp 9090/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Kredensial Grafana disimpan di /opt/crava-observability/.env (root-only)."
