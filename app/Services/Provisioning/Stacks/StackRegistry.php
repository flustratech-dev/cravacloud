<?php

namespace App\Services\Provisioning\Stacks;

/**
 * Registry of auto-installable stacks. Phase 3: none, docker, coolify,
 * cloudpanel. Phase 5E: the remaining 1-click catalog (panels, docker apps,
 * AI/agent stacks, CLI toolchain, outbound proxy connectors). ai_combo &
 * managed_db stay HOLD (D3) and are deliberately absent — orders for them
 * never reach the auto-provisioning path.
 *
 * Blueprint selection (blueprint §2.3, D2):
 *  - Docker-based stack on Ubuntu 24.04 → ubuntu2404_docker image.
 *  - Otherwise the plain OS blueprint from config services.tencent.blueprints.
 *  - OS without a verified blueprint → null (caller falls back to manual).
 */
class StackRegistry
{
    /** @var array<string, StackDefinition> */
    private array $definitions;

    public function __construct()
    {
        $dockerLinuxOs = ['ubuntu2204', 'ubuntu2404', 'ubuntu2604', 'debian11', 'debian12', 'debian13'];
        $proxyLinuxOs = [
            'ubuntu2004', 'ubuntu2204', 'ubuntu2404', 'ubuntu2604',
            'debian11', 'debian12', 'debian13',
            'rocky94', 'rocky10', 'centos_stream9',
        ];

        $this->definitions = [
            'none' => new StackDefinition(
                key: 'none',
                label: 'Tanpa Control Panel',
                ports: [22],
            ),
            'docker' => new StackDefinition(
                key: 'docker',
                label: 'Docker',
                ports: [22],
                script: 'docker.sh',
                needsDocker: true,
                // Script uses the Ubuntu docker apt repo — Ubuntu family only.
                supportedOs: ['ubuntu2204', 'ubuntu2404', 'ubuntu2604'],
            ),
            'coolify' => new StackDefinition(
                key: 'coolify',
                label: 'Coolify',
                ports: [22, 80, 443, 8000],
                script: 'coolify.sh',
                needsDocker: true,
                appUrlTemplate: 'http://{ip}:8000',
                // Coolify officially supports Ubuntu LTS & Debian stable.
                supportedOs: ['ubuntu2004', 'ubuntu2204', 'ubuntu2404', 'debian11', 'debian12', 'debian13'],
            ),
            'cloudpanel' => new StackDefinition(
                key: 'cloudpanel',
                label: 'CloudPanel',
                ports: [22, 80, 443, 8443],
                script: 'cloudpanel.sh',
                appUrlTemplate: 'https://{ip}:8443',
                // CloudPanel CE v2: Ubuntu 22/24 & Debian 12 only (review A3).
                supportedOs: ['ubuntu2204', 'ubuntu2404', 'debian12'],
            ),

            // ============================================================
            // Phase 5E — remaining 1-click stacks. ai_combo & managed_db
            // stay HOLD (D3) and are deliberately absent. Docker stacks use
            // get.docker.com (Ubuntu + Debian). Native panels restrict to
            // their officially supported distros. Proxy stacks (omniroute,
            // 9router) dial OUT to the central gateway and open NO extra
            // inbound port. All scripts are idempotent (marker file) and
            // never carry plaintext secrets through TAT (D6).
            // ============================================================
            'dokploy' => new StackDefinition(
                key: 'dokploy', label: 'Dokploy', ports: [22, 80, 443, 3000],
                script: 'dokploy.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:3000',
                supportedOs: $dockerLinuxOs,
            ),
            'aapanel' => new StackDefinition(
                key: 'aapanel', label: 'aaPanel', ports: [22, 80, 443, 8888, 888, 20, 21],
                script: 'aapanel.sh', appUrlTemplate: 'http://{ip}:8888',
                supportedOs: ['ubuntu2004', 'ubuntu2204', 'ubuntu2404', 'debian11', 'debian12', 'debian13', 'rocky94', 'rocky10', 'centos_stream9'],
            ),
            'cyberpanel' => new StackDefinition(
                key: 'cyberpanel', label: 'CyberPanel', ports: [22, 80, 443, 8090, 7080],
                script: 'cyberpanel.sh', appUrlTemplate: 'https://{ip}:8090',
                supportedOs: ['ubuntu2204', 'ubuntu2404', 'rocky94', 'centos_stream9'],
            ),
            'hestiacp' => new StackDefinition(
                key: 'hestiacp', label: 'HestiaCP', ports: [22, 80, 443, 8083],
                script: 'hestiacp.sh', appUrlTemplate: 'https://{ip}:8083',
                supportedOs: ['ubuntu2004', 'ubuntu2204', 'ubuntu2404', 'debian11', 'debian12', 'debian13'],
            ),
            'n8n' => new StackDefinition(
                key: 'n8n', label: 'n8n', ports: [22, 5678],
                script: 'n8n.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:5678',
                supportedOs: $dockerLinuxOs,
            ),
            'vscode_server' => new StackDefinition(
                key: 'vscode_server', label: 'Visual Studio Code Server', ports: [22, 8080],
                script: 'vscode_server.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:8080',
                supportedOs: $dockerLinuxOs,
            ),
            'gitea_forgejo' => new StackDefinition(
                key: 'gitea_forgejo', label: 'Gitea / Forgejo', ports: [22, 3000, 2222],
                script: 'gitea_forgejo.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:3000',
                supportedOs: $dockerLinuxOs,
            ),
            'uptime_kuma' => new StackDefinition(
                key: 'uptime_kuma', label: 'Uptime Kuma', ports: [22, 3001],
                script: 'uptime_kuma.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:3001',
                supportedOs: $dockerLinuxOs,
            ),
            'netdata_beszel' => new StackDefinition(
                key: 'netdata_beszel', label: 'Netdata / Beszel', ports: [22, 19999, 8090],
                script: 'netdata_beszel.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:19999',
                supportedOs: $dockerLinuxOs,
            ),
            'wordpress' => new StackDefinition(
                key: 'wordpress', label: 'WordPress', ports: [22, 80, 443],
                script: 'wordpress.sh', needsDocker: true, appUrlTemplate: 'http://{ip}',
                supportedOs: $dockerLinuxOs,
            ),
            'ghost' => new StackDefinition(
                key: 'ghost', label: 'Ghost', ports: [22, 2368],
                script: 'ghost.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:2368',
                supportedOs: $dockerLinuxOs,
            ),
            'strapi_directus' => new StackDefinition(
                key: 'strapi_directus', label: 'Strapi / Directus', ports: [22, 8055],
                script: 'strapi_directus.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:8055',
                supportedOs: $dockerLinuxOs,
            ),
            'prestashop_bagisto' => new StackDefinition(
                key: 'prestashop_bagisto', label: 'PrestaShop / Bagisto', ports: [22, 80, 443],
                script: 'prestashop_bagisto.sh', needsDocker: true, appUrlTemplate: 'http://{ip}',
                supportedOs: $dockerLinuxOs,
            ),
            'hermes_agent' => new StackDefinition(
                key: 'hermes_agent', label: 'Hermes Agent', ports: [22, 8000],
                script: 'hermes_agent.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:8000',
                supportedOs: $dockerLinuxOs,
            ),
            'openclaw' => new StackDefinition(
                key: 'openclaw', label: 'OpenClaw', ports: [22, 8080],
                script: 'openclaw.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:8080',
                supportedOs: $dockerLinuxOs,
            ),
            'agent_zero' => new StackDefinition(
                key: 'agent_zero', label: 'Agent Zero', ports: [22, 50001],
                script: 'agent_zero.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:50001',
                supportedOs: $dockerLinuxOs,
            ),
            'claude_opencode' => new StackDefinition(
                key: 'claude_opencode', label: 'Claude Code & OpenCode CLI Stack', ports: [22],
                script: 'claude_opencode.sh',
                supportedOs: $dockerLinuxOs,
            ),
            'ollama' => new StackDefinition(
                key: 'ollama', label: 'Ollama', ports: [22, 11434],
                script: 'ollama.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:11434',
                supportedOs: $dockerLinuxOs,
            ),
            'anythingllm' => new StackDefinition(
                key: 'anythingllm', label: 'AnythingLLM', ports: [22, 3001],
                script: 'anythingllm.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:3001',
                supportedOs: $dockerLinuxOs,
            ),
            'librechat' => new StackDefinition(
                key: 'librechat', label: 'LibreChat', ports: [22, 3080],
                script: 'librechat.sh', needsDocker: true, appUrlTemplate: 'http://{ip}:3080',
                supportedOs: $dockerLinuxOs,
            ),
            'dify_ollama' => new StackDefinition(
                key: 'dify_ollama', label: 'Dify AI + Ollama Private RAG Studio', ports: [22, 80, 443],
                script: 'dify_ollama.sh', needsDocker: true, appUrlTemplate: 'http://{ip}',
                supportedOs: $dockerLinuxOs,
            ),
            // Proxy stacks: traffic leaves through the central gateway
            // (43.173.51.90:20129). NO extra inbound firewall port is opened.
            'omniroute' => new StackDefinition(
                key: 'omniroute', label: 'OmniRoute', ports: [22],
                script: 'omniroute.sh',
                supportedOs: $proxyLinuxOs,
            ),
            '9router' => new StackDefinition(
                key: '9router', label: '9router', ports: [22],
                script: '9router.sh',
                supportedOs: $proxyLinuxOs,
            ),
        ];
    }

