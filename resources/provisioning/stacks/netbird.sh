#!/usr/bin/env bash
set -euo pipefail

# {{common}}

MARKER="/var/lib/cravacloud/.installed-{{stack}}"
STACK_DIR="/opt/netbird"

crava_require_os ubuntu debian

if [[ -f "$MARKER" ]]; then
    echo "[cravacloud] {{stack}} sudah terpasang."
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

crava_wait_pkg_lock
crava_pkg_install curl ca-certificates openssl jq
crava_ensure_swap 2048
crava_ensure_docker

mkdir -p "$STACK_DIR/mgmt" "$STACK_DIR/signal" "$STACK_DIR/caddy-data" "$STACK_DIR/turn"
umask 077
PUBLIC_IP="$(curl -fsSL https://checkip.amazonaws.com 2>/dev/null || hostname -I | awk '{print $1}')"
TURN_PWD="$(openssl rand -hex 18)"
STORE_KEY="$(openssl rand -base64 32 | tr -d '\n')"

cat > "$STACK_DIR/turnserver.conf" <<TURN
listening-port=3478
tls-listening-port=5349
fingerprint
lt-cred-mech
user=netbird:${TURN_PWD}
realm=${PUBLIC_IP}
external-ip=${PUBLIC_IP}
no-stdout-log
TURN

cat > "$STACK_DIR/management.json" <<MGMT
{
  "Stuns": [
    { "Proto": "udp", "URI": "stun:${PUBLIC_IP}:3478", "Username": "", "Password": "" }
  ],
  "TURNConfig": {
    "TimeBasedCredentials": false,
    "CredentialsTTL": "12h0m0s",
    "Secret": "${TURN_PWD}",
    "Turns": [
      { "Proto": "udp", "URI": "turn:${PUBLIC_IP}:3478", "Username": "netbird", "Password": "${TURN_PWD}" }
    ]
  },
  "Relay": {
    "Addresses": ["rel://${PUBLIC_IP}:33080"],
    "Secret": "${TURN_PWD}"
  },
  "Signal": { "Proto": "http", "URI": "${PUBLIC_IP}:80" },
  "HttpConfig": { "AuthIssuer": "", "AuthAudience": "", "OIDCConfigEndpoint": "" },
  "DataStoreEncryptionKey": "${STORE_KEY}"
}
MGMT

cat > "$STACK_DIR/Caddyfile" <<'CADDY'
:80 {
    handle /signalexchange.SignalExchange/* {
        reverse_proxy signal:80 {
            transport http {
                versions h2c
            }
        }
    }
    handle /api/* {
        reverse_proxy management:443
    }
    handle /management.ManagementService/* {
        reverse_proxy management:443 {
            transport http {
                versions h2c
            }
        }
    }
    handle {
        reverse_proxy dashboard:80
    }
}
:443 {
    tls internal
    handle /api/* {
        reverse_proxy management:443
    }
    handle {
        reverse_proxy dashboard:80
    }
}
CADDY

cat > "$STACK_DIR/docker-compose.yml" <<EOF
services:
  coturn:
    image: coturn/coturn:latest
    ports:
      - "3478:3478/udp"
    volumes:
      - ./turnserver.conf:/etc/turnserver.conf:ro
    command: ["-c", "/etc/turnserver.conf"]
    restart: unless-stopped

  signal:
    image: netbirdio/signal:latest
    restart: unless-stopped

  management:
    image: netbirdio/management:latest
    ports:
      - "33073:443"
    volumes:
      - ./mgmt:/var/lib/netbird
      - ./management.json:/etc/netbird/management.json:ro
    command: ["--port", "443", "--log-file", "console", "--disable-anonymous-metrics"]
    restart: unless-stopped

  dashboard:
    image: netbirdio/dashboard:latest
    environment:
      NETBIRD_MGMT_API_ENDPOINT: https://${PUBLIC_IP}
      NETBIRD_MGMT_GRPC_API_ENDPOINT: https://${PUBLIC_IP}
    restart: unless-stopped

  caddy:
    image: caddy:2-alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - ./caddy-data:/data
    depends_on:
      - dashboard
      - management
      - signal
    restart: unless-stopped
EOF

docker compose -f "$STACK_DIR/docker-compose.yml" pull
docker compose -f "$STACK_DIR/docker-compose.yml" up -d

crava_ufw_allow 22/tcp
crava_ufw_allow 80/tcp
crava_ufw_allow 443/tcp
crava_ufw_allow 33073/tcp
crava_ufw_allow 3478/udp

crava_wait_port 80 120 tcp
crava_wait_port 3478 60 udp

mkdir -p "$(dirname "$MARKER")"
touch "$MARKER"
echo "[cravacloud] NetBird Mesh VPN Control Plane aktif di http://${PUBLIC_IP} (STUN/TURN :3478/udp)."
echo "CRAVA_INSTALL_OK {{stack}}"
