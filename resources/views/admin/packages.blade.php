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
        features: '',
        cpu: 1,
        ram: 1,
        disk: 20,
        bandwidth: 1000,
        cost_price: 0,
        sell_price: 0,
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
            features: feat,
            cpu: spec.cpu,
            ram: spec.ram,
            disk: spec.disk,
            bandwidth: spec.bandwidth,
            cost_price: spec.cost_price,
            sell_price: spec.sell_price,
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
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
    @endphp

    <!-- Stats Summary Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Total Paket VPS</span>
            <div class="flex items-baseline justify-between">
                <span class="font-display text-2xl font-bold text-ink">{{ $totalSpecs }}</span>
                <span class="font-mono text-[11px] text-muted">Katalog Tersedia</span>
            </div>
        </div>

        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Paket Aktif Dijual</span>
            <div class="flex items-baseline justify-between">
                <span class="font-display text-2xl font-bold text-electric">{{ $activeSpecs }}</span>
                <span class="font-mono text-[11px] text-muted">Tampil di Checkout</span>
            </div>
        </div>

        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Paket Nonaktif</span>
            <div class="flex items-baseline justify-between">
                <span class="font-display text-2xl font-bold text-ink">{{ $inactiveSpecs }}</span>
                <span class="font-mono text-[11px] text-muted">Diarsipkan</span>
            </div>
        </div>

        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Rata-rata Margin</span>
            <div class="flex items-baseline justify-between">
                <span class="font-display text-xl font-bold text-ink">Rp {{ number_format($avgMargin, 0, ',', '.') }}</span>
                <span class="font-mono text-[11px] text-muted">Per Bulan/Unit</span>
            </div>
        </div>
    </div>

    <!-- Main Table Container with Category Tabs -->
    <div class="bg-white border border-hairline overflow-hidden">

        <!-- Category Tabs Navigation Bar -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-hairline bg-creamDark/40 px-4 pt-2">
            <div class="flex flex-wrap items-center gap-1">
                <button @click="activeTab = 'vps'"
                        type="button"
                        :class="activeTab === 'vps' ? 'border-electric text-ink font-bold bg-white border-t border-x border-hairline' : 'border-transparent text-muted hover:text-ink'"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 border-b-2 font-mono text-xs uppercase tracking-wider transition-colors cursor-pointer">
                    <span>Cloud VPS KVM</span>
                    <span :class="activeTab === 'vps' ? 'bg-electric text-white' : 'bg-creamDark text-ink border border-hairline'"
                          class="px-1.5 py-0.5 text-[10px] font-bold">{{ $vpsCount }}</span>
                </button>

                <button @click="activeTab = 'managed_db'"
                        type="button"
                        :class="activeTab === 'managed_db' ? 'border-electric text-ink font-bold bg-white border-t border-x border-hairline' : 'border-transparent text-muted hover:text-ink'"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 border-b-2 font-mono text-xs uppercase tracking-wider transition-colors cursor-pointer">
                    <span>Managed Database</span>
                    <span :class="activeTab === 'managed_db' ? 'bg-electric text-white' : 'bg-creamDark text-ink border border-hairline'"
                          class="px-1.5 py-0.5 text-[10px] font-bold">{{ $dbCount }}</span>
                </button>

                <button @click="activeTab = 'ai_combo'"
                        type="button"
                        :class="activeTab === 'ai_combo' ? 'border-electric text-ink font-bold bg-white border-t border-x border-hairline' : 'border-transparent text-muted hover:text-ink'"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 border-b-2 font-mono text-xs uppercase tracking-wider transition-colors cursor-pointer">
                    <span>AI Agent &amp; Workstation</span>
                    <span :class="activeTab === 'ai_combo' ? 'bg-electric text-white' : 'bg-creamDark text-ink border border-hairline'"
                          class="px-1.5 py-0.5 text-[10px] font-bold">{{ $aiCount }}</span>
                </button>
            </div>
        </div>

        <!-- Tab Header Banner & Action Buttons -->
        <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-hairline bg-white">
            <div>
                <div x-show="activeTab === 'vps'">
                    <h3 class="font-display font-bold text-ink text-base">Katalog Spesifikasi Cloud VPS ({{ $vpsCount }} Paket)</h3>
                    <p class="text-xs text-muted mt-0.5">Virtual server performa tinggi untuk website, web control panel, dan aplikasi umum.</p>
                </div>
                <div x-show="activeTab === 'managed_db'" style="display: none;">
                    <h3 class="font-display font-bold text-ink text-base">Katalog Managed Database VPS ({{ $dbCount }} Paket)</h3>
                    <p class="text-xs text-muted mt-0.5">Paket database siap pakai (MySQL, PostgreSQL, Redis) dengan CloudBeaver GUI &amp; optimasi engine.</p>
                </div>
                <div x-show="activeTab === 'ai_combo'" style="display: none;">
                    <h3 class="font-display font-bold text-ink text-base">Katalog AI Agent &amp; Workstation ({{ $aiCount }} Paket)</h3>
                    <p class="text-xs text-muted mt-0.5">Paket komputasi mandiri untuk Coding Agent, Ollama LLM, Hermes Autonomous Hub, dan Private RAG.</p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <div x-show="activeTab === 'vps'">
                    <button @click="openCreate('vps')" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        <span>+ Tambah Paket Cloud VPS</span>
                    </button>
                </div>

                <div x-show="activeTab === 'managed_db'" style="display: none;">
                    <button @click="openCreate('managed_db')" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        <span>+ Tambah Managed Database</span>
                    </button>
                </div>

                <div x-show="activeTab === 'ai_combo'" style="display: none;">
                    <button @click="openCreate('ai_combo')" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        <span>+ Tambah AI Agent / Combo</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Nama Paket</th>
                        <th class="px-5 py-3.5">Spesifikasi Hardware</th>
                        <th class="px-5 py-3.5">Harga Pokok (Modal)</th>
                        <th class="px-5 py-3.5">Harga Jual</th>
                        <th class="px-5 py-3.5">Margin Profit</th>
                        <th class="px-5 py-3.5 text-center">Total Order</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($specs as $spec)
                        @php
                            $specCat = $spec->isDatabasePackage() ? 'managed_db' : ($spec->isAiPackage() ? 'ai_combo' : 'vps');
                            $isCore = in_array(strtolower($spec->name), array_map('strtolower', $corePlanNames), true) || in_array((int)$spec->id, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13], true);
                            $margin = $spec->sell_price - $spec->cost_price;
                            $marginPercent = $spec->sell_price > 0 ? round(($margin / $spec->sell_price) * 100, 1) : 0;
                        @endphp
                        <tr x-show="activeTab === '{{ $specCat }}'"
                            class="hover:bg-creamDark/30 transition-colors">
                            <!-- Nama Paket -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-display font-bold text-ink text-sm block">{{ $spec->name }}</span>
                                    @if($spec->category === 'managed_db' || $spec->isDatabasePackage())
                                        <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">Managed DB</span>
                                    @elseif($spec->isAiPackage())
                                        <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">AI Combo</span>
                                    @else
                                        <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-white text-ink border border-hairline">Cloud VPS</span>
                                    @endif
                                    @if($isCore)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-ink" title="Paket sistem inti (dilindungi dari penghapusan)">
                                            Inti
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-white text-muted border border-hairline">
                                            Kustom
                                        </span>
                                    @endif
                                </div>
                                @if($spec->tagline)
                                    <span class="text-[11px] text-muted italic block">{{ $spec->tagline }}</span>
                                @endif
                                <div class="mt-0.5">
                                    <span class="text-[11px] text-muted font-mono">ID: #SPEC-{{ str_pad($spec->id, 3, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            </td>

                            <!-- Spesifikasi Hardware -->
                            <td class="px-5 py-3.5 text-ink">
                                <div class="font-mono font-bold text-ink">
                                    {{ $spec->cpu }} vCPU &middot; {{ $spec->ram }} GB RAM
                                </div>
                                <div class="font-mono text-[11px] text-muted">
                                    {{ $spec->disk }} GB NVMe SSD &middot; {{ $spec->bandwidth >= 1000 ? ($spec->bandwidth / 1000) . ' TB' : $spec->bandwidth . ' GB' }} BW
                                </div>
                            </td>

                            <!-- Harga Pokok -->
                            <td class="px-5 py-3.5 font-mono text-muted">
                                Rp {{ number_format($spec->cost_price, 0, ',', '.') }}
                            </td>

                            <!-- Harga Jual -->
                            <td class="px-5 py-3.5 font-mono font-bold text-ink">
                                Rp {{ number_format($spec->sell_price, 0, ',', '.') }}
                            </td>

                            <!-- Margin -->
                            <td class="px-5 py-3.5">
                                @if($margin < 0)
                                    <span class="font-mono font-bold text-ink block">
                                        -Rp {{ number_format(abs($margin), 0, ',', '.') }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream mt-0.5" title="Harga jual di bawah modal supplier!">
                                        Rugi / Defisit
                                    </span>
                                @else
                                    <span class="font-mono font-bold text-electric block">
                                        +Rp {{ number_format($margin, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[10px] text-muted font-mono">({{ $marginPercent }}%)</span>
                                @endif
                            </td>

                            <!-- Total Order -->
                            <td class="px-5 py-3.5 text-center font-mono font-bold text-ink">
                                {{ $spec->orders_count }}
                            </td>

                            <!-- Status -->
                            <td class="px-5 py-3.5 text-center">
                                @if($spec->is_active && $spec->is_renewable)
                                    <span class="inline-flex items-center px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white" title="Aktif: Tersedia untuk checkout baru & perpanjangan">
                                        Aktif
                                    </span>
                                @elseif(!$spec->is_active && $spec->is_renewable)
                                    <span class="inline-flex items-center px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-ink" title="Legacy / Stop Selling: Tutup order baru, hanya perpanjangan pelanggan lama">
                                        Legacy
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-muted border border-hairline" title="Discontinued / End-of-Life: Paket dihentikan permanen">
                                        Discontinued
                                    </span>
                                    @if($spec->replacementSpec)
                                        <span class="font-mono text-[10px] text-ink font-medium block mt-0.5 truncate max-w-[120px]" title="Fallback ke {{ $spec->replacementSpec->name }}">
                                            &rarr; {{ $spec->replacementSpec->name }}
                                        </span>
                                    @else
                                        <span class="font-mono text-[10px] text-muted block mt-0.5">
                                            Tanpa Pengganti
                                        </span>
                                    @endif
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button @click="openEdit(specs[{{ $spec->id }}])"
                                            type="button"
                                            class="p-1.5 border border-hairline hover:border-ink text-ink bg-white transition-colors cursor-pointer"
                                            title="Edit Spesifikasi & Harga">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>

                                    @if($isCore)
                                        <button type="button"
                                                disabled
                                                class="p-1.5 border border-hairline text-muted/40 cursor-not-allowed"
                                                title="Paket inti dilindungi sistem dan tidak dapat dihapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        </button>
                                    @elseif($spec->orders_count > 0)
                                        <button type="button"
                                                disabled
                                                class="p-1.5 border border-hairline text-muted/40 cursor-not-allowed"
                                                title="Paket memiliki {{ $spec->orders_count }} riwayat pesanan, tidak dapat dihapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @else
                                        <button @click="openDelete(specs[{{ $spec->id }}])"
                                                type="button"
                                                class="p-1.5 border border-hairline hover:bg-ink hover:text-white text-ink bg-white transition-colors cursor-pointer"
                                                title="Hapus Paket Kustom">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada spesifikasi paket VPS di database.
                            </td>
                        </tr>
                    @endforelse

                    <!-- Empty state khusus jika kategori aktif kosong -->
                    <tr x-show="activeTab === 'vps' && {{ $vpsCount }} === 0" style="display: none;">
                        <td colspan="8" class="px-5 py-12 text-center text-muted">
                            <p class="font-display font-bold text-ink">Belum ada paket Cloud VPS KVM yang dibuat.</p>
                            <button @click="openCreate('vps')" class="mt-2 inline-flex items-center gap-1.5 px-3.5 py-2 bg-electric text-white font-mono text-xs font-bold uppercase hover:bg-electricHover transition-colors cursor-pointer">
                                + Tambah Paket Cloud VPS
                            </button>
                        </td>
                    </tr>
                    <tr x-show="activeTab === 'managed_db' && {{ $dbCount }} === 0" style="display: none;">
                        <td colspan="8" class="px-5 py-12 text-center text-muted">
                            <p class="font-display font-bold text-ink">Belum ada paket Managed Database.</p>
                            <button @click="openCreate('managed_db')" class="mt-2 inline-flex items-center gap-1.5 px-3.5 py-2 bg-electric text-white font-mono text-xs font-bold uppercase hover:bg-electricHover transition-colors cursor-pointer">
                                + Tambah Managed Database
                            </button>
                        </td>
                    </tr>
                    <tr x-show="activeTab === 'ai_combo' && {{ $aiCount }} === 0" style="display: none;">
                        <td colspan="8" class="px-5 py-12 text-center text-muted">
                            <p class="font-display font-bold text-ink">Belum ada paket AI Agent / Workstation.</p>
                            <button @click="openCreate('ai_combo')" class="mt-2 inline-flex items-center gap-1.5 px-3.5 py-2 bg-electric text-white font-mono text-xs font-bold uppercase hover:bg-electricHover transition-colors cursor-pointer">
                                + Tambah AI Agent / Combo
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah Paket Baru -->
    <div x-show="createModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4 sm:p-6"
         style="display: none;"
         @keydown.escape.window="createModalOpen = false">

        <div @click.away="createModalOpen = false"
             class="bg-white border border-ink shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">

            <div class="px-6 py-4 border-b border-hairline flex items-center justify-between shrink-0 bg-creamDark/40">
                <div>
                    <h3 class="font-display font-bold text-ink text-base" x-text="createModalTitle">Tambah Paket Baru</h3>
                    <p class="text-xs text-muted">Konfigurasi spesifikasi hardware, layanan stack, dan harga paket</p>
                </div>
                <button @click="createModalOpen = false" type="button" class="text-muted hover:text-ink p-1.5 font-mono text-base cursor-pointer">&times;</button>
            </div>

            <form action="/admin/packages" method="POST" class="flex flex-col flex-1 min-h-0">
                @csrf

                <div class="p-6 overflow-y-auto flex-1 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h4 class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted pb-1 border-b border-hairline">Informasi &amp; Layanan</h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Nama Paket</label>
                                    <input type="text" name="name" required placeholder="Contoh: Starter Node" class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Kategori Layanan</label>
                                    <select name="category" x-model="createCategory"
                                            @change="createCategory === 'managed_db' ? (createDefaultStack = 'managed_database', createModalTitle = 'Tambah Paket Managed Database VPS') : (createCategory === 'ai_combo' ? (createDefaultStack = 'ollama', createModalTitle = 'Tambah Paket AI Agent & Workstation') : (createDefaultStack = 'none', createModalTitle = 'Tambah Paket Cloud VPS KVM'))"
                                            class="{{ $inputClass }}">
                                        <option value="vps">Cloud VPS KVM</option>
                                        <option value="ai_combo">CravaCloud AI Combo</option>
                                        <option value="managed_db">Managed Database VPS</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Tagline Singkat</label>
                                    <input type="text" name="tagline" placeholder="Contoh: Cocok untuk website..." class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Badge Produk</label>
                                    <input type="text" name="badge" placeholder="Contoh: Terpopuler, Best Value" class="{{ $inputClass }}">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Default Stack / Panel</label>
                                    <select name="default_stack" x-model="createDefaultStack" class="{{ $inputClass }}">
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
                                    <label class="{{ $labelClass }}">Target Pengguna</label>
                                    <input type="text" name="target_audience" placeholder="Developer, Mahasiswa..." class="{{ $inputClass }}">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted pb-1 border-b border-hairline">Spesifikasi &amp; Harga</h4>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">CPU (vCPU)</label>
                                    <input type="number" name="cpu" min="1" max="128" value="2" required class="{{ $inputClass }} font-mono">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">RAM (GB)</label>
                                    <input type="number" name="ram" min="1" max="1024" value="4" required class="{{ $inputClass }} font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Disk NVMe (GB)</label>
                                    <input type="number" name="disk" min="5" max="10000" value="60" required class="{{ $inputClass }} font-mono">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Bandwidth (GB)</label>
                                    <input type="number" name="bandwidth" min="1" max="100000" value="1000" required class="{{ $inputClass }} font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Harga Modal (Rp)</label>
                                    <input type="number" name="cost_price" min="0" value="70000" required class="{{ $inputClass }} font-mono">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Harga Jual (Rp)</label>
                                    <input type="number" name="sell_price" min="0" value="120000" required class="{{ $inputClass }} font-mono">
                                </div>
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">Fitur Utama (Satu per baris)</label>
                                <textarea name="features" rows="2" placeholder="2 Core vCPU&#10;4 GB RAM DDR4&#10;60 GB NVMe SSD&#10;Root Access Penuh"
                                          class="{{ $inputClass }} font-mono"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer -->
                <div class="px-6 py-4 bg-creamDark/40 border-t border-hairline space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" id="create_is_active" value="1" checked class="w-4 h-4 mt-0.5 border-ink accent-electric">
                            <div>
                                <span class="text-xs font-semibold text-ink block">Buka Checkout Baru</span>
                                <span class="text-[11px] text-muted block">Paket tampil di katalog &amp; dapat dipesan pelanggan baru.</span>
                            </div>
                        </label>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_renewable" id="create_is_renewable" value="1" checked class="w-4 h-4 mt-0.5 border-ink accent-electric">
                            <div>
                                <span class="text-xs font-semibold text-ink block">Izinkan Perpanjangan</span>
                                <span class="text-[11px] text-muted block">Pelanggan lama yang memiliki server ini dapat perpanjang.</span>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">Paket Pengganti (Fallback jika Dihentikan/EOL)</label>
                        <select name="replacement_spec_id" class="{{ $inputClass }}">
                            <option value="">-- Tanpa Paket Pengganti (Wajib Hubungi Support jika EOL) --</option>
                            @foreach($specs as $otherSpec)
                                <option value="{{ $otherSpec->id }}">{{ $otherSpec->name }} (Rp {{ number_format($otherSpec->sell_price, 0, ',', '.') }}/bln)</option>
                            @endforeach
                        </select>
                        <span class="text-[11px] text-muted mt-1 block">Jika perpanjangan dinonaktifkan (EOL), pelanggan otomatis dialihkan ke skema paket pengganti ini.</span>
                    </div>

                    <div class="flex items-center gap-2 w-full justify-end pt-2 border-t border-hairline">
                        <button @click="createModalOpen = false" type="button"
                                class="px-4 py-2 border border-hairline bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                            Simpan Paket
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Paket -->
    <div x-show="editModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4 sm:p-6"
         style="display: none;"
         @keydown.escape.window="editModalOpen = false">

        <div @click.away="editModalOpen = false"
             class="bg-white border border-ink shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">

            <div class="px-6 py-4 border-b border-hairline flex items-center justify-between shrink-0 bg-creamDark/40">
                <div>
                    <h3 class="font-display font-bold text-ink text-base">Edit Paket VPS</h3>
                    <p class="text-xs text-muted">Perbarui spesifikasi atau harga paket <span class="font-semibold text-ink" x-text="editData.name"></span></p>
                </div>
                <button @click="editModalOpen = false" type="button" class="text-muted hover:text-ink p-1.5 font-mono text-base cursor-pointer">&times;</button>
            </div>

            <form :action="'/admin/packages/' + editData.id" method="POST" class="flex flex-col flex-1 min-h-0">
                @csrf
                @method('PUT')

                <div class="p-6 overflow-y-auto flex-1 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h4 class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted pb-1 border-b border-hairline">Informasi &amp; Layanan</h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Nama Paket</label>
                                    <input type="text" name="name" x-model="editData.name" required class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Kategori Layanan</label>
                                    <select name="category" x-model="editData.category" class="{{ $inputClass }}">
                                        <option value="vps">Cloud VPS KVM</option>
                                        <option value="ai_combo">CravaCloud AI Combo</option>
                                        <option value="managed_db">Managed Database VPS</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Tagline Singkat</label>
                                    <input type="text" name="tagline" x-model="editData.tagline" placeholder="Contoh: Pilihan ideal website bisnis..." class="{{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Badge Produk</label>
                                    <input type="text" name="badge" x-model="editData.badge" placeholder="Contoh: Terpopuler, Scale Up..." class="{{ $inputClass }}">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Default Stack / Panel</label>
                                    <select name="default_stack" x-model="editData.default_stack" class="{{ $inputClass }}">
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
                                    <label class="{{ $labelClass }}">Target Pengguna</label>
                                    <input type="text" name="target_audience" x-model="editData.target_audience" placeholder="Developer, Mahasiswa, Startup..." class="{{ $inputClass }}">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted pb-1 border-b border-hairline">Spesifikasi &amp; Harga</h4>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">CPU (vCPU)</label>
                                    <input type="number" name="cpu" x-model="editData.cpu" min="1" max="128" required class="{{ $inputClass }} font-mono">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">RAM (GB)</label>
                                    <input type="number" name="ram" x-model="editData.ram" min="1" max="1024" required class="{{ $inputClass }} font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Disk NVMe (GB)</label>
                                    <input type="number" name="disk" x-model="editData.disk" min="5" max="10000" required class="{{ $inputClass }} font-mono">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Bandwidth (GB)</label>
                                    <input type="number" name="bandwidth" x-model="editData.bandwidth" min="1" max="100000" required class="{{ $inputClass }} font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Harga Modal (Rp)</label>
                                    <input type="number" name="cost_price" x-model="editData.cost_price" min="0" required class="{{ $inputClass }} font-mono">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Harga Jual (Rp)</label>
                                    <input type="number" name="sell_price" x-model="editData.sell_price" min="0" required class="{{ $inputClass }} font-mono">
                                </div>
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">Fitur Utama (Satu per baris)</label>
                                <textarea name="features" x-model="editData.features" rows="2" placeholder="2 Core vCPU&#10;4 GB RAM DDR4&#10;60 GB NVMe SSD&#10;Root Access Penuh"
                                          class="{{ $inputClass }} font-mono"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer -->
                <div class="px-6 py-4 bg-creamDark/40 border-t border-hairline space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1" :checked="editData.is_active" class="w-4 h-4 mt-0.5 border-ink accent-electric">
                            <div>
                                <span class="text-xs font-semibold text-ink block">Buka Checkout Baru</span>
                                <span class="text-[11px] text-muted block">Tampil di katalog &amp; dapat dipesan pelanggan baru.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" name="is_renewable" id="edit_is_renewable" value="1" :checked="editData.is_renewable" class="w-4 h-4 mt-0.5 border-ink accent-electric">
                            <div>
                                <span class="text-xs font-semibold text-ink block">Izinkan Perpanjangan</span>
                                <span class="text-[11px] text-muted block">Pelanggan lama yang memiliki server ini dapat perpanjang.</span>
                            </div>
                        </label>
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">Paket Pengganti (Fallback jika Dihentikan/EOL)</label>
                        <select name="replacement_spec_id" x-model="editData.replacement_spec_id" class="{{ $inputClass }}">
                            <option value="">-- Tanpa Paket Pengganti (Wajib Hubungi Support jika EOL) --</option>
                            @foreach($specs as $otherSpec)
                                <option value="{{ $otherSpec->id }}">{{ $otherSpec->name }} (Rp {{ number_format($otherSpec->sell_price, 0, ',', '.') }}/bln)</option>
                            @endforeach
                        </select>
                        <span class="text-[11px] text-muted mt-1 block">Jika perpanjangan dinonaktifkan (Mode EOL), pelanggan otomatis dialihkan ke skema paket pengganti ini.</span>
                    </div>

                    <div class="flex items-center gap-2 w-full justify-end pt-2 border-t border-hairline">
                        <button @click="editModalOpen = false" type="button"
                                class="px-4 py-2 border border-hairline bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                            Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div x-show="deleteModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="deleteModalOpen = false">

        <div @click.away="deleteModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-md w-full p-6 text-center">
            <div class="w-12 h-12 bg-creamDark border border-ink text-ink mx-auto flex items-center justify-center mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>

            <h3 class="font-display font-bold text-ink text-base mb-2">Hapus Paket VPS?</h3>
            <p class="text-xs text-muted mb-6 leading-relaxed">
                Anda yakin ingin menghapus paket <span class="font-bold text-ink" x-text="deleteData.name"></span>? Tindakan ini permanen dan tidak dapat dibatalkan.
            </p>

            <form :action="'/admin/packages/' + deleteData.id" method="POST" class="flex items-center justify-center gap-3">
                @csrf
                @method('DELETE')

                <button @click="deleteModalOpen = false" type="button"
                        class="px-4 py-2 border border-hairline bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase transition-colors cursor-pointer">
                    Batalkan
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase transition-colors cursor-pointer">
                    Ya, Hapus Paket
                </button>
            </form>
        </div>
    </div>

</div>
@endsection