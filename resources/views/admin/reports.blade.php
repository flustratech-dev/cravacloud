@extends('layouts.admin', ['title' => 'Reports & Analytics', 'headerTitle' => 'Reports & Analytics', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6"
     x-data="{
         previewModal: false,
         previewLoading: true,
         previewUrl: '',
         previewTitle: 'Laporan Bisnis & Operasional',
         currentType: 'executive',
         currentDays: '{{ $days }}',
         exportDropdownOpen: false,
         downloadUrl(format) {
             return '{{ route('admin.reports.export') }}?type=' + this.currentType + '&days=' + this.currentDays + '&format=' + format;
         },
         openPdfPreview(type = 'executive', title = 'Laporan Bisnis') {
             this.currentType = type;
             this.previewTitle = title;
             this.previewLoading = true;
             this.previewUrl = '{{ route('admin.reports.preview') }}?type=' + type + '&days=' + this.currentDays;
             this.previewModal = true;
         }
     }">

    <!-- Top Action & Filter Bar -->
    <div class="bg-white border border-hairline p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-xl font-bold text-ink tracking-tight">Pusat Laporan & Analitik</h2>
                <p class="text-xs text-muted mt-1">
                    Ringkasan performa finansial, konversi pesanan layanan, penagihan invoice, dan SLA support.
                </p>
                <div class="flex flex-wrap items-center gap-3 text-[11px] text-muted mt-2 font-mono">
                    <span>No. Dokumen: <strong class="text-ink">{{ $reportRef }}</strong></span>
                    <span>&middot;</span>
                    <span>Periode Aktif: <strong class="text-ink">{{ $periodLabel }}</strong></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Filter Periode -->
                <form method="GET" action="{{ route('admin.reports') }}" class="flex items-center">
                    <select name="days"
                            onchange="this.form.submit()"
                            class="px-3.5 py-2 border border-hairline text-xs font-mono font-bold bg-white text-ink hover:border-ink focus:outline-none focus:border-electric cursor-pointer">
                        <option value="30" {{ (string)$days === '30' ? 'selected' : '' }}>30 Hari Terakhir</option>
                        <option value="90" {{ (string)$days === '90' ? 'selected' : '' }}>90 Hari Terakhir</option>
                        <option value="365" {{ (string)$days === '365' ? 'selected' : '' }}>1 Tahun Terakhir</option>
                        <option value="all" {{ (string)$days === 'all' ? 'selected' : '' }}>Semua Periode</option>
                    </select>
                </form>

                <!-- Tombol Preview PDF Eksekutif -->
                <button type="button"
                        @click="openPdfPreview('executive', 'Laporan Eksekutif & Portofolio Bisnis')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                    </svg>
                    <span>Preview & Cetak PDF</span>
                </button>

                <!-- Tombol Word (.docx) -->
                <a :href="'{{ route('admin.reports.export') }}?type=executive&days=' + currentDays + '&format=docx'"
                   class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-hairline hover:border-ink text-ink font-mono text-xs font-bold transition-colors">
                    <svg class="w-4 h-4 text-ink" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/>
                        <path d="M15.2 12.9L13.8 17h-1.6l-1.1-3.6L10 17H8.4l-1.4-4.1h1.4l.8 2.8 1.1-2.8h1.4l1.1 2.8.8-2.8z"/>
                    </svg>
                    <span>Word (.docx)</span>
                </a>

                <!-- Tombol CSV -->
                <a :href="'{{ route('admin.reports.export') }}?type=executive&days=' + currentDays + '&format=csv'"
                   class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-hairline hover:border-ink text-ink font-mono text-xs font-bold transition-colors">
                    <svg class="w-4 h-4 text-ink" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM8 12h8v2H8v-2zm0 3h8v2H8v-2z"/>
                    </svg>
                    <span>CSV Excel</span>
                </a>

                <!-- Dropdown Laporan Khusus Lengkap -->
                <div class="relative" @click.away="exportDropdownOpen = false">
                    <button type="button"
                            @click="exportDropdownOpen = !exportDropdownOpen"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-creamDark hover:border-ink text-ink border border-hairline font-mono text-xs font-bold transition-colors cursor-pointer">
                        <span>Laporan Khusus</span>
                        <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="exportDropdownOpen" x-cloak
                         class="absolute right-0 mt-1.5 w-80 bg-white border border-ink shadow-xl py-1 z-30 text-xs divide-y divide-hairline"
                         style="display:none;">

                        <div class="px-3.5 py-2 font-mono text-[10px] font-bold uppercase tracking-widest text-muted bg-creamDark/40">
                            Pilih Dokumen Laporan Khusus
                        </div>

                        <!-- 1. Laporan Khusus Cloud VPS -->
                        <div class="p-3.5 hover:bg-creamDark/30 transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="font-display font-bold text-ink">Laporan Khusus Cloud VPS</span>
                                <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 bg-creamDark border border-hairline text-ink">{{ $vpsOrders->count() }} unit</span>
                            </div>
                            <p class="text-[11px] text-muted mt-0.5 mb-2">Infrastruktur compute Linux KVM, 1-Click Stack &amp; datacenter</p>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('vps', 'Laporan Khusus Infrastruktur Cloud VPS')"
                                        class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors cursor-pointer">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=vps&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=vps&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <!-- 2. Laporan Semua Pesanan Layanan -->
                        <div class="p-3.5 hover:bg-creamDark/30 transition-colors">
                            <div class="font-display font-bold text-ink">Laporan Seluruh Pesanan (Semua)</div>
                            <div class="font-mono text-[11px] text-muted mb-2">{{ $orders->count() }} total seluruh transaksi</div>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('orders', 'Laporan Konsolidasi Seluruh Pesanan Layanan')"
                                        class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors cursor-pointer">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=orders&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=orders&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <!-- 5. Invoices & Keuangan -->
                        <div class="p-3.5 hover:bg-creamDark/30 transition-colors">
                            <div class="font-display font-bold text-ink">Laporan Invoice &amp; Pendapatan</div>
                            <div class="font-mono text-[11px] text-muted mb-2">{{ $invoices->count() }} invoice terbit</div>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('invoices', 'Laporan Faktur Penagihan & Pendapatan')"
                                        class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors cursor-pointer">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <!-- 6. Support Tickets -->
                        <div class="p-3.5 hover:bg-creamDark/30 transition-colors">
                            <div class="font-display font-bold text-ink">Laporan Layanan &amp; Tiket Support (SLA)</div>
                            <div class="font-mono text-[11px] text-muted mb-2">{{ $tickets->count() }} tiket bantuan</div>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('tickets', 'Laporan Layanan & Performa Tiket Dukungan (SLA)')"
                                        class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors cursor-pointer">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 bg-white hover:border-ink text-ink border border-hairline font-mono font-bold text-[10px] uppercase transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Executive KPI Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <!-- Revenue -->
        <div class="bg-white p-5">
            <div class="flex items-center justify-between">
                <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Omzet Lunas</span>
                <span class="p-1.5 bg-creamDark border border-hairline text-electric">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="font-display text-2xl font-bold text-ink mt-2">Rp {{ number_format($revenue, 0, ',', '.') }}</p>
            <p class="font-mono text-[11px] text-muted mt-1">{{ $paidInvoices }} transaksi lunas</p>
        </div>

        <!-- Paid Invoices -->
        <div class="bg-white p-5">
            <div class="flex items-center justify-between">
                <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Invoice Terbit</span>
                <span class="p-1.5 bg-creamDark border border-hairline text-ink">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
            </div>
            <p class="font-display text-2xl font-bold text-ink mt-2">{{ $invoices->count() }}</p>
            <p class="font-mono text-[11px] text-muted mt-1">{{ $paidInvoices }} lunas ({{ $invoices->count() > 0 ? round(($paidInvoices / $invoices->count()) * 100) : 0 }}%)</p>
        </div>

        <!-- Total Orders -->
        <div class="bg-white p-5">
            <div class="flex items-center justify-between">
                <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Total Pesanan Layanan</span>
                <span class="p-1.5 bg-creamDark border border-hairline text-ink">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                </span>
            </div>
            <p class="font-display text-2xl font-bold text-ink mt-2">{{ $orders->count() }}</p>
            <p class="font-mono text-[11px] text-muted mt-1">{{ $orders->where('status', 'active')->count() }} layanan running</p>
        </div>

        <!-- SLA Response -->
        <div class="bg-white p-5">
            <div class="flex items-center justify-between">
                <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Kecepatan SLA</span>
                <span class="p-1.5 bg-creamDark border border-hairline text-ink">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="font-display text-2xl font-bold text-ink mt-2">{{ $avgFirstResponse ? round($avgFirstResponse / 60, 1).' jam' : '-' }}</p>
            <p class="font-mono text-[11px] text-muted mt-1">{{ $tickets->count() }} tiket bantuan tercatat</p>
        </div>
    </div>

    <!-- Ekspor Cepat Laporan Operasional -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="font-display font-bold text-sm text-ink uppercase tracking-wider">Dokumen Laporan Operasional</h3>
                <p class="text-xs text-muted mt-0.5">Ekspor rincian infrastruktur Cloud VPS, invoice penagihan, dan performa tiket SLA dalam format PDF, Word, atau CSV.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- 1. Cloud VPS -->
            <div class="bg-white border border-hairline p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="font-display font-bold text-sm text-ink">Cloud VPS KVM</span>
                        <span class="font-mono text-[10px] font-bold uppercase px-2 py-0.5 bg-creamDark border border-hairline text-ink">Compute</span>
                    </div>
                    <div class="mt-3">
                        <p class="font-display text-2xl font-bold text-ink">Rp {{ number_format($vpsRevenue, 0, ',', '.') }}</p>
                        <div class="flex items-center gap-2 font-mono text-[11px] text-muted mt-1">
                            <span><strong class="text-ink">{{ $vpsOrders->count() }}</strong> total pesanan</span>
                            <span>&middot;</span>
                            <span class="text-electric font-bold">{{ $vpsOrders->where('status', 'active')->count() }} aktif</span>
                        </div>
                    </div>
                </div>
                <div class="pt-4 mt-4 border-t border-hairline flex items-center gap-1.5">
                    <button type="button"
                            @click="openPdfPreview('vps', 'Laporan Khusus Infrastruktur Cloud VPS')"
                            class="flex-1 py-1.5 text-center font-mono text-[11px] font-bold uppercase bg-creamDark hover:border-ink text-ink border border-hairline transition-colors cursor-pointer">
                        Preview PDF
                    </button>
                    <a :href="'{{ route('admin.reports.export') }}?type=vps&days=' + currentDays + '&format=docx'"
                       class="px-2.5 py-1.5 font-mono text-[11px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline transition-colors">
                        Word
                    </a>
                    <a :href="'{{ route('admin.reports.export') }}?type=vps&days=' + currentDays + '&format=csv'"
                       class="px-2.5 py-1.5 font-mono text-[11px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline transition-colors">
                        CSV
                    </a>
                </div>
            </div>

            <!-- 2. Invoice & Penagihan -->
            <div class="bg-white border border-hairline p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="font-display font-bold text-sm text-ink">Invoice &amp; Penagihan</span>
                        <span class="font-mono text-[10px] font-bold uppercase px-2 py-0.5 bg-creamDark border border-hairline text-ink">Billing</span>
                    </div>
                    <div class="mt-3">
                        <p class="font-display text-2xl font-bold text-ink">Rp {{ number_format($revenue, 0, ',', '.') }}</p>
                        <div class="flex items-center gap-2 font-mono text-[11px] text-muted mt-1">
                            <span><strong class="text-ink">{{ $invoices->count() }}</strong> invoice terbit</span>
                            <span>&middot;</span>
                            <span class="text-electric font-bold">{{ $paidInvoices }} lunas</span>
                        </div>
                    </div>
                </div>
                <div class="pt-4 mt-4 border-t border-hairline flex items-center gap-1.5">
                    <button type="button"
                            @click="openPdfPreview('invoices', 'Laporan Faktur Penagihan & Pendapatan')"
                            class="flex-1 py-1.5 text-center font-mono text-[11px] font-bold uppercase bg-creamDark hover:border-ink text-ink border border-hairline transition-colors cursor-pointer">
                        Preview PDF
                    </button>
                    <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=docx'"
                       class="px-2.5 py-1.5 font-mono text-[11px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline transition-colors">
                        Word
                    </a>
                    <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=csv'"
                       class="px-2.5 py-1.5 font-mono text-[11px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline transition-colors">
                        CSV
                    </a>
                </div>
            </div>

            <!-- 3. Tiket Support & SLA -->
            <div class="bg-white border border-hairline p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="font-display font-bold text-sm text-ink">Tiket Support &amp; SLA</span>
                        <span class="font-mono text-[10px] font-bold uppercase px-2 py-0.5 bg-creamDark border border-hairline text-ink">Helpdesk</span>
                    </div>
                    <div class="mt-3">
                        <p class="font-display text-2xl font-bold text-ink">{{ $avgFirstResponse ? round($avgFirstResponse / 60, 1) . ' jam' : '-' }}</p>
                        <div class="flex items-center gap-2 font-mono text-[11px] text-muted mt-1">
                            <span><strong class="text-ink">{{ $tickets->count() }}</strong> total tiket</span>
                            <span>&middot;</span>
                            <span class="text-electric font-bold">{{ $tickets->whereIn('status', ['resolved', 'closed'])->count() }} selesai</span>
                        </div>
                    </div>
                </div>
                <div class="pt-4 mt-4 border-t border-hairline flex items-center gap-1.5">
                    <button type="button"
                            @click="openPdfPreview('tickets', 'Laporan Layanan & Performa Tiket Dukungan (SLA)')"
                            class="flex-1 py-1.5 text-center font-mono text-[11px] font-bold uppercase bg-creamDark hover:border-ink text-ink border border-hairline transition-colors cursor-pointer">
                        Preview PDF
                    </button>
                    <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=docx'"
                       class="px-2.5 py-1.5 font-mono text-[11px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline transition-colors">
                        Word
                    </a>
                    <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=csv'"
                       class="px-2.5 py-1.5 font-mono text-[11px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline transition-colors">
                        CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown Grid -->
    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Status Order -->
        <div class="bg-white border border-hairline p-5">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-hairline">
                <div class="flex items-center gap-2">
                    <h3 class="font-display font-bold text-sm text-ink">Distribusi Status Pesanan</h3>
                    <span class="font-mono text-[10px] font-bold px-2 py-0.5 bg-creamDark border border-hairline text-ink">{{ $orders->count() }} Total</span>
                </div>
                <div class="flex items-center gap-1.5 font-mono text-[11px]">
                    <button type="button" @click="openPdfPreview('orders', 'Laporan Rincian Pesanan')" class="text-electric font-bold hover:underline cursor-pointer">PDF</button>
                    <span class="text-muted">&middot;</span>
                    <a :href="'{{ route('admin.reports.export') }}?type=orders&days=' + currentDays + '&format=docx'" class="text-ink hover:text-electric font-bold">Word</a>
                    <span class="text-muted">&middot;</span>
                    <a :href="'{{ route('admin.reports.export') }}?type=orders&days=' + currentDays + '&format=csv'" class="text-ink hover:text-electric font-bold">CSV</a>
                </div>
            </div>

            <div class="space-y-3">
                @php $totalOrd = max($orders->count(), 1); @endphp
                @forelse($orderStatuses as $status => $count)
                    <div>
                        <div class="flex justify-between font-mono text-xs mb-1">
                            <span class="font-bold text-ink uppercase">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="text-muted font-bold">{{ $count }} ({{ round(($count / $totalOrd) * 100) }}%)</span>
                        </div>
                        <div class="w-full bg-creamDark border border-hairline h-2">
                            <div class="h-full {{ in_array($status, ['active', 'completed']) ? 'bg-electric' : 'bg-ink' }}"
                                 style="width: {{ ($count / $totalOrd) * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="font-mono text-xs text-muted py-4 text-center">Belum ada data pesanan pada periode ini.</p>
                @endforelse
            </div>
        </div>

        <!-- Status Tiket -->
        <div class="bg-white border border-hairline p-5">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-hairline">
                <div class="flex items-center gap-2">
                    <h3 class="font-display font-bold text-sm text-ink">Distribusi Status Tiket Support</h3>
                    <span class="font-mono text-[10px] font-bold px-2 py-0.5 bg-creamDark border border-hairline text-ink">{{ $tickets->count() }} Total</span>
                </div>
                <div class="flex items-center gap-1.5 font-mono text-[11px]">
                    <button type="button" @click="openPdfPreview('tickets', 'Laporan Layanan & Tiket Support')" class="text-electric font-bold hover:underline cursor-pointer">PDF</button>
                    <span class="text-muted">&middot;</span>
                    <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=docx'" class="text-ink hover:text-electric font-bold">Word</a>
                    <span class="text-muted">&middot;</span>
                    <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=csv'" class="text-ink hover:text-electric font-bold">CSV</a>
                </div>
            </div>

            <div class="space-y-3">
                @php $totalTck = max($tickets->count(), 1); @endphp
                @forelse($ticketStatuses as $status => $count)
                    <div>
                        <div class="flex justify-between font-mono text-xs mb-1">
                            <span class="font-bold text-ink uppercase">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="text-muted font-bold">{{ $count }} ({{ round(($count / $totalTck) * 100) }}%)</span>
                        </div>
                        <div class="w-full bg-creamDark border border-hairline h-2">
                            <div class="h-full {{ in_array($status, ['resolved', 'closed']) ? 'bg-muted' : 'bg-electric' }}"
                                 style="width: {{ ($count / $totalTck) * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="font-mono text-xs text-muted py-4 text-center">Belum ada data tiket pada periode ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Daily Revenue Table -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-hairline">
            <div>
                <h3 class="font-display font-bold text-sm text-ink uppercase tracking-wider">Rincian Omzet Harian</h3>
                <p class="text-xs text-muted mt-0.5">Arus kas pendapatan dari invoice pelanggan yang telah berstatus lunas.</p>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button"
                        @click="openPdfPreview('executive', 'Laporan Omzet Harian')"
                        class="font-mono text-[11px] font-bold uppercase px-2.5 py-1.5 bg-creamDark text-ink border border-hairline hover:border-ink transition-colors cursor-pointer">
                    Preview PDF
                </button>
                <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=docx'"
                   class="font-mono text-[11px] font-bold uppercase px-2.5 py-1.5 bg-white text-ink border border-hairline hover:border-ink transition-colors">
                    Export Word
                </a>
                <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=csv'"
                   class="font-mono text-[11px] font-bold uppercase px-2.5 py-1.5 bg-white text-ink border border-hairline hover:border-ink transition-colors">
                    Export CSV
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                    <tr>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-center">Invoice Lunas</th>
                        <th class="px-5 py-3 text-right">Total Omzet</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($dailyRevenue as $date => $amount)
                        @php
                            $dayCount = $invoices->where('status', 'paid')->filter(fn($i) => $i->created_at->format('Y-m-d') === $date)->count();
                        @endphp
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3 font-medium text-ink">
                                {{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                <span class="px-2 py-0.5 font-mono text-[11px] font-bold bg-creamDark border border-hairline text-ink">{{ $dayCount }} transaksi</span>
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-bold text-ink">
                                Rp {{ number_format($amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center font-mono text-xs text-muted">
                                Belum ada invoice lunas pada rentang periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- INTERACTIVE PDF PREVIEW MODAL -->
    <div x-show="previewModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;"
         @keydown.escape.window="previewModal = false">

        <div class="fixed inset-0 bg-ink/75 transition-opacity"
             @click="previewModal = false"></div>

        <div class="min-h-full flex items-center justify-center p-2 sm:p-4">
            <div class="relative w-full max-w-6xl h-[94vh] bg-ink border border-ink shadow-2xl flex flex-col overflow-hidden z-10"
                 @click.stop>

                <div class="h-10 bg-ink px-4 flex items-center justify-between border-b border-muted/30 text-cream shrink-0">
                    <span class="font-mono text-xs font-bold uppercase tracking-wider" x-text="previewTitle">Pratinjau Dokumen PDF</span>
                    <button type="button"
                            @click="previewModal = false"
                            class="inline-flex items-center gap-1.5 font-mono text-xs text-cream/80 hover:text-white px-2.5 py-1 hover:bg-white/10 transition-colors cursor-pointer">
                        <span>Tutup</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="relative flex-1 bg-white">
                    <div x-show="previewLoading"
                         class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-cream text-ink">
                        <div class="w-8 h-8 border-2 border-hairline border-t-electric animate-spin"></div>
                        <p class="font-mono text-xs text-muted mt-2.5">Memuat dokumen PDF...</p>
                    </div>

                    <template x-if="previewUrl">
                        <iframe id="reportPdfIframe"
                                :src="previewUrl"
                                class="w-full h-full border-0"
                                @load="previewLoading = false">
                        </iframe>
                    </template>
                </div>

            </div>
        </div>
    </div>

</div>
@endsection