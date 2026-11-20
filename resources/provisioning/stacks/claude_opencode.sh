#!/usr/bin/env bash
# CravaCloud stack installer: claude_opencode (idempotent — safe to re-run via TAT retry).
# Claude Code + OpenCode CLI toolchain on Node.js 20. No web UI / inbound port.
# Provider API keys are configured by the customer in their shell environment
# after install (D6: no plaintext secrets travel through TAT).
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }
mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

echo "== [1/3] Base dependencies + Node.js 20 =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw git
if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

echo "== [2/3] Claude Code + OpenCode CLI =="
npm install -g @anthropic-ai/claude-code opencode-ai || true

echo "== [3/3] Firewall + finalize =="
ufw allow 22/tcp || true
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Set API key provider lewat environment shell pelanggan (bukan via TAT)."