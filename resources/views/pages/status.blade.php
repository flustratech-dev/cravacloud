@extends('layouts.app', [
    'title' => 'CravaCloud Status Layanan',
    'description' => 'Status realtime layanan CravaCloud: ketersediaan cluster Cloud VPS Starter & Enterprise DCI Jakarta, Zero-Touch Engine, pembayaran QRIS/VA, dan dashboard pelanggan.',
    'breadcrumbs' => [
        ['name' => 'Beranda', 'url' => url('/')],
        ['name' => 'Status Layanan'],
    ],
])

@section('content')
<div class="min-h-screen bg-cream pb-20">

    <!-- Editorial Header Banner -->
    <section class="border-b border-hairline bg-creamDark/60 pt-10 sm:pt-12 pb-10 sm:pb-12">
        <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14">

            <!-- Breadcrumbs -->
            <nav class="flex items-center space-x-2 font-mono text-xs text-muted uppercase tracking-wider mb-6">
                <a href="{{ route('home') }}" class="hover:text-electric transition-colors">Beranda</a>
                <span class="text-hairline">/</span>
                <span class="text-ink font-bold">Status Layanan</span>
            </nav>

            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
                <div>
                    <span class="font-mono text-[11px] font-bold tracking-[0.2em] text-electric uppercase block">
                        ● TELEMETRY &amp; SYSTEM HEALTH
                    </span>
                    <h1 class="mt-2 font-display font-black text-3xl sm:text-5xl tracking-tight text-ink uppercase leading-none">
                        Status Layanan CravaCloud
                    </h1>
                    <p class="text-muted text-xs sm:text-sm mt-3 max-w-2xl leading-relaxed">
                        Keadaan terkini komponen layanan dan jadwal pemeliharaan platform yang diperbarui langsung oleh tim CravaCloud.
                    </p>
                </div>

                <!-- Summary Pill -->
                <div class="shrink-0">
                    @if(!($allOperational ?? true) || ($globalMaintenance ?? false))
                        <div class="inline-flex items-center gap-3 px-5 py-3.5 bg-amber-100 border-2 border-ink text-amber-950 font-mono">
                            <span class="relative flex h-3 w-3 shrink-0">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-600 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-700"></span>
                            </span>
                            <span class="text-xs font-bold uppercase tracking-wider">
                                {{ ($globalMaintenance ?? false) ? 'Sistem Dalam Pemeliharaan' : 'Sebagian Layanan Terganggu' }}
                            </span>
                        </div>
                    @else
                        <div class="inline-flex items-center gap-3 px-5 py-3.5 bg-electric text-white border-2 border-electric font-mono shadow-sm">
                            <span class="relative flex h-3 w-3 shrink-0">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-300 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-400"></span>
                            </span>
                            <span class="text-xs font-bold uppercase tracking-wider">Semua Sistem Beroperasi Normal</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-8 pt-4 border-t border-hairline font-mono text-[11px] text-muted flex items-center gap-2 uppercase tracking-wider">
                <span>Zona Waktu: Asia/Jakarta (WIB) • UTC+7</span>
            </div>
        </div>
    </section>

    <!-- Main Container -->
    <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 mt-10 space-y-8">

        <!-- Running Maintenance Windows -->
        @if(!empty($runningWindows) && count($runningWindows) > 0)
            <div class="bg-white border-2 border-electric p-6">
                <div class="flex items-center gap-3 mb-4 border-b border-hairline pb-3">
                    <x-ui.badge variant="electric">● Sedang Berlangsung</x-ui.badge>
                    <h2 class="font-display text-base font-bold text-ink uppercase">Pemeliharaan Aktif</h2>
                </div>
                <div class="space-y-3">
                    @foreach($runningWindows as $window)
                        <div class="border border-hairline bg-cream/60 p-4">
                            <div class="font-display text-sm font-bold text-ink">{{ $window->title }}</div>
                            @if($window->description)
                                <p class="text-xs text-muted mt-1 leading-relaxed">{{ $window->description }}</p>
                            @endif
                            <div class="font-mono text-[11px] text-ink font-semibold mt-2">
                                {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                &ndash;
                                {{ $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Upcoming Maintenance Windows -->
        @if(!empty($upcomingWindows) && count($upcomingWindows) > 0)
            <div class="bg-white border-2 border-ink p-6">
                <div class="flex items-center gap-3 mb-4 border-b border-hairline pb-3">
                    <x-ui.badge variant="amber">Terjadwal</x-ui.badge>
                    <h2 class="font-display text-base font-bold text-ink uppercase">Pemeliharaan Mendatang</h2>
                </div>
                <div class="space-y-3">
                    @foreach($upcomingWindows as $window)
                        <div class="border border-hairline bg-cream/60 p-4">
                            <div class="font-display text-sm font-bold text-ink">{{ $window->title }}</div>
                            @if($window->description)
                                <p class="text-xs text-muted mt-1 leading-relaxed">{{ $window->description }}</p>
                            @endif
                            <div class="font-mono text-[11px] text-ink font-semibold mt-2">
                                {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                &ndash;
                                {{ $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Component Status Grid -->
        <div class="bg-white border border-hairline p-6 sm:p-8">
            <div class="border-b border-hairline pb-4 mb-5 flex items-center justify-between">
                <div>
                    <h2 class="font-display text-lg font-bold text-ink uppercase">Status Komponen Layanan</h2>
                    <p class="font-mono text-xs text-muted mt-0.5">Keadaan terkini tiap komponen inti platform CravaCloud.</p>
                </div>
                <span class="font-mono text-[10px] uppercase tracking-widest text-muted">LIVE STATE</span>
            </div>

            @if(!empty($components) && count($components) > 0)
                <div class="divide-y divide-hairline border border-hairline">
                    @foreach($components as $component)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4 bg-cream/40 hover:bg-white transition-colors">
                            <div class="min-w-0">
                                <div class="font-display text-sm font-bold text-ink uppercase">{{ $component->name }}</div>
                                <div class="text-xs text-muted mt-0.5">
                                    {{ $component->status_note ?: $component->description }}
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-none font-mono text-[11px] font-bold uppercase border shrink-0 {{ $component->status_classes }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $component->status_dot_class }}"></span>
                                {{ $component->status_label }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="font-mono text-xs text-muted py-4">Belum ada komponen yang ditampilkan.</p>
            @endif
        </div>

        <!-- Assistance CTA -->
        <div class="bg-ink text-cream border-2 border-ink p-7 sm:p-9 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-1.5">
                <span class="font-mono text-[10px] font-bold tracking-[0.2em] text-electric uppercase block">● INCIDENT SUPPORT</span>
                <h3 class="font-display text-lg sm:text-xl font-bold text-white uppercase">Mengalami Kendala Pada Server Anda?</h3>
                <p class="text-xs sm:text-sm text-cream/70 max-w-2xl leading-relaxed">
                    Jika server Anda tidak bisa diakses, laporkan dari halaman detail VPS di dashboard atau buka tiket bantuan resmi kami.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <a href="{{ auth()->check() ? route('dashboard.support') : \App\Support\NomorWhatsApp::tautan() }}"
                   @unless(auth()->check()) target="_blank" rel="noopener" @endunless
                   class="inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-none bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                    <span>Buka Tiket Bantuan</span>
                    <span>↗</span>
                </a>
                <a href="{{ route('docs') }}"
                   class="inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-none border border-white/25 hover:bg-white hover:text-ink text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                    Dokumentasi
                </a>
            </div>
        </div>

        <!-- Footnote -->
        <div class="text-center font-mono text-[11px] text-muted leading-relaxed max-w-3xl mx-auto">
            <p>
                Status di halaman ini diperbarui oleh tim operasional CravaCloud. Penggunaan layanan tunduk pada
                <a href="{{ route('terms') }}" class="text-ink font-semibold hover:text-electric underline">Ketentuan Layanan CravaCloud</a>.
                Created by cravacloud.
            </p>
        </div>

    </div>
</div>
@endsection
