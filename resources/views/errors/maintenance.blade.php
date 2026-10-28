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
    <title>Sedang Pemeliharaan — {{ $brand['name'] ?? 'CravaCloud' }}</title>
    <link rel="icon" type="image/png" href="{{ $brand['favicon'] ?? asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            var isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
        })();
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
        html.dark { color-scheme: dark; background-color: #09090b; }
        html.dark body { background-color: #09090b; color: #fafafa; }
    </style>
</head>
<body class="h-full antialiased bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-zinc-100">
<div class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-lg">

        <div class="flex justify-center mb-8">
            <img src="{{ $brand['logo'] ?? asset('images/logo.png') }}"
                 alt="{{ $brand['name'] ?? 'CravaCloud' }}"
                 class="h-10 w-auto object-contain">
        </div>

        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-slate-200 dark:border-zinc-800 shadow-sm">

            <div class="flex items-center gap-3 mb-5">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/50">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400 animate-pulse"></span>
                    Maintenance
                </span>
                <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Status Layanan</span>
            </div>

            <h1 class="text-xl font-extrabold text-slate-900 dark:text-zinc-50 mb-2">
                Sedang Dalam Pemeliharaan
            </h1>

            <p class="text-sm text-slate-600 dark:text-zinc-400 leading-relaxed mb-5">
                {{ $message ?? 'Layanan ini sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.' }}
            </p>

            @if(!empty($window))
                <div class="rounded-lg border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-950 p-4 mb-5">
                    <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 uppercase tracking-wider block mb-2">
                        Jadwal Pemeliharaan
                    </span>
                    <div class="text-sm font-bold text-slate-900 dark:text-zinc-100 mb-1">{{ $window->title }}</div>
                    <div class="font-mono-code text-xs text-slate-600 dark:text-zinc-400">
                        {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                        &ndash;
                        {{ $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                    </div>
                </div>
            @endif

            <div class="flex flex-col sm:flex-row gap-2">
                <a href="{{ route('status') }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white dark:bg-zinc-100 dark:hover:bg-zinc-200 dark:text-zinc-950 text-xs font-bold transition-colors shadow-sm">
                    Lihat Halaman Status
                </a>
                <a href="{{ url('/') }}"
                   class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-white hover:bg-slate-50 text-slate-700 dark:bg-zinc-800 dark:hover:bg-zinc-750 dark:text-zinc-200 dark:border-zinc-700 text-xs font-bold border border-slate-200 transition-colors">
                    Kembali ke Beranda
                </a>
            </div>

        </div>

        @if(!empty($brand['support_email']))
            <p class="text-center text-xs text-slate-500 dark:text-zinc-500 mt-6">
                Butuh bantuan? Hubungi
                <a href="mailto:{{ $brand['support_email'] }}"
                   class="font-semibold text-slate-700 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-zinc-100 underline underline-offset-2">
                    {{ $brand['support_email'] }}
                </a>
            </p>
        @endif

    </div>
</div>
</body>
</html>
