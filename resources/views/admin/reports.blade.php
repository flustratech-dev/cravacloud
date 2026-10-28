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
    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-900 tracking-tight">Pusat Laporan & Analitik</h2>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Ringkasan performa finansial, konversi pesanan multi-layanan (Cloud VPS, AI Agent, Managed Database), penagihan invoice, dan SLA support.
                </p>
                <div class="flex items-center gap-3 text-2xs text-slate-400 mt-2 font-mono-code">
                    <span>No. Dokumen: <strong class="text-slate-700">{{ $reportRef }}</strong></span>
                    <span>•</span>
                    <span>Periode Aktif: <strong class="text-slate-700">{{ $periodLabel }}</strong></span>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Filter Periode -->
                <form method="GET" action="{{ route('admin.reports') }}" class="flex items-center">
                    <select name="days"
                            onchange="this.form.submit()"
                            class="px-3.5 py-2 rounded-lg border border-slate-300 text-xs font-semibold bg-white text-slate-800 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-900 shadow-xs cursor-pointer">
                        <option value="30" {{ (string)$days === '30' ? 'selected' : '' }}>📅 30 Hari Terakhir</option>
                        <option value="90" {{ (string)$days === '90' ? 'selected' : '' }}>📅 90 Hari Terakhir</option>
                        <option value="365" {{ (string)$days === '365' ? 'selected' : '' }}>📅 1 Tahun Terakhir</option>
                        <option value="all" {{ (string)$days === 'all' ? 'selected' : '' }}>🌐 Semua Periode</option>
                    </select>
                </form>

                <!-- Tombol Preview PDF Eksekutif -->
                <button type="button"
                        @click="openPdfPreview('executive', 'Laporan Eksekutif & Portofolio Bisnis')"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 hover:bg-black text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                    </svg>
                    <span>Preview & Cetak PDF</span>
                </button>

                <!-- Tombol Word (.docx) -->
                <a :href="'{{ route('admin.reports.export') }}?type=executive&days=' + currentDays + '&format=docx'"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs transition-colors">
                    <svg class="w-4 h-4 text-slate-700" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6z"/>
                        <path d="M15.2 12.9L13.8 17h-1.6l-1.1-3.6L10 17H8.4l-1.4-4.1h1.4l.8 2.8 1.1-2.8h1.4l1.1 2.8.8-2.8z"/>
                    </svg>
                    <span>Word (.docx)</span>
                </a>

                <!-- Tombol CSV -->
                <a :href="'{{ route('admin.reports.export') }}?type=executive&days=' + currentDays + '&format=csv'"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs transition-colors">
                    <svg class="w-4 h-4 text-slate-700" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 2l5 5h-5V4zM8 12h8v2H8v-2zm0 3h8v2H8v-2z"/>
                    </svg>
                    <span>CSV Excel</span>
                </a>

                <!-- Dropdown Laporan Khusus Lengkap (VPS, AI Agent, Managed DB, dll.) -->
                <div class="relative" @click.away="exportDropdownOpen = false">
                    <button type="button"
                            @click="exportDropdownOpen = !exportDropdownOpen"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                        <span>Laporan Khusus</span>
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="exportDropdownOpen"
                         x-transition
                         class="absolute right-0 mt-2 w-80 bg-white border border-slate-200 rounded-lg shadow-xl py-2 z-30 text-xs divide-y divide-slate-100"
                         style="display:none;">

                        <div class="px-3.5 py-2 text-2xs font-bold uppercase tracking-wider text-slate-400">
                            Pilih Dokumen Laporan Khusus
                        </div>

                        <!-- 1. Laporan Khusus Cloud VPS -->
                        <div class="p-3 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 flex items-center gap-1.5">
                                    <span>💻</span> Laporan Khusus Cloud VPS
                                </span>
                                <span class="text-2xs font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $vpsOrders->count() }} unit</span>
                            </div>
                            <p class="text-2xs text-slate-400 mt-0.5 mb-2">Infrastruktur compute Linux, spesifikasi CPU/RAM & datacenter</p>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('vps', 'Laporan Khusus Infrastruktur Cloud VPS')"
                                        class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=vps&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=vps&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <!-- 2. Laporan Khusus AI Agent & Combo -->
                        <div class="p-3 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 flex items-center gap-1.5">
                                    <span>🤖</span> Laporan Khusus AI Agent
                                </span>
                                <span class="text-2xs font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $aiOrders->count() }} unit</span>
                            </div>
                            <p class="text-2xs text-slate-400 mt-0.5 mb-2">Workstation AI, Claude Code, OpenCode, Ollama & Private RAG</p>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('ai_combo', 'Laporan Khusus AI Agent & Autonomous Workstation')"
                                        class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=ai_combo&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=ai_combo&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <!-- 3. Laporan Khusus Managed Database -->
                        <div class="p-3 hover:bg-slate-50 transition-colors">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 flex items-center gap-1.5">
                                    <span>🗄️</span> Laporan Khusus Managed DB
                                </span>
                                <span class="text-2xs font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $dbOrders->count() }} unit</span>
                            </div>
                            <p class="text-2xs text-slate-400 mt-0.5 mb-2">Dedicated Database Server (Postgres, MySQL, Redis, MongoDB)</p>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('database', 'Laporan Khusus Managed Database Server')"
                                        class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=database&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=database&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <!-- 4. Laporan Semua Pesanan Layanan (Konsolidasi) -->
                        <div class="p-3 hover:bg-slate-50 transition-colors">
                            <div class="font-bold text-slate-900">Laporan Seluruh Pesanan (Semua)</div>
                            <div class="text-2xs text-slate-400 mb-2">{{ $orders->count() }} total seluruh transaksi</div>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('orders', 'Laporan Konsolidasi Seluruh Pesanan Layanan')"
                                        class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=orders&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=orders&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <!-- 5. Invoices & Keuangan -->
                        <div class="p-3 hover:bg-slate-50 transition-colors">
                            <div class="font-bold text-slate-800">Laporan Invoice & Pendapatan</div>
                            <div class="text-2xs text-slate-400 mb-2">{{ $invoices->count() }} invoice terbit</div>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('invoices', 'Laporan Faktur Penagihan & Pendapatan')"
                                        class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <!-- 6. Support Tickets -->
                        <div class="p-3 hover:bg-slate-50 transition-colors">
                            <div class="font-bold text-slate-800">Laporan Layanan & Tiket Support (SLA)</div>
                            <div class="text-2xs text-slate-400 mb-2">{{ $tickets->count() }} tiket bantuan</div>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="exportDropdownOpen = false; openPdfPreview('tickets', 'Laporan Layanan & Performa Tiket Dukungan (SLA)')"
                                        class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Preview PDF
                                </button>
                                <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=docx'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
                                    Word
                                </a>
                                <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=csv'"
                                   class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-semibold text-xs transition-colors">
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
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Revenue -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Omzet Lunas</span>
                <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-slate-900 mt-2">Rp {{ number_format($revenue, 0, ',', '.') }}</p>
            <p class="text-2xs text-slate-400 mt-1">{{ $paidInvoices }} transaksi lunas</p>
        </div>

        <!-- Paid Invoices -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Invoice Terbit</span>
                <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-slate-900 mt-2">{{ $invoices->count() }}</p>
            <p class="text-2xs text-slate-400 mt-1">{{ $paidInvoices }} lunas ({{ $invoices->count() > 0 ? round(($paidInvoices / $invoices->count()) * 100) : 0 }}%)</p>
        </div>

        <!-- Total Orders -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Pesanan Layanan</span>
                <span class="p-1.5 rounded-lg bg-slate-100 text-slate-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-slate-900 mt-2">{{ $orders->count() }}</p>
            <p class="text-2xs text-slate-400 mt-1">{{ $orders->where('status', 'active')->count() }} layanan running</p>
        </div>

        <!-- SLA Response -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Kecepatan SLA</span>
                <span class="p-1.5 rounded-lg bg-purple-50 text-purple-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-2xl font-bold text-slate-900 mt-2">{{ $avgFirstResponse ? round($avgFirstResponse / 60, 1).' jam' : '-' }}</p>
            <p class="text-2xs text-slate-400 mt-1">{{ $tickets->count() }} tiket bantuan tercatat</p>
        </div>
    </div>

    <!-- 3 Pilar Layanan Utama CravaCloud (Cloud VPS, AI Agent, Managed Database) -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="font-bold text-sm text-slate-900">Performa 3 Pilar Layanan Utama</h3>
                <p class="text-xs text-slate-500">Rincian performa penjualan, instans aktif, dan ekspor dokumen khusus per kategori layanan.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- 1. Cloud VPS -->
            <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">💻</span>
                            <span class="font-bold text-sm text-slate-900">Cloud VPS</span>
                        </div>
                        <span class="text-2xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">Compute</span>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-bold text-slate-900">Rp {{ number_format($vpsRevenue, 0, ',', '.') }}</p>
                        <div class="flex items-center gap-2 text-2xs text-slate-500 mt-1">
                            <span><strong>{{ $vpsOrders->count() }}</strong> total pesanan</span>
                            <span>•</span>
                            <span class="text-emerald-600 font-semibold">{{ $vpsOrders->where('status', 'active')->count() }} aktif</span>
                        </div>
                    </div>
                </div>
                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center gap-1.5">
                    <button type="button"
                            @click="openPdfPreview('vps', 'Laporan Khusus Infrastruktur Cloud VPS')"
                            class="flex-1 py-1.5 text-center text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        Preview PDF
                    </button>
                    <a :href="'{{ route('admin.reports.export') }}?type=vps&days=' + currentDays + '&format=docx'"
                       class="px-2.5 py-1.5 text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        Word
                    </a>
                    <a :href="'{{ route('admin.reports.export') }}?type=vps&days=' + currentDays + '&format=csv'"
                       class="px-2.5 py-1.5 text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        CSV
                    </a>
                </div>
            </div>

            <!-- 2. AI Agent & Combo -->
            <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🤖</span>
                            <span class="font-bold text-sm text-slate-900">AI Agent & Workstation</span>
                        </div>
                        <span class="text-2xs font-semibold px-2 py-0.5 rounded bg-blue-50 text-blue-700">Autonomous</span>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-bold text-slate-900">Rp {{ number_format($aiRevenue, 0, ',', '.') }}</p>
                        <div class="flex items-center gap-2 text-2xs text-slate-500 mt-1">
                            <span><strong>{{ $aiOrders->count() }}</strong> total pesanan</span>
                            <span>•</span>
                            <span class="text-emerald-600 font-semibold">{{ $aiOrders->where('status', 'active')->count() }} aktif</span>
                        </div>
                    </div>
                </div>
                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center gap-1.5">
                    <button type="button"
                            @click="openPdfPreview('ai_combo', 'Laporan Khusus AI Agent & Autonomous Workstation')"
                            class="flex-1 py-1.5 text-center text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        Preview PDF
                    </button>
                    <a :href="'{{ route('admin.reports.export') }}?type=ai_combo&days=' + currentDays + '&format=docx'"
                       class="px-2.5 py-1.5 text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        Word
                    </a>
                    <a :href="'{{ route('admin.reports.export') }}?type=ai_combo&days=' + currentDays + '&format=csv'"
                       class="px-2.5 py-1.5 text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        CSV
                    </a>
                </div>
            </div>

            <!-- 3. Managed Database Server -->
            <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🗄️</span>
                            <span class="font-bold text-sm text-slate-900">Managed Database</span>
                        </div>
                        <span class="text-2xs font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700">Isolated</span>
                    </div>
                    <div class="mt-3">
                        <p class="text-2xl font-bold text-slate-900">Rp {{ number_format($dbRevenue, 0, ',', '.') }}</p>
                        <div class="flex items-center gap-2 text-2xs text-slate-500 mt-1">
                            <span><strong>{{ $dbOrders->count() }}</strong> total pesanan</span>
                            <span>•</span>
                            <span class="text-emerald-600 font-semibold">{{ $dbOrders->where('status', 'active')->count() }} aktif</span>
                        </div>
                    </div>
                </div>
                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center gap-1.5">
                    <button type="button"
                            @click="openPdfPreview('database', 'Laporan Khusus Managed Database Server')"
                            class="flex-1 py-1.5 text-center text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        Preview PDF
                    </button>
                    <a :href="'{{ route('admin.reports.export') }}?type=database&days=' + currentDays + '&format=docx'"
                       class="px-2.5 py-1.5 text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        Word
                    </a>
                    <a :href="'{{ route('admin.reports.export') }}?type=database&days=' + currentDays + '&format=csv'"
                       class="px-2.5 py-1.5 text-xs font-semibold rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors">
                        CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown Grid -->
    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Status Order -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-sm text-slate-900">Distribusi Status Pesanan</h3>
                    <span class="text-2xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600">{{ $orders->count() }} Total</span>
                </div>
                <div class="flex items-center gap-1 text-xs">
                    <button type="button" @click="openPdfPreview('orders', 'Laporan Rincian Pesanan')" class="text-slate-600 hover:text-slate-900 font-medium px-1.5 py-0.5 rounded hover:bg-slate-100">PDF</button>
                    <span class="text-slate-300">•</span>
                    <a :href="'{{ route('admin.reports.export') }}?type=orders&days=' + currentDays + '&format=docx'" class="text-slate-600 hover:text-slate-900 font-medium px-1.5 py-0.5 rounded hover:bg-slate-100">Word</a>
                    <span class="text-slate-300">•</span>
                    <a :href="'{{ route('admin.reports.export') }}?type=orders&days=' + currentDays + '&format=csv'" class="text-slate-600 hover:text-slate-900 font-medium px-1.5 py-0.5 rounded hover:bg-slate-100">CSV</a>
                </div>
            </div>

            <div class="space-y-3">
                @php $totalOrd = max($orders->count(), 1); @endphp
                @forelse($orderStatuses as $status => $count)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium text-slate-700 uppercase">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="text-slate-500 font-semibold">{{ $count }} ({{ round(($count / $totalOrd) * 100) }}%)</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="h-2 rounded-full {{ in_array($status, ['active', 'completed']) ? 'bg-emerald-500' : (in_array($status, ['pending', 'provisioning']) ? 'bg-amber-500' : 'bg-rose-500') }}"
                                 style="width: {{ ($count / $totalOrd) * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 py-4 text-center">Belum ada data pesanan pada periode ini.</p>
                @endforelse
            </div>
        </div>

        <!-- Status Tiket -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-xs">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-sm text-slate-900">Distribusi Status Tiket Support</h3>
                    <span class="text-2xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600">{{ $tickets->count() }} Total</span>
                </div>
                <div class="flex items-center gap-1 text-xs">
                    <button type="button" @click="openPdfPreview('tickets', 'Laporan Layanan & Tiket Support')" class="text-slate-600 hover:text-slate-900 font-medium px-1.5 py-0.5 rounded hover:bg-slate-100">PDF</button>
                    <span class="text-slate-300">•</span>
                    <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=docx'" class="text-slate-600 hover:text-slate-900 font-medium px-1.5 py-0.5 rounded hover:bg-slate-100">Word</a>
                    <span class="text-slate-300">•</span>
                    <a :href="'{{ route('admin.reports.export') }}?type=tickets&days=' + currentDays + '&format=csv'" class="text-slate-600 hover:text-slate-900 font-medium px-1.5 py-0.5 rounded hover:bg-slate-100">CSV</a>
                </div>
            </div>

            <div class="space-y-3">
                @php $totalTck = max($tickets->count(), 1); @endphp
                @forelse($ticketStatuses as $status => $count)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-medium text-slate-700 uppercase">{{ str_replace('_', ' ', $status) }}</span>
                            <span class="text-slate-500 font-semibold">{{ $count }} ({{ round(($count / $totalTck) * 100) }}%)</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="h-2 rounded-full {{ in_array($status, ['resolved', 'closed']) ? 'bg-slate-500' : ($status === 'open' ? 'bg-rose-500' : 'bg-blue-500') }}"
                                 style="width: {{ ($count / $totalTck) * 100 }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 py-4 text-center">Belum ada data tiket pada periode ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Daily Revenue Table -->
    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-xs">
        <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-sm text-slate-900">Rincian Omzet Harian</h3>
                <p class="text-xs text-slate-500 mt-0.5">Arus kas pendapatan dari invoice pelanggan yang telah berstatus lunas.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                        @click="openPdfPreview('executive', 'Laporan Omzet Harian')"
                        class="text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 border border-slate-300 hover:bg-slate-200 transition-colors">
                    Preview PDF
                </button>
                <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=docx'"
                   class="text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 border border-slate-300 hover:bg-slate-200 transition-colors">
                    Export Word
                </a>
                <a :href="'{{ route('admin.reports.export') }}?type=invoices&days=' + currentDays + '&format=csv'"
                   class="text-xs font-semibold px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 border border-slate-300 hover:bg-slate-200 transition-colors">
                    Export CSV
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
                    <tr>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-center">Invoice Lunas</th>
                        <th class="px-5 py-3 text-right">Total Omzet</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dailyRevenue as $date => $amount)
                        @php
                            $dayCount = $invoices->where('status', 'paid')->filter(fn($i) => $i->created_at->format('Y-m-d') === $date)->count();
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-3 font-medium text-slate-800">
                                {{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}
                            </td>
                            <td class="px-5 py-3 text-center text-slate-600">
                                <span class="px-2 py-0.5 rounded-full text-2xs font-semibold bg-slate-100 text-slate-700">{{ $dayCount }} transaksi</span>
                            </td>
                            <td class="px-5 py-3 text-right font-bold text-slate-900">
                                Rp {{ number_format($amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center text-slate-400">
                                Belum ada invoice lunas pada rentang periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- INTERACTIVE PDF PREVIEW MODAL (CUMA PDF NYA AJA - CLEAN)          -->
    <!-- ================================================================= -->
    <div x-show="previewModal"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;"
         @keydown.escape.window="previewModal = false">

        <!-- Backdrop Blur -->
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-xs transition-opacity"
             @click="previewModal = false"></div>

        <!-- Modal Dialog -->
        <div class="min-h-full flex items-center justify-center p-2 sm:p-4">
            <div class="relative w-full max-w-6xl h-[94vh] bg-slate-900 rounded-lg shadow-2xl flex flex-col overflow-hidden z-10"
                 @click.stop>

                <!-- Slim Minimal Header (No template card, just title & close) -->
                <div class="h-10 bg-slate-900 px-4 flex items-center justify-between border-b border-slate-800 text-white shrink-0">
                    <span class="text-xs font-semibold text-slate-300" x-text="previewTitle">Pratinjau Dokumen PDF</span>
                    <button type="button"
                            @click="previewModal = false"
                            class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white px-2.5 py-1 rounded-md hover:bg-slate-800 transition-colors cursor-pointer">
                        <span>Tutup</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- PDF Viewer (Takes 100% of space) -->
                <div class="relative flex-1 bg-slate-800">
                    <!-- Loading Spinner Overlay -->
                    <div x-show="previewLoading"
                         class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-slate-900 text-white">
                        <div class="w-8 h-8 border-2 border-slate-600 border-t-white rounded-full animate-spin"></div>
                        <p class="text-xs text-slate-400 mt-2.5">Memuat dokumen PDF...</p>
                    </div>

                    <!-- PDF Render Iframe -->
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
