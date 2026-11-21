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
            <p x-show="Object.keys(compatibleOperatingSystems || {}).length < Object.keys(operatingSystems || {}).length"
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
                1-Click Applications
            </button>
        </div>

        {{-- Search & Category Filters (Shared across Control Panel & 1-Click Applications) --}}
        <div x-show="stackType === 'control_panel' || stackType === 'app'" x-transition class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2.5">
                <div class="relative flex-1">
                    <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="text"
                           x-model="stackSearch"
                           placeholder="Cari stack (misal: Coolify, Portainer, cPanel, Supabase, WireGuard, FiveM...)"
                           aria-label="Cari Control Panel atau Aplikasi"
                           class="w-full rounded-none pl-9 pr-9 py-2.5 text-sm bg-white border border-hairline text-ink font-mono focus:outline-none focus:border-electric transition-colors">
                    <button type="button"
                            x-show="stackSearch !== ''"
                            @click="stackSearch = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-mono text-muted hover:text-ink px-1 cursor-pointer"
                            aria-label="Hapus pencarian">✕</button>
                </div>
                <div class="font-mono text-[11px] text-muted shrink-0">
                    Menampilkan <strong class="text-ink" x-text="filteredStacks.length"></strong> dari <span x-text="groupStacks.length"></span> stack
                </div>
            </div>

            {{-- Category Filter Chips --}}
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button"
                        @click="stackCategory = 'all'"
                        :class="stackCategory === 'all' ? 'bg-ink text-cream border-ink' : 'bg-white text-ink border-hairline hover:border-ink'"
                        class="px-3 py-1 border font-mono text-[11px] font-bold uppercase tracking-wide transition-colors cursor-pointer">
                    Semua
                </button>
                <template x-if="hasPaidPanelsInGroup">
                    <button type="button"
                            @click="stackCategory = '__free'"
                            :class="stackCategory === '__free' ? 'bg-ink text-cream border-ink' : 'bg-white text-ink border-hairline hover:border-ink'"
                            class="px-3 py-1 border font-mono text-[11px] font-bold uppercase tracking-wide transition-colors cursor-pointer">
                        Gratis / Open-Source
                    </button>
                </template>
                <template x-if="hasPaidPanelsInGroup">
                    <button type="button"
                            @click="stackCategory = '__paid'"
                            :class="stackCategory === '__paid' ? 'bg-amber-500 text-ink border-amber-600' : 'bg-white text-amber-900 border-amber-400 hover:bg-amber-50'"
                            class="px-3 py-1 border font-mono text-[11px] font-bold uppercase tracking-wide transition-colors cursor-pointer">
                        Berbayar · BYOL
                    </button>
                </template>
                <template x-for="cat in availableStackCategories" :key="cat">
                    <button type="button"
                            @click="stackCategory = cat"
                            :class="stackCategory === cat ? 'bg-electric text-white border-electric' : 'bg-white text-ink border-hairline hover:border-ink'"
                            class="px-3 py-1 border font-mono text-[11px] font-bold uppercase tracking-wide transition-colors cursor-pointer"
                            x-text="cat"></button>
                </template>
            </div>

            {{-- Stack Selection Grid (Never disabled — @click="handleStackClick(stack)") --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <template x-for="stack in filteredStacks" :key="stack.key">
                    <button type="button"
                            @click="handleStackClick(stack)"
                            :class="controlPanel === stack.key
                                ? 'border-2 border-electric bg-cream shadow-xs'
                                : (stack.hardBlockOnRam && planRamGb > 0 && planRamGb < stack.minRamGb
                                    ? 'border border-red-300 bg-white/80 hover:border-red-500'
                                    : 'border border-hairline bg-white hover:border-ink/60')"
                            class="p-3.5 text-left cursor-pointer transition-colors relative flex flex-col justify-between gap-3">
                        <div class="flex items-start justify-between gap-2 w-full">
                            <div class="w-10 h-10 rounded-xl bg-white/95 border border-black/10 p-1.5 shrink-0 flex items-center justify-center relative">
                                <img :src="stack.logoUrl"
                                     :alt="stack.label"
                                     loading="lazy"
                                     class="w-full h-full object-contain">
                                <template x-if="stack.secondaryLogoUrl">
                                    <img :src="stack.secondaryLogoUrl"
                                         :alt="stack.label"
                                         loading="lazy"
                                         class="w-4 h-4 rounded-full bg-white border border-black/15 p-0.5 object-contain absolute -bottom-1 -right-1 shadow-sm">
                                </template>
                            </div>
                            <span class="font-mono text-[10px] font-semibold px-1.5 py-0.5 bg-creamDark/70 border border-hairline text-muted uppercase shrink-0"
                                  x-text="'Min. ' + stack.minRamGb + ' GB'"></span>
                        </div>

                        <div class="w-full min-w-0">
                            <div class="font-display font-bold text-sm text-ink truncate" x-text="stack.label"></div>
                            <div class="text-[11px] text-muted leading-snug mt-0.5 line-clamp-2" x-text="stack.description"></div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1 w-full">
                            <template x-if="stack.requiresLicense">
                                <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-amber-100 text-amber-950 border border-amber-400">
                                    Berbayar · BYOL
                                </span>
                            </template>
                            <template x-if="stack.hardBlockOnRam && planRamGb > 0 && planRamGb < stack.minRamGb">
                                <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-red-100 text-red-900 border border-red-400"
                                      x-text="'Butuh Min. ' + stack.minRamGb + ' GB RAM'"></span>
                            </template>
                            <template x-if="!stack.requiresLicense && (stack.warningType === 'activation' || stack.warningType === 'network')">
                                <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-sky-100 text-sky-900 border border-sky-300"
                                      x-text="stack.warningType === 'network' ? 'Info Akses' : 'Info Aktivasi'"></span>
                            </template>
                            <template x-if="Array.isArray(stack.udpPorts) && stack.udpPorts.length > 0">
                                <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-violet-100 text-violet-900 border border-violet-300">
                                    TCP+UDP
                                </span>
                            </template>
                            <template x-if="stack.needsWarningConfirmation && acknowledgedWarnings[stack.key]">
                                <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-emerald-100 text-emerald-900 border border-emerald-400">
                                    ✓ Info Disetujui
                                </span>
                            </template>
                        </div>
                    </button>
                </template>
            </div>

            {{-- Empty Search Result State --}}
            <div x-show="filteredStacks.length === 0" x-cloak class="p-6 border border-dashed border-hairline bg-creamDark/30 text-center space-y-2">
                <p class="font-mono text-xs text-muted">Tidak ada stack yang cocok dengan filter atau kata kunci Anda.</p>
                <button type="button"
                        @click="stackSearch = ''; stackCategory = 'all'"
                        class="px-3 py-1.5 border border-ink bg-white hover:bg-creamDark font-mono text-[11px] font-bold uppercase text-ink cursor-pointer">
                    Reset Filter
                </button>
            </div>
        </div>

        {{-- Fresh OS confirmation --}}
        <div x-show="stackType === 'none'" x-transition class="p-4 bg-creamDark/60 border border-hairline text-xs text-ink/80 font-mono">
            Server dikirim dengan OS bersih pilihan Anda tanpa software tambahan. Kredensial akses SSH penuh tersedia langsung di dashboard.
        </div>
    </div>
</div>