    public function get(string $key): ?StackDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * Full auto-install compatibility check (review patch A3): known stack,
     * OS allowed for that stack, AND a verified blueprint for the combo.
     * False → the order must stay in the manual flow (no false failure).
     */
    public function supportsOs(string $os, string $stack): bool
    {
        $definition = $this->get($stack);

        return $definition !== null
            && $definition->supportsOs($os)
            && $this->blueprintIdFor($os, $stack) !== null;
    }

    /**
     * Map [stackKey => list<osKey>] of verified checkout OS options compatible with each stack.
     *
     * @return array<string, array<int, string>>
     */
    public function supportedOsMap(): array
    {
        $allOs = array_keys(\App\Models\Order::checkoutOperatingSystems());
        $map = [];
        foreach (array_keys($this->definitions) as $stack) {
            $map[$stack] = array_values(array_filter(
                $allOs,
                fn (string $os) => $this->supportsOs($os, $stack)
            ));
        }

        return $map;
    }

    /** @return array<string, StackDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Verified BlueprintId for an OS + stack combination, or null when the
     * OS has no verified blueprint (D2 — such orders stay manual).
     */
    public function blueprintIdFor(string $os, string $stack): ?string
    {
        $blueprints = (array) config('services.tencent.blueprints', []);
        $definition = $this->get($stack);

        $dockerKey = $os . '_docker';
        if ($definition?->needsDocker && isset($blueprints[$dockerKey]) && $blueprints[$dockerKey] !== '') {
            return $blueprints[$dockerKey];
        }

        return $blueprints[$os] ?? null;
    }
}