@extends('layouts.app', [
    'title' => 'Status Layanan — CravaCloud',
    'description' => 'Status realtime layanan CravaCloud: ketersediaan node VPS Jakarta & Singapore, Managed Database, dan dashboard pelanggan.',
    'breadcrumbs' => [
        ['name' => 'Beranda', 'url' => url('/')],
        ['name' => 'Status Layanan'],
    ],
])

@section('content')
{{--
    Halaman status publik.

    Seluruh isi berasal dari data nyata yang dikelola admin:
      - system_components   : status tiap komponen (Admin > Pengaturan)
      - maintenance_windows : jadwal pemeliharaan (Admin > Maintenance)

    Sengaja TIDAK menampilkan persentase uptime, grafik 90 hari, atau latensi:
    CravaCloud tidak memiliki pemantauan otomatis atas server supplier, jadi
    angka seperti itu tidak bisa dipertanggungjawabkan.
--}}
<div class="bg-slate-50 min-h-screen pb-20">

    <!-- Hero Header Banner -->
    <section class="relative overflow-hidden bg-gradient-to-br from-[#F5F8FD] via-[#EBF2FA] to-[#DFEAF7] dark:from-[#09090b] dark:via-zinc-950 dark:to-[#09090b] border-b border-slate-200/70 dark:border-zinc-800 pt-10 sm:pt-12 pb-12 sm:pb-14">

        <!-- Ambient 3D Floating Orbs -->
        <div class="pointer-events-none absolute -top-10 -left-10 w-64 h-64 rounded-full bg-gradient-to-br from-[#CADFF8] via-[#8BB5E8] to-[#4A6FA5] opacity-35 dark:opacity-10 filter blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-12 -right-10 w-72 h-72 rounded-full bg-gradient-to-tl from-[#4A6FA5] to-[#C0D9F7] opacity-30 dark:opacity-10 filter blur-2xl"></div>
        <div class="pointer-events-none absolute top-6 right-1/4 w-20 h-0.5 bg-white/70 dark:bg-zinc-800/50 rotate-[-40deg] rounded-full"></div>
        <div class="pointer-events-none absolute bottom-6 left-1/4 w-16 h-0.5 bg-white/60 dark:bg-zinc-800/50 rotate-[-40deg] rounded-full"></div>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <!-- Breadcrumbs -->
            <nav class="flex items-center space-x-2 text-xs text-slate-500 dark:text-zinc-400 mb-6 font-mono-code">
                <a href="{{ route('home') }}" class="hover:text-[#4A6FA5] transition-colors">Beranda</a>
                <span class="text-slate-400 dark:text-zinc-600">/</span>
                <span class="text-[#4A6FA5] dark:text-[#7da2d8] font-semibold">Status Layanan</span>
            </nav>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-2">
                <div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-slate-900 dark:text-zinc-50">
                        Status Layanan CravaCloud
                    </h1>
                    <p class="text-slate-600 dark:text-zinc-400 text-xs sm:text-sm mt-2 max-w-2xl leading-relaxed">
                        Keadaan terkini komponen layanan dan jadwal pemeliharaan. Status diperbarui oleh tim CravaCloud.
                    </p>
                </div>

                <!-- Ringkasan status keseluruhan -->
                <div class="shrink-0">
                    @if(!($allOperational ?? true) || ($globalMaintenance ?? false))
                        <div class="inline-flex items-center gap-3 px-4.5 py-3 rounded-xl bg-white/85 dark:bg-zinc-900/90 backdrop-blur-xs border border-amber-300/80 dark:border-amber-800/60 text-amber-800 dark:text-amber-300 shadow-2xs">
                            <span class="relative flex h-3 w-3 shrink-0">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                            </span>
                            <div class="text-xs sm:text-sm font-bold text-amber-800 dark:text-amber-300 tracking-tight">
                                {{ ($globalMaintenance ?? false) ? 'Sistem Dalam Pemeliharaan' : 'Sebagian Layanan Terganggu' }}
                            </div>
                        </div>
                    @else
                        <div class="inline-flex items-center gap-3 px-4.5 py-3 rounded-xl bg-white/85 dark:bg-zinc-900/90 backdrop-blur-xs border border-emerald-300/80 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 shadow-2xs">
                            <span class="relative flex h-3 w-3 shrink-0">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            <div class="text-xs sm:text-sm font-bold text-emerald-800 dark:text-emerald-300 tracking-tight">Semua Sistem Beroperasi Normal</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-slate-200/70 dark:border-zinc-800 text-[11px] text-slate-500 dark:text-zinc-400 font-mono-code flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-slate-400 dark:text-zinc-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Zona Waktu: Asia/Jakarta (WIB) &bull; UTC+7</span>
            </div>
        </div>
    </section>

    <!-- Main Container -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 space-y-6">

        {{-- Maintenance yang sedang berjalan (tabel maintenance_windows). --}}
        @if(!empty($runningWindows) && count($runningWindows) > 0)
            <div class="bg-white rounded-xl border border-sky-200 p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-sky-50 text-sky-800 border border-sky-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-sky-600"></span>
                        Sedang Berlangsung
                    </span>
                    <h2 class="text-sm font-bold text-slate-900">Pemeliharaan Aktif</h2>
                </div>
                @foreach($runningWindows as $window)
                    <div class="border border-slate-200 rounded-lg p-4 mb-2 last:mb-0">
                        <div class="text-sm font-bold text-slate-900">{{ $window->title }}</div>
                        @if($window->description)
                            <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $window->description }}</p>
                        @endif
                        <div class="font-mono-code text-[11px] text-slate-500 mt-2">
                            {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                            &ndash;
                            {{ $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Maintenance yang akan datang. --}}
        @if(!empty($upcomingWindows) && count($upcomingWindows) > 0)
            <div class="bg-white rounded-xl border border-amber-200 p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                        Terjadwal
                    </span>
                    <h2 class="text-sm font-bold text-slate-900">Pemeliharaan Mendatang</h2>
                </div>
                @foreach($upcomingWindows as $window)
                    <div class="border border-slate-200 rounded-lg p-4 mb-2 last:mb-0">
                        <div class="text-sm font-bold text-slate-900">{{ $window->title }}</div>
                        @if($window->description)
                            <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $window->description }}</p>
                        @endif
                        <div class="font-mono-code text-[11px] text-slate-500 mt-2">
                            {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                            &ndash;
                            {{ $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Status per komponen (tabel system_components). --}}
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-sm font-bold text-slate-900 mb-1">Status Komponen Layanan</h2>
            <p class="text-xs text-slate-500 mb-4">Keadaan terkini tiap komponen inti platform.</p>

            @if(!empty($components) && count($components) > 0)
                <div class="space-y-2">
                    @foreach($components as $component)
                        <div class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg border border-slate-200">
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-slate-900">{{ $component->name }}</div>
                                <div class="text-[11px] text-slate-500">
                                    {{ $component->status_note ?: $component->description }}
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold border shrink-0 {{ $component->status_classes }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $component->status_dot_class }}"></span>
                                {{ $component->status_label }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-slate-500">Belum ada komponen yang ditampilkan.</p>
            @endif
        </div>

        <!-- Bantuan -->
        <div class="bg-white rounded-xl border border-slate-200 p-6 sm:p-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-1.5">
                    <h3 class="text-base font-bold text-slate-900">Mengalami Kendala pada Server Anda?</h3>
                    <p class="text-xs text-slate-600 max-w-2xl leading-relaxed">
                        Jika server Anda tidak bisa diakses, laporkan dari halaman detail VPS di dashboard atau buka tiket bantuan. Tim kami akan menanganinya pada jam kerja.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <a href="{{ auth()->check() ? route('dashboard.support') : route('home') . '#kontak' }}"
                       class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white text-xs sm:text-sm font-bold shadow-xs hover:shadow-md transition-all hover:scale-[1.02] active:scale-95 cursor-pointer">
                        Buka Tiket Bantuan
                    </a>
                    <a href="{{ route('docs') }}"
                       class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition-colors">
                        Dokumentasi
                    </a>
                </div>
            </div>
        </div>

        <!-- Catatan -->
        <div class="text-center text-xs text-slate-400 leading-relaxed max-w-3xl mx-auto">
            <p>
                Status di halaman ini diperbarui secara manual oleh tim CravaCloud. Penggunaan layanan tunduk pada
                <a href="{{ route('terms') }}" class="text-slate-600 hover:underline">Ketentuan Layanan CravaCloud</a>.
                <span class="text-slate-400">Created by cravacloud.</span>
            </p>
        </div>

    </div>
</div>
@endsection
