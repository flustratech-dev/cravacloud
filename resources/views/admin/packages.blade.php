@extends('layouts.admin', ['title' => 'Manajemen Paket VPS', 'headerTitle' => 'Katalog Paket VPS Cloud', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    specs: {{ Js::from($specs->keyBy('id')) }},
    activeTab: (new URLSearchParams(window.location.search)).get('tab') || 'vps',
    createModalOpen: false,
    editModalOpen: false,
    deleteModalOpen: false,
    createCategory: 'vps',
    createModalTitle: 'Tambah Paket Cloud VPS Baru',
    createDefaultStack: 'none',
    openCreate(cat = 'vps') {
        this.createCategory = cat;
        if (cat === 'managed_db') {
            this.createModalTitle = 'Tambah Paket Managed Database VPS';
            this.createDefaultStack = 'managed_database';
        } else if (cat === 'ai_combo') {
            this.createModalTitle = 'Tambah Paket AI Agent & Workstation';
            this.createDefaultStack = 'ollama';
        } else {
            this.createModalTitle = 'Tambah Paket Cloud VPS KVM';
            this.createDefaultStack = 'none';
        }
        this.createModalOpen = true;
    },
    editData: {
        id: '',
        name: '',
        category: 'vps',
        tagline: '',
        badge: '',
        target_audience: '',
        solution: '',
        default_stack: 'none',
        allowed_providers: ['tencent'],
        default_provider: 'tencent',
        features: '',
        cpu: 1,
        ram: 1,
        disk: 20,
        bandwidth: 1000,
        cost_price: 0,
        sell_price: 0,
        payment_url: '',
        is_active: true,
        is_renewable: true,
        replacement_spec_id: ''
    },
    deleteData: {
        id: '',
        name: ''
    },
    openEdit(spec) {
        let feat = '';
        if (Array.isArray(spec.features)) {
            feat = spec.features.join('\n');
        } else if (typeof spec.features === 'string') {
            try {
                const parsed = JSON.parse(spec.features);
                feat = Array.isArray(parsed) ? parsed.join('\n') : spec.features;
            } catch(e) {
                feat = spec.features;
            }
        }

        this.editData = {
            id: spec.id,
            name: spec.name,
            category: spec.category || 'vps',
            tagline: spec.tagline || '',
            badge: spec.badge || '',
            target_audience: spec.target_audience || '',
            solution: spec.solution || '',
            default_stack: spec.default_stack || 'none',
            allowed_providers: Array.isArray(spec.allowed_providers) && spec.allowed_providers.length > 0 ? spec.allowed_providers : ['tencent'],
            default_provider: spec.default_provider || 'tencent',
            features: feat,
            cpu: spec.cpu,
            ram: spec.ram,
            disk: spec.disk,
            bandwidth: spec.bandwidth,
            cost_price: spec.cost_price,
            sell_price: spec.sell_price,
            payment_url: spec.payment_url || '',
            is_active: Boolean(spec.is_active),
            is_renewable: spec.is_renewable !== undefined ? Boolean(spec.is_renewable) : true,
            replacement_spec_id: spec.replacement_spec_id || ''
        };
        this.editModalOpen = true;
    },
    openDelete(spec) {
        this.deleteData = {
            id: spec.id,
            name: spec.name
        };
        this.deleteModalOpen = true;
    }
}">

    <!-- Stats Summary Row -->
    @php
        $totalSpecs = $specs->count();
        $activeSpecs = $specs->where('is_active', true)->count();
        $inactiveSpecs = $totalSpecs - $activeSpecs;
        $avgMargin = $totalSpecs > 0 ? $specs->avg(fn($s) => $s->sell_price - $s->cost_price) : 0;

        $vpsSpecs = $specs->filter(fn($s) => !$s->isDatabasePackage() && !$s->isAiPackage());
        $dbSpecs = $specs->filter(fn($s) => $s->isDatabasePackage());
        $aiSpecs = $specs->filter(fn($s) => $s->isAiPackage());

        $vpsCount = $vpsSpecs->count();
        $dbCount = $dbSpecs->count();
        $aiCount = $aiSpecs->count();
        $allCount = $totalSpecs;
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Total Paket VPS</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $totalSpecs }}</span>
                <span class="text-xs text-slate-500">Katalog Tersedia</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Paket Aktif Dijual</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $activeSpecs }}</span>
                <span class="text-xs text-slate-500 font-medium">Tampil di Checkout</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Paket Nonaktif</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-extrabold text-slate-600 font-mono-code">{{ $inactiveSpecs }}</span>
                <span class="text-xs text-slate-400">Diarsipkan</span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Rata-rata Margin</span>
            <div class="flex items-baseline justify-between">
                <span class="text-xl font-extrabold text-slate-900 font-mono-code">Rp {{ number_format($avgMargin, 0, ',', '.') }}</span>
                <span class="text-xs text-slate-500">Per Bulan/Unit</span>
            </div>
        </div>
    </div>

    <!-- Main Table Container with Category Tabs -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden shadow-xs">
        
        <!-- Category Tabs Navigation Bar (Monokrom / Satu Warna) -->
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/70 px-4 pt-3 overflow-x-auto">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <!-- Tab: VPS -->
                <button @click="activeTab = 'vps'"
                        type="button"
                        :class="activeTab === 'vps' ? 'border-black text-slate-900 font-bold bg-white shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-100/60'"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 border-b-2 rounded-t-lg text-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                    <span>Cloud VPS KVM</span>
                    <span :class="activeTab === 'vps' ? 'bg-black text-white' : 'bg-slate-200 text-slate-700'"
                          class="px-1.5 py-0.2 rounded-full text-[10px] font-bold">{{ $vpsCount }}</span>
                </button>

                <!-- Tab: Managed DB -->
                <button @click="activeTab = 'managed_db'"
                        type="button"
                        :class="activeTab === 'managed_db' ? 'border-black text-slate-900 font-bold bg-white shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-100/60'"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 border-b-2 rounded-t-lg text-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                    <span>Managed Database</span>
                    <span :class="activeTab === 'managed_db' ? 'bg-black text-white' : 'bg-slate-200 text-slate-700'"
                          class="px-1.5 py-0.2 rounded-full text-[10px] font-bold">{{ $dbCount }}</span>
                </button>

                <!-- Tab: AI Agent -->
                <button @click="activeTab = 'ai_combo'"
                        type="button"
                        :class="activeTab === 'ai_combo' ? 'border-black text-slate-900 font-bold bg-white shadow-xs' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-100/60'"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 border-b-2 rounded-t-lg text-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>AI Agent & Workstation</span>
                    <span :class="activeTab === 'ai_combo' ? 'bg-black text-white' : 'bg-slate-200 text-slate-700'"
                          class="px-1.5 py-0.2 rounded-full text-[10px] font-bold">{{ $aiCount }}</span>
                </button>
            </div>
        </div>

        <!-- Tab Header Banner & Action Buttons -->
        <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 bg-white">
            <div>
                <!-- Banner Tab: VPS -->
                <div x-show="activeTab === 'vps'">
                    <h3 class="font-bold text-slate-900 text-base">Katalog Spesifikasi Cloud VPS ({{ $vpsCount }} Paket)</h3>
                    <p class="text-xs text-slate-500">Virtual server performa tinggi untuk website, web control panel, dan aplikasi umum.</p>
                </div>
                <!-- Banner Tab: Managed DB -->
                <div x-show="activeTab === 'managed_db'" style="display: none;">
                    <h3 class="font-bold text-slate-900 text-base">Katalog Managed Database VPS ({{ $dbCount }} Paket)</h3>
                    <p class="text-xs text-slate-500">Paket database siap pakai (MySQL, PostgreSQL, Redis) dengan CloudBeaver GUI & optimasi engine.</p>
                </div>
                <!-- Banner Tab: AI Agent -->
                <div x-show="activeTab === 'ai_combo'" style="display: none;">
                    <h3 class="font-bold text-slate-900 text-base">Katalog AI Agent & Workstation ({{ $aiCount }} Paket)</h3>
                    <p class="text-xs text-slate-500">Paket komputasi mandiri untuk Coding Agent, Ollama LLM, Hermes Autonomous Hub, dan Private RAG.</p>
                </div>
            </div>

            <!-- Action Button per Tab (Monokrom Hitam) -->
            <div class="flex items-center gap-2 shrink-0">
                <!-- When activeTab is 'vps' -->
                <div x-show="activeTab === 'vps'">
                    <button @click="openCreate('vps')" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Paket Cloud VPS</span>
                    </button>
                </div>

                <!-- When activeTab is 'managed_db' -->
                <div x-show="activeTab === 'managed_db'" style="display: none;">
                    <button @click="openCreate('managed_db')" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah Managed Database</span>
                    </button>
                </div>

                <!-- When activeTab is 'ai_combo' -->
                <div x-show="activeTab === 'ai_combo'" style="display: none;">
                    <button @click="openCreate('ai_combo')" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors shadow-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Tambah AI Agent / Combo</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Nama Paket</th>
                        <th class="px-5 py-3.5">Spesifikasi Hardware</th>
                        <th class="px-5 py-3.5">Provider Cloud</th>
                        <th class="px-5 py-3.5">Harga Pokok (Modal)</th>
                        <th class="px-5 py-3.5">Harga Jual</th>
                        <th class="px-5 py-3.5">Margin Profit</th>
                        <th class="px-5 py-3.5 text-center">Total Order</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($specs as $spec)
                        @php
                            $specCat = $spec->isDatabasePackage() ? 'managed_db' : ($spec->isAiPackage() ? 'ai_combo' : 'vps');
                            $isCore = in_array(strtolower($spec->name), array_map('strtolower', $corePlanNames), true) || in_array((int)$spec->id, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13], true);
                            $margin = $spec->sell_price - $spec->cost_price;
                            $marginPercent = $spec->sell_price > 0 ? round(($margin / $spec->sell_price) * 100, 1) : 0;
                            $provider = $spec->default_provider;
                        @endphp
                        <tr x-show="activeTab === '{{ $specCat }}'"
                            class="hover:bg-slate-50/80 transition-colors">
                            <!-- Nama Paket -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-900 text-sm block">{{ $spec->name }}</span>
                                    @if($spec->category === 'managed_db' || $spec->isDatabasePackage())
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                            Managed DB
                                        </span>
                                    @elseif($spec->isAiPackage())
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                            AI Combo
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                            Cloud VPS
                                        </span>
                                    @endif
                                    @if($isCore)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200" title="Paket sistem inti (dilindungi dari penghapusan)">
                                            <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Inti
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            Kustom
                                        </span>
                                    @endif
                                </div>
                                @if($spec->tagline)
                                    <span class="text-[11px] text-slate-500 italic block">{{ $spec->tagline }}</span>
                                @endif
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[11px] text-slate-400 font-mono-code">ID: #SPEC-{{ str_pad($spec->id, 3, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            </td>

                            <!-- Spesifikasi Hardware -->
                            <td class="px-5 py-3.5 text-slate-700">
                                <div class="font-mono-code font-semibold text-slate-900">
                                    {{ $spec->cpu }} vCPU &bull; {{ $spec->ram }} GB RAM
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    {{ $spec->disk }} GB NVMe SSD &bull; {{ $spec->bandwidth >= 1000 ? ($spec->bandwidth / 1000) . ' TB' : $spec->bandwidth . ' GB' }} BW
                                </div>
                            </td>

                            <!-- Provider Cloud -->
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($spec->allowed_providers as $p)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ $p === 'cloudeka' ? 'Cloudeka' : ($p === 'tencent' ? 'Tencent' : ucfirst($p)) }}
                                        </span>
                                    @endforeach
                                </div>
                                <span class="text-[10px] text-slate-400 block mt-0.5">Default: {{ ucfirst($spec->default_provider) }}</span>
                            </td>

                            <!-- Harga Pokok -->
                            <td class="px-5 py-3.5 font-mono-code text-slate-600">
                                Rp {{ number_format($spec->cost_price, 0, ',', '.') }}
                            </td>

                            <!-- Harga Jual -->
                            <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">
                                Rp {{ number_format($spec->sell_price, 0, ',', '.') }}
                            </td>

                            <!-- Margin -->
                            <td class="px-5 py-3.5">
                                @if($margin < 0)
                                    <span class="font-mono-code font-bold text-slate-900 block">
                                        -Rp {{ number_format(abs($margin), 0, ',', '.') }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-900 text-white mt-0.5" title="Harga jual di bawah modal supplier!">
                                        Rugi / Defisit
                                    </span>
                                @else
                                    <span class="font-mono-code font-semibold text-slate-900 block">
                                        +Rp {{ number_format($margin, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 font-mono-code">({{ $marginPercent }}%)</span>
                                @endif
                            </td>

                            <!-- Total Order -->
                            <td class="px-5 py-3.5 text-center font-mono-code font-semibold text-slate-700">
                                {{ $spec->orders_count }}
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3.5 text-center">
                                @if($spec->is_active && $spec->is_renewable)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-900 border border-slate-200" title="Aktif: Tersedia untuk checkout baru & perpanjangan">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-900"></span>
                                        Aktif
                                    </span>
                                @elseif(!$spec->is_active && $spec->is_renewable)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-300" title="Legacy / Stop Selling: Tutup order baru, hanya perpanjangan pelanggan lama">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                        Legacy
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-400 border border-slate-200" title="Discontinued / End-of-Life: Paket dihentikan permanen">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                        Discontinued
                                    </span>
                                    @if($spec->replacementSpec)
                                        <span class="text-[10px] text-slate-600 font-medium block mt-0.5 truncate max-w-[120px]" title="Fallback ke {{ $spec->replacementSpec->name }}">
                                            &rarr; {{ $spec->replacementSpec->name }}
                                        </span>
                                    @else
                                        <span class="text-[10px] text-slate-400 block mt-0.5">
                                            Tanpa Pengganti
                                        </span>
                                    @endif
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Edit Button -->
                                    <button @click="openEdit(specs[{{ $spec->id }}])"
                                            type="button"
                                            class="p-1.5 rounded text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors"
                                            title="Edit Spesifikasi & Harga">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>

                                    <!-- Delete Button (Conditional) -->
                                    @if($isCore)
                                        <button type="button"
                                                disabled
                                                class="p-1.5 rounded text-slate-300 cursor-not-allowed"
                                                title="Paket inti dilindungi sistem dan tidak dapat dihapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        </button>
                                    @elseif($spec->orders_count > 0)
                                        <button type="button"
                                                disabled
                                                class="p-1.5 rounded text-slate-300 cursor-not-allowed"
                                                title="Paket memiliki {{ $spec->orders_count }} riwayat pesanan, tidak dapat dihapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @else
                                        <button @click="openDelete(specs[{{ $spec->id }}])"
                                                type="button"
                                                class="p-1.5 rounded text-slate-700 hover:text-black hover:bg-slate-100 transition-colors"
                                                title="Hapus Paket Kustom">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-10 text-center text-slate-400">
                                Belum ada spesifikasi paket VPS di database.
                            </td>
                        </tr>
                    @endforelse

                    <!-- Empty state khusus jika kategori aktif kosong -->
                    <tr x-show="activeTab === 'vps' && {{ $vpsCount }} === 0" style="display: none;">
                        <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                            <p class="font-medium text-slate-600">Belum ada paket Cloud VPS KVM yang dibuat.</p>
                            <button @click="openCreate('vps')" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-black text-white text-xs font-bold hover:bg-neutral-800 transition-colors">
                                + Tambah Paket Cloud VPS
                            </button>
                        </td>
                    </tr>
                    <tr x-show="activeTab === 'managed_db' && {{ $dbCount }} === 0" style="display: none;">
                        <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                            <p class="font-medium text-slate-600">Belum ada paket Managed Database.</p>
                            <button @click="openCreate('managed_db')" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-black text-white text-xs font-bold hover:bg-neutral-800 transition-colors">
                                + Tambah Managed Database
                            </button>
                        </td>
                    </tr>
                    <tr x-show="activeTab === 'ai_combo' && {{ $aiCount }} === 0" style="display: none;">
                        <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                            <p class="font-medium text-slate-600">Belum ada paket AI Agent / Workstation.</p>
                            <button @click="openCreate('ai_combo')" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-black text-white text-xs font-bold hover:bg-neutral-800 transition-colors">
                                + Tambah AI Agent / Combo
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah Paket Baru -->
    <div x-show="createModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6"
         style="display: none;"
         @keydown.escape.window="createModalOpen = false">

        <div @click.away="createModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">
            
            <!-- Sticky Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white">
                <div>
                    <h3 class="font-bold text-slate-900 text-base" x-text="createModalTitle">Tambah Paket Baru</h3>
                    <p class="text-xs text-slate-500">Konfigurasi spesifikasi hardware, provider cloud, dan harga paket</p>
                </div>
                <button @click="createModalOpen = false" type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Form with scrollable body -->
            <form action="/admin/packages" method="POST" class="flex flex-col flex-1 min-h-0">
                @csrf

                <div class="p-6 overflow-y-auto flex-1 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Kolom Kiri: Informasi Paket & Provider -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 pb-1 border-b border-slate-100">Informasi & Provider</h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nama Paket</label>
                                    <input type="text" name="name" required placeholder="Contoh: Starter Node"
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Kategori Layanan</label>
                                    <select name="category" x-model="createCategory"
                                            @change="createCategory === 'managed_db' ? (createDefaultStack = 'managed_database', createModalTitle = 'Tambah Paket Managed Database VPS') : (createCategory === 'ai_combo' ? (createDefaultStack = 'ollama', createModalTitle = 'Tambah Paket AI Agent & Workstation') : (createDefaultStack = 'none', createModalTitle = 'Tambah Paket Cloud VPS KVM'))"
                                            class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                        <option value="vps">Cloud VPS KVM</option>
                                        <option value="ai_combo">CravaCloud AI Combo</option>
                                        <option value="managed_db">Managed Database VPS</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Tagline Singkat</label>
                                    <input type="text" name="tagline" placeholder="Contoh: Cocok untuk website..."
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Badge Produk</label>
                                    <input type="text" name="badge" placeholder="Contoh: Terpopuler, Best Value"
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Default Stack / Panel</label>
                                    <select name="default_stack" x-model="createDefaultStack" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                        <option value="none">Tanpa Stack (OS Bersih)</option>
                                        <option value="coolify">Coolify</option>
                                        <option value="dokploy">Dokploy</option>
                                        <option value="aapanel">aaPanel</option>
                                        <option value="cloudpanel">CloudPanel</option>
                                        <option value="docker">Docker</option>
                                        <option value="managed_database">Managed Database</option>
                                        <option value="vscode_server">VS Code Server</option>
                                        <option value="ollama">Ollama AI</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Target Pengguna</label>
                                    <input type="text" name="target_audience" placeholder="Developer, Mahasiswa..."
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">Cloud Provider Tersedia</label>
                                <div class="flex flex-wrap gap-4 text-xs">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="allowed_providers[]" value="tencent" checked
                                               class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                                        <span class="font-medium text-slate-700">Tencent Cloud (SG)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="allowed_providers[]" value="cloudeka"
                                               class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                                        <span class="font-medium text-slate-700">Cloudeka (JKT)</span>
                                    </label>
                                </div>
                                <div class="pt-2 border-t border-slate-200 flex items-center gap-4 text-xs">
                                    <span class="text-slate-500 font-medium">Provider Utama:</span>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="default_provider" value="tencent" checked
                                               class="border-slate-300 text-black focus:ring-black">
                                        <span class="text-slate-800">Tencent</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="default_provider" value="cloudeka"
                                               class="border-slate-300 text-black focus:ring-black">
                                        <span class="text-slate-800">Cloudeka</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Spesifikasi Hardware & Harga -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 pb-1 border-b border-slate-100">Spesifikasi & Harga</h4>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">CPU (vCPU)</label>
                                    <input type="number" name="cpu" min="1" max="128" value="2" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">RAM (GB)</label>
                                    <input type="number" name="ram" min="1" max="1024" value="4" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Disk NVMe (GB)</label>
                                    <input type="number" name="disk" min="5" max="10000" value="60" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Bandwidth (GB)</label>
                                    <input type="number" name="bandwidth" min="1" max="100000" value="1000" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Harga Modal (Rp)</label>
                                    <input type="number" name="cost_price" min="0" value="70000" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Harga Jual (Rp)</label>
                                    <input type="number" name="sell_price" min="0" value="120000" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Fitur Utama (Satu per baris)</label>
                                <textarea name="features" rows="2" placeholder="2 Core vCPU&#10;4 GB RAM DDR4&#10;60 GB NVMe SSD&#10;Root Access Penuh"
                                          class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black font-mono-code"></textarea>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Sticky Footer with 3-Tier Lifecycle Controls -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 space-y-3 rounded-b-lg">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" id="create_is_active" value="1" checked
                                   class="w-4 h-4 mt-0.5 rounded border-slate-300 text-black focus:ring-black">
                            <div>
                                <span class="text-xs font-semibold text-slate-900 block">Buka Checkout Baru</span>
                                <span class="text-[11px] text-slate-500 block">Paket tampil di katalog & dapat dipesan pelanggan baru.</span>
                            </div>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_renewable" id="create_is_renewable" value="1" checked
                                   class="w-4 h-4 mt-0.5 rounded border-slate-300 text-black focus:ring-black">
                            <div>
                                <span class="text-xs font-semibold text-slate-900 block">Izinkan Perpanjangan</span>
                                <span class="text-[11px] text-slate-500 block">Pelanggan lama yang memiliki server ini dapat perpanjang.</span>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Paket Pengganti (Fallback jika Dihentikan/EOL)</label>
                        <select name="replacement_spec_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black bg-white">
                            <option value="">-- Tanpa Paket Pengganti (Wajib Hubungi Support jika EOL) --</option>
                            @foreach($specs as $otherSpec)
                                <option value="{{ $otherSpec->id }}">{{ $otherSpec->name }} (Rp {{ number_format($otherSpec->sell_price, 0, ',', '.') }}/bln)</option>
                            @endforeach
                        </select>
                        <span class="text-[11px] text-slate-500 mt-1 block">Jika perpanjangan dinonaktifkan (EOL), pelanggan otomatis dialihkan ke skema paket pengganti ini.</span>
                    </div>

                    <div class="flex items-center gap-2 w-full justify-end pt-2 border-t border-slate-200">
                        <button @click="createModalOpen = false" type="button"
                                class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-100 transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 rounded-lg bg-black text-white text-xs font-bold hover:bg-neutral-800 transition-colors shadow-xs">
                            Simpan Paket
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Paket -->
    <div x-show="editModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6"
         style="display: none;"
         @keydown.escape.window="editModalOpen = false">

        <div @click.away="editModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">
            
            <!-- Sticky Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Edit Paket VPS</h3>
                    <p class="text-xs text-slate-500">Perbarui spesifikasi atau harga paket <span class="font-semibold text-slate-800" x-text="editData.name"></span></p>
                </div>
                <button @click="editModalOpen = false" type="button" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Form with scrollable body -->
            <form :action="'/admin/packages/' + editData.id" method="POST" class="flex flex-col flex-1 min-h-0">
                @csrf
                @method('PUT')

                <div class="p-6 overflow-y-auto flex-1 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Kolom Kiri: Informasi Paket & Provider -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 pb-1 border-b border-slate-100">Informasi & Provider</h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nama Paket</label>
                                    <input type="text" name="name" x-model="editData.name" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Kategori Layanan</label>
                                    <select name="category" x-model="editData.category" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                        <option value="vps">Cloud VPS KVM</option>
                                        <option value="ai_combo">CravaCloud AI Combo</option>
                                        <option value="managed_db">Managed Database VPS</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Tagline Singkat</label>
                                    <input type="text" name="tagline" x-model="editData.tagline" placeholder="Contoh: Pilihan ideal website bisnis..."
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Badge Produk</label>
                                    <input type="text" name="badge" x-model="editData.badge" placeholder="Contoh: Terpopuler, Scale Up..."
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Default Stack / Panel</label>
                                    <select name="default_stack" x-model="editData.default_stack" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                        <option value="none">Tanpa Stack (OS Bersih)</option>
                                        <option value="coolify">Coolify</option>
                                        <option value="dokploy">Dokploy</option>
                                        <option value="aapanel">aaPanel</option>
                                        <option value="cloudpanel">CloudPanel</option>
                                        <option value="docker">Docker</option>
                                        <option value="managed_database">Managed Database</option>
                                        <option value="vscode_server">VS Code Server</option>
                                        <option value="ollama">Ollama AI</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Target Pengguna</label>
                                    <input type="text" name="target_audience" x-model="editData.target_audience" placeholder="Developer, Mahasiswa, Startup..."
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 space-y-3">
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider">Cloud Provider Tersedia</label>
                                <div class="flex flex-wrap gap-4 text-xs">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="allowed_providers[]" value="tencent"
                                               :checked="editData.allowed_providers && editData.allowed_providers.includes('tencent')"
                                               class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                                        <span class="font-medium text-slate-700">Tencent Cloud (SG)</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="allowed_providers[]" value="cloudeka"
                                               :checked="editData.allowed_providers && editData.allowed_providers.includes('cloudeka')"
                                               class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                                        <span class="font-medium text-slate-700">Cloudeka (JKT)</span>
                                    </label>
                                </div>
                                <div class="pt-2 border-t border-slate-200 flex items-center gap-4 text-xs">
                                    <span class="text-slate-500 font-medium">Provider Utama:</span>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="default_provider" value="tencent"
                                               x-model="editData.default_provider"
                                               class="border-slate-300 text-black focus:ring-black">
                                        <span class="text-slate-800">Tencent</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="default_provider" value="cloudeka"
                                               x-model="editData.default_provider"
                                               class="border-slate-300 text-black focus:ring-black">
                                        <span class="text-slate-800">Cloudeka</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Kolom Kanan: Spesifikasi Hardware & Harga -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 pb-1 border-b border-slate-100">Spesifikasi & Harga</h4>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">CPU (vCPU)</label>
                                    <input type="number" name="cpu" x-model="editData.cpu" min="1" max="128" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">RAM (GB)</label>
                                    <input type="number" name="ram" x-model="editData.ram" min="1" max="1024" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Disk NVMe (GB)</label>
                                    <input type="number" name="disk" x-model="editData.disk" min="5" max="10000" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Bandwidth (GB)</label>
                                    <input type="number" name="bandwidth" x-model="editData.bandwidth" min="1" max="100000" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Harga Modal (Rp)</label>
                                    <input type="number" name="cost_price" x-model="editData.cost_price" min="0" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Harga Jual (Rp)</label>
                                    <input type="number" name="sell_price" x-model="editData.sell_price" min="0" required
                                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Fitur Utama (Satu per baris)</label>
                                <textarea name="features" x-model="editData.features" rows="2" placeholder="2 Core vCPU&#10;4 GB RAM DDR4&#10;60 GB NVMe SSD&#10;Root Access Penuh"
                                          class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black font-mono-code"></textarea>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Sticky Footer with 3-Tier Lifecycle Controls -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 space-y-3 rounded-b-lg">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1" :checked="editData.is_active"
                                   class="w-4 h-4 mt-0.5 rounded border-slate-300 text-black focus:ring-black">
                            <div>
                                <span class="text-xs font-semibold text-slate-900 block">Buka Checkout Baru</span>
                                <span class="text-[11px] text-slate-500 block">Tampil di katalog & dapat dipesan pelanggan baru.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_renewable" id="edit_is_renewable" value="1" :checked="editData.is_renewable"
                                   class="w-4 h-4 mt-0.5 rounded border-slate-300 text-black focus:ring-black">
                            <div>
                                <span class="text-xs font-semibold text-slate-900 block">Izinkan Perpanjangan</span>
                                <span class="text-[11px] text-slate-500 block">Pelanggan lama yang memiliki server ini dapat perpanjang.</span>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Paket Pengganti (Fallback jika Dihentikan/EOL)</label>
                        <select name="replacement_spec_id" x-model="editData.replacement_spec_id"
                                class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black bg-white">
                            <option value="">-- Tanpa Paket Pengganti (Wajib Hubungi Support jika EOL) --</option>
                            @foreach($specs as $otherSpec)
                                <option value="{{ $otherSpec->id }}">{{ $otherSpec->name }} (Rp {{ number_format($otherSpec->sell_price, 0, ',', '.') }}/bln)</option>
                            @endforeach
                        </select>
                        <span class="text-[11px] text-slate-500 mt-1 block">Jika perpanjangan dinonaktifkan (Mode EOL), pelanggan otomatis dialihkan ke skema paket pengganti ini.</span>
                    </div>

                    <div class="flex items-center gap-2 w-full justify-end pt-2 border-t border-slate-200">
                        <button @click="editModalOpen = false" type="button"
                                class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-100 transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 rounded-lg bg-black text-white text-xs font-bold hover:bg-neutral-800 transition-colors shadow-xs">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="deleteModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="deleteModalOpen = false">

        <div @click.away="deleteModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-md w-full p-6 text-center">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 mx-auto flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>

            <h3 class="font-bold text-slate-900 text-base mb-2">Hapus Paket VPS?</h3>
            <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                Anda yakin ingin menghapus paket <span class="font-bold text-slate-900" x-text="deleteData.name"></span>? Tindakan ini permanen dan tidak dapat dibatalkan.
            </p>

            <form :action="'/admin/packages/' + deleteData.id" method="POST" class="flex items-center justify-center gap-3">
                @csrf
                @method('DELETE')

                <button @click="deleteModalOpen = false" type="button"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition-colors">
                    Batalkan
                </button>
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition-colors">
                    Ya, Hapus Paket
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
