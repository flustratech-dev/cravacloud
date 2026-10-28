<div class="border border-slate-200 rounded-2xl p-5 sm:p-7 bg-white shadow-xs space-y-6">
    {{-- 1. VPS Name & Akses Root --}}
    <div>
        <div class="flex items-center gap-3 mb-4">
            <span class="w-6 h-6 rounded-full bg-black text-white text-xs font-semibold flex items-center justify-center">1</span>
            <h2 class="font-semibold text-slate-900">Nama Server (Hostname)</h2>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                Hostname / VPS Name <span class="text-rose-600">*</span>
            </label>
            <input type="text" name="hostname" x-model="vpsName" required minlength="3" maxlength="63" pattern="[A-Za-z0-9][A-Za-z0-9-]*" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm font-mono-code focus:border-black focus:ring-1 focus:ring-black" autocomplete="off">
            <p class="text-[11px] text-slate-500 mt-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>Password root akan dibuat secara otomatis &amp; aman oleh retail provider, dan akan dikirimkan kepada Anda begitu instance selesai dipersiapkan.</span>
            </p>
        </div>
    </div>

    {{-- 2. Paket VPS Dipilih --}}
    <div>
        <div class="flex items-center gap-3 mb-4">
            <span class="w-6 h-6 rounded-full bg-black text-white text-xs font-semibold flex items-center justify-center">2</span>
            <h2 class="font-semibold text-slate-900">Paket VPS Dipilih</h2>
        </div>
        <div class="rounded-lg border border-black bg-slate-50 p-4 flex items-center justify-between gap-4">
            <div>
                <p class="font-semibold text-slate-900" x-text="specs[selectedSpec] ? specs[selectedSpec].name : 'Paket VPS'"></p>
                <p class="text-xs text-slate-500 mt-0.5" x-text="specs[selectedSpec] ? specs[selectedSpec].cpu + ' vCPU · ' + specs[selectedSpec].ram + ' GB RAM · ' + specs[selectedSpec].disk + ' GB SSD · ' + specs[selectedSpec].bandwidth + ' Mbps' : ''"></p>
            </div>
            <div class="text-right">
                <p class="text-lg font-bold text-slate-900 font-mono-code" x-text="formatRupiah(currentBaseMonthlyPrice)"></p>
                <p class="text-xs text-slate-500">/bulan</p>
            </div>
        </div>
        <input type="hidden" name="vps_spec_id" :value="selectedSpec">
        <input type="hidden" name="billing_cycle" value="monthly">
    </div>

    {{-- 3. Pilih Provider --}}
    <div>
        <div class="flex items-center gap-3 mb-4">
            <span class="w-6 h-6 rounded-full bg-black text-white text-xs font-semibold flex items-center justify-center">3</span>
            <h2 class="font-semibold text-slate-900">Pilih Provider</h2>
        </div>
        <div class="grid gap-3" :class="(canUseProvider('tencent') && canUseProvider('cloudeka')) ? 'sm:grid-cols-2' : 'grid-cols-1'">
            <button type="button" 
                    x-show="canUseProvider('tencent')"
                    @click="selectProvider('tencent')" 
                    :class="provider === 'tencent' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300'" 
                    class="p-4 rounded-lg border text-left transition-all flex items-center gap-3.5">
                <img src="{{ asset('images/logos/providers/tencent.svg') }}" alt="Tencent Cloud" class="w-8 h-8 object-contain shrink-0" />
                <p class="font-semibold text-sm text-slate-900">Tencent Cloud</p>
            </button>
            <button type="button" 
                    x-show="canUseProvider('cloudeka')"
                    @click="selectProvider('cloudeka')" 
                    :class="provider === 'cloudeka' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300'" 
                    class="p-4 rounded-lg border text-left transition-all flex items-center gap-3.5">
                <img src="{{ asset('images/logos/providers/cloudeka.svg') }}" alt="Cloudeka by Lintasarta" class="w-8 h-8 object-contain shrink-0" />
                <p class="font-semibold text-sm text-slate-900">Cloudeka by Lintasarta</p>
            </button>
        </div>
        <input type="hidden" name="provider" :value="provider">
    </div>

    {{-- 4. Datacenter & OS --}}
    <div>
        <div class="flex items-center gap-3 mb-4">
            <span class="w-6 h-6 rounded-full bg-black text-white text-xs font-semibold flex items-center justify-center">4</span>
            <h2 class="font-semibold text-slate-900">Datacenter &amp; OS</h2>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-2">Lokasi Datacenter</label>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label x-show="provider === 'tencent'" class="p-3.5 rounded-lg border cursor-pointer flex items-center justify-between" :class="datacenter === 'singapore' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300'">
                        <input type="radio" name="datacenter_location" value="singapore" x-model="datacenter" class="sr-only">
                        <span class="text-sm font-semibold text-slate-900">Singapore</span>
                    </label>
                    <label class="p-3.5 rounded-lg border cursor-pointer flex items-center justify-between" :class="datacenter === 'indonesia' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300'">
                        <input type="radio" name="datacenter_location" value="indonesia" x-model="datacenter" class="sr-only">
                        <span class="text-sm font-semibold text-slate-900">Indonesia (Jakarta)</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Operating System</label>
                <select name="os" x-model="os" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 text-sm bg-white focus:border-black focus:ring-1 focus:ring-black">
                    <template x-for="[value, label] in Object.entries(operatingSystems[provider])" :key="value">
                        <option :value="value" x-text="label"></option>
                    </template>
                </select>
            </div>
        </div>
    </div>

    {{-- 5. Pilih Stack Server --}}
    <div>
        <div class="flex items-center gap-3 mb-4">
            <span class="w-6 h-6 rounded-full bg-black text-white text-xs font-semibold flex items-center justify-center">5</span>
            <h2 class="font-semibold text-slate-900">Pilih Stack Server</h2>
        </div>
        <div class="grid sm:grid-cols-3 gap-3 mb-4">
            <button type="button" @click="selectStack('none')" :class="stackType === 'none' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300'" class="p-3.5 rounded-lg border text-center transition-all">
                <p class="font-semibold text-sm text-slate-900">Tanpa Control Panel</p>
            </button>
            <button type="button" @click="selectStack('control_panel')" :class="stackType === 'control_panel' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300'" class="p-3.5 rounded-lg border text-center transition-all">
                <p class="font-semibold text-sm text-slate-900">Control Panel</p>
            </button>
            <button type="button" @click="selectStack('application')" :class="stackType === 'application' ? 'border-black bg-slate-50 ring-1 ring-black' : 'border-slate-200 hover:border-slate-300'" class="p-3.5 rounded-lg border text-center transition-all">
                <p class="font-semibold text-sm text-slate-900">Aplikasi</p>
            </button>
        </div>
        <div x-show="stackType === 'control_panel'" class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            @foreach(['coolify' => 'Coolify', 'dokploy' => 'Dokploy', 'aapanel' => 'aaPanel', 'cloudpanel' => 'CloudPanel', 'docker' => 'Docker', 'cyberpanel' => 'CyberPanel', 'hestiacp' => 'HestiaCP'] as $value => $label)
                <button type="button" @click="controlPanel = '{{ $value }}'" 
                        :class="controlPanel === '{{ $value }}' ? 'border-black bg-slate-50 ring-1 ring-black shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white'" 
                        class="p-3 rounded-lg border text-left flex items-center gap-3 transition-all min-h-[52px]">
                    <img src="{{ asset('images/logos/panels/' . $value . '.svg') }}" alt="{{ $label }}" class="w-6 h-6 object-contain shrink-0" />
                    <span class="text-xs sm:text-sm font-semibold text-slate-900 leading-tight">{{ $label }}</span>
                </button>
            @endforeach
        </div>
        <div x-show="stackType === 'application'" class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            @foreach(['hermes_agent' => 'Hermes Agent', 'openclaw' => 'OpenClaw', 'omniroute' => 'OmniRoute', '9router' => '9router', 'agent_zero' => 'Agent Zero', 'n8n' => 'n8n', 'ollama' => 'Ollama', 'anythingllm' => 'AnythingLLM', 'librechat' => 'LibreChat', 'vscode_server' => 'Visual Studio Code Server', 'gitea_forgejo' => 'Gitea / Forgejo', 'uptime_kuma' => 'Uptime Kuma', 'netdata_beszel' => 'Netdata / Beszel', 'wordpress' => 'WordPress', 'ghost' => 'Ghost', 'strapi_directus' => 'Strapi / Directus', 'prestashop_bagisto' => 'PrestaShop / Bagisto'] as $value => $label)
                <button type="button" @click="controlPanel = '{{ $value }}'" 
                        :class="controlPanel === '{{ $value }}' ? 'border-black bg-slate-50 ring-1 ring-black shadow-xs' : 'border-slate-200 hover:border-slate-300 bg-white'" 
                        class="p-3 rounded-lg border text-left flex items-center gap-3 transition-all min-h-[52px]">
                    <img src="{{ asset('images/logos/apps/' . $value . '.svg') }}" alt="{{ $label }}" class="w-6 h-6 object-contain shrink-0" />
                    <span class="text-xs sm:text-sm font-semibold text-slate-900 leading-tight">{{ $label }}</span>
                </button>
            @endforeach
        </div>
        <input type="hidden" name="control_panel" :value="controlPanel">
    </div>
</div>
