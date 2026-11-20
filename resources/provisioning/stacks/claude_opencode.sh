#!/usr/bin/env bash
# CravaCloud stack installer: claude_opencode (idempotent, _common.sh hardened).
# Claude Code + OpenCode CLI toolchain on Node.js 20. No web UI / inbound port.
# Provider API keys are configured by the customer in their shell environment (D6).
set -euo pipefail
# {{common}}

MARKER=/var/lib/cravacloud/.installed-{{stack}}
if [ -f "$MARKER" ] && command -v claude >/dev/null 2>&1 && command -v opencode >/dev/null 2>&1; then
    echo "CRAVA_INSTALL_OK {{stack}}"
    exit 0
fi

mkdir -p /var/lib/cravacloud
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive

crava_require_os ubuntu debian

echo "== [1/4] Dependencies, Node.js 20 & 2 GB Swap =="
crava_pkg_install ca-certificates curl gnupg ufw git
crava_ensure_swap 2048
if ! command -v node >/dev/null 2>&1; then
    crava_wait_pkg_lock 120
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

echo "== [2/4] Claude Code + OpenCode CLI =="
npm install -g @anthropic-ai/claude-code opencode-ai

echo "== [3/4] Readiness Gate =="
if ! command -v claude >/dev/null 2>&1 || ! command -v opencode >/dev/null 2>&1; then
    echo "CLI binary check failed after npm install" >&2
    exit 1
fi

echo "== [4/4] Firewall & Finalize =="
crava_ufw_allow 22/tcp
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
echo "Set API key provider lewat environment shell pelanggan (bukan via TAT)."
