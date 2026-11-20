#!/usr/bin/env bash
# Shared CravaCloud provisioning helpers injected by StackScriptBuilder.
# Safe under `set -euo pipefail`. Zero-secret stdout policy (D6).

CRAVA_OS_ID=""
CRAVA_OS_LIKE=""
CRAVA_PKG_MGR=""
_CRAVA_APT_UPDATED=0

crava_detect_os() {
    if [ -r /etc/os-release ]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        CRAVA_OS_ID="$(printf '%s' "${ID:-unknown}" | tr '[:upper:]' '[:lower:]')"
        CRAVA_OS_LIKE="$(printf '%s' "${ID_LIKE:-}" | tr '[:upper:]' '[:lower:]')"
    else
        CRAVA_OS_ID="unknown"
        CRAVA_OS_LIKE=""
    fi

    if command -v apt-get >/dev/null 2>&1; then
        CRAVA_PKG_MGR="apt"
    elif command -v dnf >/dev/null 2>&1; then
        CRAVA_PKG_MGR="dnf"
    elif command -v yum >/dev/null 2>&1; then
        CRAVA_PKG_MGR="yum"
    else
        CRAVA_PKG_MGR=""
    fi
}

crava_require_os() {
    crava_detect_os
    local allowed
    for allowed in "$@"; do
        if [ "$CRAVA_OS_ID" = "$allowed" ]; then
            return 0
        fi
        case " $CRAVA_OS_LIKE " in
            *" $allowed "*) return 0 ;;
        esac
    done
    echo "Unsupported OS [$CRAVA_OS_ID] (like: $CRAVA_OS_LIKE). Required one of: $*" >&2
    return 1
}

crava_wait_pkg_lock() {
    local timeout="${1:-120}"
    local elapsed=0

    while [ "$elapsed" -lt "$timeout" ]; do
        local locked=0
        if command -v fuser >/dev/null 2>&1; then
            if fuser /var/lib/dpkg/lock /var/lib/dpkg/lock-frontend /var/lib/apt/lists/lock /var/cache/apt/archives/lock >/dev/null 2>&1; then
                locked=1
            fi
        elif command -v pgrep >/dev/null 2>&1; then
            if pgrep -x "apt-get|apt|dpkg|unattended-upgr|dnf|yum" >/dev/null 2>&1; then
                locked=1
            fi
        fi

        if [ "$locked" -eq 0 ]; then
            return 0
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done

    echo "Timed out waiting for package manager lock after ${timeout}s" >&2
    return 1
}

crava_pkg_install() {
    crava_detect_os
    crava_wait_pkg_lock 120

    if [ "$CRAVA_PKG_MGR" = "apt" ]; then
        if [ "$_CRAVA_APT_UPDATED" -eq 0 ]; then
            apt-get update -y
            _CRAVA_APT_UPDATED=1
        fi
        apt-get install -y --no-install-recommends "$@"
    elif [ "$CRAVA_PKG_MGR" = "dnf" ]; then
        dnf install -y "$@"
    elif [ "$CRAVA_PKG_MGR" = "yum" ]; then
        yum install -y "$@"
    else
        echo "No supported package manager found (apt/dnf/yum)." >&2
        return 1
    fi
}

crava_ensure_swap() {
    local size_mb="${1:-2048}"
    if [ -n "$(swapon --show --noheadings 2>/dev/null || true)" ]; then
        return 0
    fi

    if [ -f /swapfile ]; then
        chmod 600 /swapfile
        swapon /swapfile >/dev/null 2>&1 || true
        return 0
    fi

    if command -v fallocate >/dev/null 2>&1; then
        fallocate -l "${size_mb}M" /swapfile || dd if=/dev/zero of=/swapfile bs=1M count="$size_mb" status=none
    else
        dd if=/dev/zero of=/swapfile bs=1M count="$size_mb" status=none
    fi
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    if [ -w /etc/fstab ] && ! grep -q '^/swapfile ' /etc/fstab; then
        printf '/swapfile none swap sw 0 0\n' >> /etc/fstab
    fi
}

crava_ensure_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        crava_wait_pkg_lock 120
        curl -fsSL https://get.docker.com | sh
    fi
    systemctl enable --now docker

    local elapsed=0
    while [ "$elapsed" -lt 30 ]; do
        if docker info >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done

    echo "Docker daemon failed to become ready within 30s" >&2
    journalctl -u docker -n 40 --no-pager >&2 || true
    return 1
}

crava_wait_port() {
    local port="$1"
    local timeout="${2:-120}"
    local proto="${3:-tcp}"
    local elapsed=0

    while [ "$elapsed" -lt "$timeout" ]; do
        if command -v ss >/dev/null 2>&1; then
            if [ "$proto" = "udp" ]; then
                if ss -uln | grep -qE ":${port}([[:space:]]|$)"; then
                    return 0
                fi
            else
                if ss -tln | grep -qE ":${port}([[:space:]]|$)"; then
                    return 0
                fi
            fi
        elif command -v netstat >/dev/null 2>&1; then
            if netstat -tuln | grep -qE ":${port}([[:space:]]|$)"; then
                return 0
            fi
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done

    echo "Readiness check failed: ${proto} port ${port} did not listen within ${timeout}s" >&2
    ss -tuln >&2 || true
    return 1
}

crava_wait_http() {
    local url="$1"
    local timeout="${2:-120}"
    local elapsed=0

    while [ "$elapsed" -lt "$timeout" ]; do
        local code
        code="$(curl -sk -o /dev/null -w '%{http_code}' --max-time 5 "$url" || true)"
        case "$code" in
            2??|3??|401|403) return 0 ;;
        esac
        sleep 3
        elapsed=$((elapsed + 3))
    done

    echo "Readiness check failed: HTTP endpoint ${url} unreachable within ${timeout}s" >&2
    return 1
}

crava_wait_container() {
    local name="$1"
    local timeout="${2:-120}"
    local elapsed=0

    while [ "$elapsed" -lt "$timeout" ]; do
        local state
        state="$(docker inspect --format '{{.State.Status}}|{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$name" 2>/dev/null || true)"
        case "$state" in
            "running|healthy"|"running|none")
                return 0
                ;;
        esac
        sleep 3
        elapsed=$((elapsed + 3))
    done

    echo "Container [${name}] failed readiness check within ${timeout}s" >&2
    docker logs --tail 40 "$name" >&2 || true
    return 1
}

crava_wait_service() {
    local unit="$1"
    local timeout="${2:-120}"
    local elapsed=0

    while [ "$elapsed" -lt "$timeout" ]; do
        if systemctl is-active --quiet "$unit"; then
            return 0
        fi
        sleep 2
        elapsed=$((elapsed + 2))
    done

    echo "Systemd unit [${unit}] failed readiness check within ${timeout}s" >&2
    journalctl -u "$unit" -n 40 --no-pager >&2 || true
    return 1
}

crava_ufw_allow() {
    if command -v ufw >/dev/null 2>&1; then
        local spec
        for spec in "$@"; do
            ufw allow "$spec" >/dev/null 2>&1 || true
        done
    fi
}
