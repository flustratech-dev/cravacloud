<div class="border border-hairline bg-white p-5 sm:p-8 space-y-8">
    <div class="border-b border-hairline pb-4 flex items-center justify-between gap-4">
        <div>
            <h2 class="font-display text-xl sm:text-2xl font-bold text-ink tracking-tight">Konfigurasi VPS Anda</h2>
        </div>
        <span class="w-8 h-8 bg-electric text-white font-mono text-xs font-bold flex items-center justify-center shrink-0">01</span>
    </div>

    {{-- Step 1: VPS Hostname --}}
    <div>
        <div class="flex items-center gap-2.5 mb-2.5">
            <span class="w-5 h-5 bg-ink text-cream font-mono text-[10px] font-bold flex items-center justify-center shrink-0">1</span>
            <label for="checkout-vps-name" class="font-mono text-xs font-bold uppercase tracking-wider text-ink">Hostname / Nama VPS</label>
        </div>
        <input id="checkout-vps-name"
               type="text"
               x-model="vpsName"
               placeholder="contoh: prod-api-jakarta-01"
               class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm bg-white border border-hairline text-ink font-mono focus:outline-none focus:border-electric transition-colors">
        <p class="font-mono text-[11px] text-muted mt-1.5">Gunakan huruf, angka, dan tanda hubung (`-`). Maksimal 63 karakter.</p>
    </div>

    {{-- Step 2: Datacenter & OS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <div class="flex items-center gap-2.5 mb-2.5">
                <span class="w-5 h-5 bg-ink text-cream font-mono text-[10px] font-bold flex items-center justify-center shrink-0">2A</span>
                <label class="font-mono text-xs font-bold uppercase tracking-wider text-ink">Lokasi Datacenter</label>
            </div>
            <div class="grid grid-cols-2 gap-px bg-hairline border border-hairline">
                <label class="p-3.5 text-center cursor-pointer transition-colors relative"
                       :class="datacenter === 'singapore' ? 'bg-cream border-2 border-electric -m-px z-10' : 'bg-white hover:bg-creamDark/40'">
                    <input type="radio" name="datacenter" value="singapore" x-model="datacenter" class="sr-only">
                    <div class="font-display font-bold text-sm text-ink">Singapore</div>
                    <div class="font-mono text-[10px] uppercase text-muted mt-0.5">SIN-1 Edge</div>
                </label>
                <label class="p-3.5 text-center cursor-pointer transition-colors relative"
                       :class="datacenter === 'indonesia' ? 'bg-cream border-2 border-electric -m-px z-10' : 'bg-white hover:bg-creamDark/40'">
                    <input type="radio" name="datacenter" value="indonesia" x-model="datacenter" class="sr-only">
                    <div class="font-display font-bold text-sm text-ink">Indonesia</div>
                    <div class="font-mono text-[10px] uppercase text-muted mt-0.5">JKT DCI-ID</div>
                </label>
            </div>
        </div>

        <div>
            <div class="flex items-center gap-2.5 mb-2.5">
                <span class="w-5 h-5 bg-ink text-cream font-mono text-[10px] font-bold flex items-center justify-center shrink-0">2B</span>
                <label for="checkout-os" class="font-mono text-xs font-bold uppercase tracking-wider text-ink">Sistem Operasi (OS)</label>
            </div>
            <select id="checkout-os"
                    x-model="os"
                    class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm bg-white border border-hairline text-ink font-mono focus:outline-none focus:border-electric transition-colors">
                <template x-for="[key, label] in Object.entries(compatibleOperatingSystems || {})" :key="key">
                    <option :value="key" x-text="label"></option>
                </template>
            </select>
            <p x-show="!isDirectCheckout && Object.keys(compatibleOperatingSystems || {}).length < Object.keys(operatingSystems || {}).length"
               class="font-mono text-[11px] text-muted mt-1.5">
                Menampilkan OS kompatibel dengan stack <span class="text-ink font-semibold" x-text="controlPanelLabel"></span>.
            </p>
        </div>
    </div>

    {{-- Step 3: Stack Selection --}}
    <div>
        <div class="flex items-center gap-2.5 mb-2.5">
            <span class="w-5 h-5 bg-ink text-cream font-mono text-[10px] font-bold flex items-center justify-center shrink-0">3</span>
            <label class="font-mono text-xs font-bold uppercase tracking-wider text-ink">1-Click Stack / Control Panel</label>
        </div>

        {{-- Stack Type Tabs --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-px bg-hairline border border-hairline mb-5">
            <button type="button" @click="selectStack('none')"
                    :class="stackType === 'none' ? 'bg-ink text-cream' : 'bg-white text-ink hover:bg-creamDark/50'"
                    class="py-3 px-4 font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer text-center">
                OS Bersih (Fresh)
            </button>
            <button type="button" @click="selectStack('control_panel')"
                    :class="stackType === 'control_panel' ? 'bg-ink text-cream' : 'bg-white text-ink hover:bg-creamDark/50'"
                    class="py-3 px-4 font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer text-center">
                Control Panel
            </button>
            <button type="button" @click="selectStack('app')"
                    :class="stackType === 'app' ? 'bg-ink text-cream' : 'bg-white text-ink hover:bg-creamDark/50'"
                    class="py-3 px-4 font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer text-center">
                AI &amp; Tools App
            </button>
        </div>

        {{-- Control Panel Selection Grid --}}
        <div x-show="stackType === 'control_panel'" x-transition class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @php
                $panels = [
                    'coolify' => ['name' => 'Coolify', 'desc' => 'Self-hosted PaaS', 'logo' => 'coolify.svg'],
                    'dokploy' => ['name' => 'Dokploy', 'desc' => 'Docker PaaS', 'logo' => 'dokploy.svg'],
                    'aapanel' => ['name' => 'aaPanel', 'desc' => 'Lightweight web panel', 'logo' => 'aapanel.png'],
                    'cloudpanel' => ['name' => 'CloudPanel', 'desc' => 'PHP, Node, Python UI', 'logo' => 'cloudpanel.svg'],
                    'docker' => ['name' => 'Docker Engine', 'desc' => 'Container & Compose', 'logo' => 'docker.svg'],
                    'cyberpanel' => ['name' => 'CyberPanel', 'desc' => 'OpenLiteSpeed panel', 'logo' => 'cyberpanel.svg'],
                    'hestiacp' => ['name' => 'HestiaCP', 'desc' => 'Lightweight web panel', 'logo' => 'hestiacp.svg'],
                ];
            @endphp
            @foreach($panels as $key => $info)
                <label class="p-3.5 cursor-pointer transition-colors relative flex flex-col justify-between bg-white"
                       :class="controlPanel === '{{ $key }}' ? 'border-2 border-electric bg-cream' : 'border border-hairline hover:border-ink/40'">
                    <input type="radio" name="control_panel" value="{{ $key }}" x-model="controlPanel" class="sr-only">
                    <div class="w-8 h-8 mb-2.5 flex items-center justify-center">
                        <img src="{{ asset('images/logos/panels/' . $info['logo']) }}" alt="{{ $info['name'] }}" class="max-h-full max-w-full object-contain">
                    </div>
                    <div>
                        <div class="font-display font-bold text-sm text-ink">{{ $info['name'] }}</div>
                        <div class="text-[11px] text-muted leading-tight mt-0.5">{{ $info['desc'] }}</div>
                    </div>
                </label>
            @endforeach
        </div>

        {{-- App Selection Grid --}}
        <div x-show="stackType === 'app'" x-transition class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @php
                $apps = [
                    'hermes_agent' => ['name' => 'Hermes Agent', 'desc' => 'Autonomous AI agent', 'logo' => 'hermes_agent.svg'],
                    'openclaw' => ['name' => 'OpenClaw', 'desc' => 'AI Agent gateway', 'logo' => 'openclaw.svg'],
                    'omniroute' => ['name' => 'OmniRoute', 'desc' => 'Smart LLM gateway', 'logo' => 'omniroute.svg'],
                    '9router' => ['name' => '9router', 'desc' => 'Lightweight AI router', 'logo' => '9router.svg'],
                    'agent_zero' => ['name' => 'Agent Zero', 'desc' => 'Dynamic AI framework', 'logo' => 'agent_zero.svg'],
                    'n8n' => ['name' => 'n8n Automation', 'desc' => 'Workflow automation', 'logo' => 'n8n.svg'],
                    'ollama' => ['name' => 'Ollama', 'desc' => 'Run local LLMs', 'logo' => 'ollama.svg'],
                    'dify_ollama' => ['name' => 'Dify + Ollama', 'desc' => 'AI RAG orchestration', 'logo' => 'dify_ollama.svg'],
                    'anythingllm' => ['name' => 'AnythingLLM', 'desc' => 'Private RAG chat', 'logo' => 'anythingllm.svg'],
                    'librechat' => ['name' => 'LibreChat', 'desc' => 'Open-source AI chat', 'logo' => 'librechat.svg'],
                    'vscode_server' => ['name' => 'VS Code Server', 'desc' => 'Web-based IDE', 'logo' => 'vscode_server.svg'],
                    'gitea_forgejo' => ['name' => 'Gitea / Forgejo', 'desc' => 'Self-hosted Git', 'logo' => 'gitea_forgejo.svg'],
                    'uptime_kuma' => ['name' => 'Uptime Kuma', 'desc' => 'Monitoring tool', 'logo' => 'uptime_kuma.svg'],
                    'netdata_beszel' => ['name' => 'Netdata / Beszel', 'desc' => 'Server metrics', 'logo' => 'netdata_beszel.svg'],
                    'wordpress' => ['name' => 'WordPress', 'desc' => 'Popular CMS + Nginx', 'logo' => 'wordpress.svg'],
                    'ghost' => ['name' => 'Ghost CMS', 'desc' => 'Modern publishing', 'logo' => 'ghost.svg'],
                    'strapi_directus' => ['name' => 'Strapi / Directus', 'desc' => 'Headless CMS', 'logo' => 'strapi_directus.svg'],
                    'prestashop_bagisto' => ['name' => 'PrestaShop / Bagisto', 'desc' => 'E-Commerce platform', 'logo' => 'prestashop_bagisto.svg'],
                ];
            @endphp
            @foreach($apps as $key => $info)
                <label class="p-3.5 cursor-pointer transition-colors relative flex flex-col justify-between bg-white"
                       :class="controlPanel === '{{ $key }}' ? 'border-2 border-electric bg-cream' : 'border border-hairline hover:border-ink/40'">
                    <input type="radio" name="control_panel" value="{{ $key }}" x-model="controlPanel" class="sr-only">
                    <div class="w-8 h-8 mb-2.5 flex items-center justify-center">
                        <img src="{{ asset('images/logos/apps/' . $info['logo']) }}" alt="{{ $info['name'] }}" class="max-h-full max-w-full object-contain">
                    </div>
                    <div>
                        <div class="font-display font-bold text-sm text-ink">{{ $info['name'] }}</div>
                        <div class="text-[11px] text-muted leading-tight mt-0.5">{{ $info['desc'] }}</div>
                    </div>
                </label>
            @endforeach
        </div>

        {{-- Fresh OS confirmation --}}
        <div x-show="stackType === 'none'" x-transition class="p-4 bg-creamDark/60 border border-hairline text-xs text-ink/80 font-mono">
            Server dikirim dengan OS bersih pilihan Anda tanpa software tambahan. Kredensial akses SSH penuh tersedia langsung di dashboard.
        </div>
    </div>
</div>
