@extends('layouts.admin', ['title' => 'Manajemen Paket VPS', 'headerTitle' => 'Katalog Paket VPS Cloud', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
@php
    $usdIdrRate = $usdIdrRate ?? 16500;
    $bundlePricesUsd = $bundlePricesUsd ?? [];
    $canaryWhitelist = $canaryWhitelist ?? [];
    $allStacks = $allStacks ?? [];
    $disabledStacks = $disabledStacks ?? [];
    $stackCategories = collect($allStacks)->pluck('category')->unique()->sort()->values()->all();
@endphp
<div class="space-y-6" x-data="{
    specs: {{ Js::from($specs->keyBy('id')) }},
    canaryIds: {{ Js::from($canaryWhitelist) }},
    activeTab: (new URLSearchParams(window.location.search)).get('tab') || 'vps',
    stackSearch: '',
    stackCategory: 'all',
    createModalOpen: false,
    editModalOpen: false,
    deleteModalOpen: false,
    createCategory: 'vps',
    createModalTitle: 'Tambah Paket Cloud VPS Baru',
    createDefaultStack: 'none',
    stackMatches(label, key, cat) {
        if (this.stackCategory !== 'all' && cat !== this.stackCategory) return false;
        const q = (this.stackSearch || '').trim().toLowerCase();
        if (!q) return true;
        return label.toLowerCase().includes(q) || key.toLowerCase().includes(q) || cat.toLowerCase().includes(q);
    },
    openCreate() {
        this.createCategory = 'vps';
        this.createModalTitle = 'Tambah Paket Cloud VPS KVM';
        this.createDefaultStack = 'none';
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
        upstream_bundle_id: '',
        upstream_bundle_type: '',
        canary_whitelisted: false,
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
            upstream_bundle_id: spec.upstream_bundle_id || '',
            upstream_bundle_type: spec.upstream_bundle_type || '',
            canary_whitelisted: this.canaryIds.includes(Number(spec.id)),
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
    <datalist id="bundleIdSuggestions">
        @foreach($bundlePricesUsd as $bId => $bUsd)
            <option value="{{ $bId }}">{{ $bId }} (${{ number_format((float) $bUsd, 2) }}/bln)</option>
        @endforeach
    </datalist>

    @php
        $totalSpecs = $specs->count();
        $activeSpecs = $specs->where('is_active', true)->count();
        $inactiveSpecs = $totalSpecs - $activeSpecs;
        $avgMargin = $totalSpecs > 0 ? $specs->avg(fn($s) => $s->sell_price - $s->cost_price) : 0;
        $vpsCount = $specs->count();
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

                <button @click="activeTab = 'stacks'"
                        type="button"
                        :class="activeTab === 'stacks' ? 'border-electric text-ink font-bold bg-white border-t border-x border-hairline' : 'border-transparent text-muted hover:text-ink'"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 border-b-2 font-mono text-xs uppercase tracking-wider transition-colors cursor-pointer">
                    <span>Katalog {{ count($allStacks) }} Stack</span>
                    <span :class="activeTab === 'stacks' ? 'bg-electric text-white' : 'bg-creamDark text-ink border border-hairline'"
                          class="px-1.5 py-0.5 text-[10px] font-bold">{{ count($disabledStacks) > 0 ? count($disabledStacks) . ' off' : 'ON' }}</span>
                </button>
            </div>
        </div>

        <!-- Tab Header Banner & Action Buttons -->
        <div class="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-hairline bg-white">
            <div>
                <div x-show="activeTab === 'vps'">
                    <h3 class="font-display font-bold text-ink text-base">Katalog Spesifikasi Cloud VPS ({{ $vpsCount }} Paket)</h3>
                    <p class="text-xs text-muted mt-0.5">Paket resmi Cloud VPS KVM NVMe (Starter &amp; Enterprise Series) yang mendukung seluruh 1-Click Stack.</p>
                </div>
                <div x-show="activeTab === 'stacks'" style="display: none;">
                    <h3 class="font-display font-bold text-ink text-base">Katalog {{ count($allStacks) }} Stack Otomatis &amp; Kill-Switch</h3>
                    <p class="text-xs text-muted mt-0.5">Sembunyikan/aktifkan stack di Checkout &amp; Reinstall secara instan tanpa deploy ulang. Kurs patokan: <span class="font-mono font-bold text-ink">1 USD = Rp {{ number_format($usdIdrRate, 0, ',', '.') }}</span></p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <div x-show="activeTab === 'vps'">
                    <button @click="openCreate()" type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        <span>+ Tambah Paket Cloud VPS</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto" x-show="activeTab !== 'stacks'">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Nama Paket &amp; Upstream</th>
                        <th class="px-5 py-3.5">Spesifikasi Hardware</th>
                        <th class="px-5 py-3.5">Harga Pokok (HPP)</th>
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
                            $isCore = in_array(strtolower($spec->name), array_map('strtolower', $corePlanNames), true);
                            $bundleId = (string) ($spec->upstream_bundle_id ?? '');
                            $bundleUsd = $bundleId !== '' ? (float) ($bundlePricesUsd[$bundleId] ?? 0) : 0.0;
                            $liveHppIdr = $bundleUsd > 0 ? round($bundleUsd * $usdIdrRate, 0) : (float) $spec->cost_price;
                            $margin = $spec->sell_price - $liveHppIdr;
                            $marginPercent = $spec->sell_price > 0 ? round(($margin / $spec->sell_price) * 100, 1) : 0;
                            $isCanary = in_array((int) $spec->id, $canaryWhitelist, true);
                        @endphp
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <!-- Nama Paket -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-display font-bold text-ink text-sm block">{{ $spec->name }}</span>
                                    <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-white text-ink border border-hairline">Cloud VPS</span>
                                    @if($isCore)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-ink" title="Paket sistem inti (dilindungi dari penghapusan)">
                                            Inti
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-white text-muted border border-hairline">
                                            Kustom
                                        </span>
                                    @endif
                                    @if($isCanary)
                                        <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white" title="Masuk whitelist auto-provisioning Tencent">
                                            Canary ON
                                        </span>
                                    @endif
                                </div>
                                @if($spec->tagline)
                                    <span class="text-[11px] text-muted italic block">{{ $spec->tagline }}</span>
                                @endif
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                    <span class="text-[11px] text-muted font-mono">ID: #SPEC-{{ str_pad($spec->id, 3, '0', STR_PAD_LEFT) }}</span>
                                    @if($bundleId !== '')
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 bg-creamDark text-ink border border-hairline">
                                            {{ $bundleId }} ({{ $spec->upstream_bundle_type ?? 'N/A' }})
                                        </span>
                                    @endif
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
                                <span class="block">Rp {{ number_format($spec->cost_price, 0, ',', '.') }}</span>
                                @if($bundleUsd > 0)
                                    <span class="block text-[10px] text-ink font-semibold mt-0.5">
                                        Upstream: ${{ number_format($bundleUsd, 2) }} (&asymp; Rp {{ number_format($liveHppIdr, 0, ',', '.') }})
                                    </span>
                                @endif
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

                    <tr x-show="activeTab === 'vps' && {{ $vpsCount }} === 0" style="display: none;">
                        <td colspan="8" class="px-5 py-12 text-center text-muted">
                            <p class="font-display font-bold text-ink">Belum ada paket Cloud VPS KVM yang dibuat.</p>
                            <button @click="openCreate()" class="mt-2 inline-flex items-center gap-1.5 px-3.5 py-2 bg-electric text-white font-mono text-xs font-bold uppercase hover:bg-electricHover transition-colors cursor-pointer">
                                + Tambah Paket Cloud VPS
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tab 4: Katalog 177 Stack + Kill-Switch -->
        <div x-show="activeTab === 'stacks'" style="display: none;">
            <div class="p-4 border-y border-hairline bg-creamDark/20 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" x-model="stackSearch" placeholder="Cari stack (mis. coolify, dokku, ollama)..."
                           class="w-64 px-3 py-2 border border-hairline text-xs font-mono bg-white text-ink focus:outline-none focus:border-electric">
                    <select x-model="stackCategory" class="px-3 py-2 border border-hairline text-xs font-mono bg-white text-ink focus:outline-none focus:border-electric">
                        <option value="all">Semua Kategori ({{ count($allStacks) }})</option>
                        @foreach($stackCategories as $stackCat)
                            <option value="{{ $stackCat }}">{{ $stackCat }}</option>
                        @endforeach
                    </select>
                </div>
                <span class="font-mono text-[11px] text-muted">
                    Disembunyikan saat ini: <strong class="text-ink">{{ count($disabledStacks) }}</strong> stack
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                        <tr>
                            <th class="px-5 py-3">Stack &amp; Key</th>
                            <th class="px-5 py-3">Kategori &amp; UI Group</th>
                            <th class="px-5 py-3">Min RAM / Flag</th>
                            <th class="px-5 py-3 text-center">Checkout &amp; Reinstall</th>
                            <th class="px-5 py-3 text-right">Kill-Switch</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @foreach($allStacks as $stackKey => $stackDef)
                            @php $isDisabled = in_array($stackKey, $disabledStacks, true); @endphp
                            <tr x-show="stackMatches(@js($stackDef->label), @js($stackDef->key), @js($stackDef->category))"
                                class="hover:bg-creamDark/30 transition-colors">
                                <td class="px-5 py-3">
                                    <span class="font-display font-bold text-ink block">{{ $stackDef->label }}</span>
                                    <span class="font-mono text-[11px] text-muted">{{ $stackDef->key }}</span>
                                    @if($stackDef->description)
                                        <span class="block text-[11px] text-muted mt-0.5">{{ $stackDef->description }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs text-ink block">{{ $stackDef->category }}</span>
                                    <span class="font-mono text-[10px] text-muted uppercase">Group: {{ $stackDef->uiGroup }}</span>
                                </td>
                                <td class="px-5 py-3 font-mono text-[11px]">
                                    <span class="text-ink font-bold block">Min {{ $stackDef->minRamGb }} GB RAM</span>
                                    <div class="flex flex-wrap gap-1 mt-0.5">
                                        @if($stackDef->requiresLicense)
                                            <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase bg-ink text-cream">BYOL / Berbayar</span>
                                        @endif
                                        @if($stackDef->warningType && $stackDef->warningType !== 'none')
                                            <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase bg-creamDark text-ink border border-ink">{{ $stackDef->warningType }}</span>
                                        @endif
                                        @if(!$stackDef->visibleOnCheckout)
                                            <span class="px-1.5 py-0.5 text-[9px] uppercase bg-white text-muted border border-hairline">Hidden Default</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if($stackKey === 'none')
                                        <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">Sistem Inti</span>
                                    @elseif($isDisabled)
                                        <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">Disembunyikan</span>
                                    @else
                                        <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">Aktif</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if($stackKey === 'none')
                                        <span class="font-mono text-[10px] text-muted">Terkunci</span>
                                    @else
                                        <form action="{{ route('admin.packages.stacks.toggle') }}" method="POST" class="inline">
                                            @csrf
                                            <input type="hidden" name="stack_key" value="{{ $stackKey }}">
                                            <button type="submit"
                                                    class="px-3 py-1.5 font-mono text-[10px] font-bold uppercase cursor-pointer {{ $isDisabled ? 'bg-electric hover:bg-electricHover text-white' : 'bg-white border border-ink hover:bg-creamDark text-ink' }}">
                                                {{ $isDisabled ? 'Aktifkan' : 'Sembunyikan' }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
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

                            <div>
                                <label class="{{ $labelClass }}">Nama Paket</label>
                                <input type="text" name="name" required placeholder="Contoh: Starter Node" class="{{ $inputClass }}">
                                <input type="hidden" name="category" value="vps">
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
                                        @foreach(\App\Models\Order::stackLabels() as $stackKey => $stackLabel)
                                            <option value="{{ $stackKey }}">{{ $stackLabel }}</option>
                                        @endforeach
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

                            <div class="pt-2 border-t border-hairline space-y-3">
                                <h4 class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted">Mapping Upstream Tencent &amp; Canary</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="{{ $labelClass }}">Upstream Bundle ID</label>
                                        <input type="text" name="upstream_bundle_id" list="bundleIdSuggestions"
                                               placeholder="bundle_starter_nmc_lin_med2_01" class="{{ $inputClass }} font-mono">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">Bundle Quota Pool Type</label>
                                        <select name="upstream_bundle_type" class="{{ $inputClass }} font-mono">
                                            <option value="">-- Tidak di-map (Manual) --</option>
                                            <option value="STARTER_BUNDLE">STARTER_BUNDLE</option>
                                            <option value="GENERAL_BUNDLE">GENERAL_BUNDLE</option>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="canary_whitelist_present" value="1">
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input type="checkbox" name="canary_whitelisted" value="1" class="w-4 h-4 mt-0.5 border-ink accent-electric">
                                    <span class="text-xs text-ink">Masukkan ke Canary Whitelist Auto-Provisioning Tencent</span>
                                </label>
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

                            <div>
                                <label class="{{ $labelClass }}">Nama Paket</label>
                                <input type="text" name="name" x-model="editData.name" required class="{{ $inputClass }}">
                                <input type="hidden" name="category" value="vps">
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
                                        @foreach(\App\Models\Order::stackLabels() as $stackKey => $stackLabel)
                                            <option value="{{ $stackKey }}">{{ $stackLabel }}</option>
                                        @endforeach
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

                            <div class="pt-2 border-t border-hairline space-y-3">
                                <h4 class="font-mono text-[10px] font-bold uppercase tracking-widest text-muted">Mapping Upstream Tencent &amp; Canary</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="{{ $labelClass }}">Upstream Bundle ID</label>
                                        <input type="text" name="upstream_bundle_id" x-model="editData.upstream_bundle_id" list="bundleIdSuggestions"
                                               placeholder="bundle_starter_nmc_lin_med2_01" class="{{ $inputClass }} font-mono">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">Bundle Quota Pool Type</label>
                                        <select name="upstream_bundle_type" x-model="editData.upstream_bundle_type" class="{{ $inputClass }} font-mono">
                                            <option value="">-- Tidak di-map (Manual) --</option>
                                            <option value="STARTER_BUNDLE">STARTER_BUNDLE</option>
                                            <option value="GENERAL_BUNDLE">GENERAL_BUNDLE</option>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="canary_whitelist_present" value="1">
                                <label class="flex items-start gap-2 cursor-pointer">
                                    <input type="checkbox" name="canary_whitelisted" value="1" :checked="editData.canary_whitelisted" class="w-4 h-4 mt-0.5 border-ink accent-electric">
                                    <span class="text-xs text-ink">Masuk Canary Whitelist Auto-Provisioning Tencent</span>
                                </label>
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