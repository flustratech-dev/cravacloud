{{--
    Halaman yang tampil saat maintenance menyala.
    Dibuat berdiri sendiri (tanpa @extends) agar tetap tampil benar
    meskipun layout utama ikut terdampak.
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $brand['name'] ?? 'CravaCloud' }} Sedang Pemeliharaan</title>
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="h-full antialiased bg-cream text-ink paper-texture selection:bg-electric selection:text-white">
<div class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-lg">

        <div class="flex justify-center mb-8">
            <div class="inline-flex items-center gap-3">
                <img src="{{ asset('images/logo.svg') }}"
                     onerror="this.src='{{ $brand['logo'] ?? asset('images/logo.png') }}'"
                     alt="{{ $brand['name'] ?? 'CravaCloud' }}"
                     class="h-8 w-8 object-contain">
                <span class="font-display font-extrabold text-xl tracking-tighter text-ink uppercase">
                    {{ strtoupper($brand['name'] ?? 'CRAVACLOUD') }}
                </span>
            </div>
        </div>

        <div class="bg-white p-7 sm:p-8 border-2 border-ink shadow-sm">

            <div class="flex items-center justify-between gap-3 mb-5 pb-3 border-b border-hairline">
                <span class="inline-flex items-center gap-2 px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-amber-100 text-amber-950 border border-amber-500">
                    <span class="w-2 h-2 rounded-full bg-amber-600 animate-pulse"></span>
                    Maintenance
                </span>
                <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Status Layanan</span>
            </div>

            <h1 class="font-display text-2xl font-black text-ink uppercase mb-2">
                Sedang Dalam Pemeliharaan
            </h1>

            <p class="text-sm text-muted leading-relaxed mb-5">
                {{ $message ?? 'Layanan ini sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.' }}
            </p>

            @if(!empty($window))
                <div class="border border-ink bg-cream p-4 mb-5">
                    <span class="font-mono text-[10px] font-bold text-electric uppercase tracking-widest block mb-1.5">
                        Jadwal Pemeliharaan
                    </span>
                    <div class="font-display text-sm font-bold text-ink mb-1">{{ $window->title }}</div>
                    <div class="font-mono text-xs text-muted">
                        {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                        &ndash;
                        {{ $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                    </div>
                </div>
            @endif

            <div class="flex flex-col sm:flex-row gap-2.5">
                <a href="{{ route('status') }}"
                   class="flex-1 inline-flex items-center justify-between gap-2 px-5 py-3 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                    <span>Lihat Halaman Status</span>
                    <span>↗</span>
                </a>
                <a href="{{ url('/') }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-3 bg-cream hover:bg-ink hover:text-white text-ink font-mono text-xs font-bold uppercase border border-ink transition-colors">
                    Kembali ke Beranda
                </a>
            </div>

        </div>

        @if(!empty($brand['support_email']))
            <p class="text-center font-mono text-xs text-muted mt-6">
                Butuh bantuan? Hubungi
                <a href="mailto:{{ $brand['support_email'] }}"
                   class="font-bold text-ink hover:text-electric underline underline-offset-2">
                    {{ $brand['support_email'] }}
                </a>
            </p>
        @endif

    </div>
</div>
</body>
</html>
