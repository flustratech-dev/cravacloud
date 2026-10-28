<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — CravaCloud</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
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
<body class="h-full antialiased bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-zinc-100 selection:bg-black selection:text-white dark:selection:bg-white dark:selection:text-black">
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md text-center">

            <!-- Logo -->
            <div class="flex justify-center mb-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 transition-transform hover:scale-105">
                    <img src="{{ asset('images/logo.png') }}"
                         alt="CravaCloud Logo"
                         class="h-10 w-auto object-contain">
                    <span class="font-extrabold text-xl tracking-tight text-slate-900 dark:text-zinc-100">CravaCloud</span>
                </a>
            </div>

            <!-- Error Card -->
            <div class="bg-white dark:bg-zinc-900 p-8 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm text-left relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-zinc-700 via-zinc-400 to-zinc-700 dark:from-zinc-600 dark:via-zinc-300 dark:to-zinc-600"></div>

                <div class="flex items-center gap-3 mb-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded font-mono-code text-xs font-bold bg-neutral-100 text-neutral-800 border border-neutral-200 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700">
                        HTTP @yield('code')
                    </span>
                    <span class="text-xs font-semibold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">
                        Terjadi Kendala
                    </span>
                </div>

                <h1 class="text-2xl font-black text-slate-900 dark:text-zinc-50 tracking-tight mb-2">
                    @yield('title')
                </h1>

                <p class="text-sm text-slate-600 dark:text-zinc-400 leading-relaxed mb-6">
                    @yield('message')
                </p>

                <div class="flex flex-col sm:flex-row gap-2.5 pt-2">
                    <a href="{{ url('/') }}"
                       class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-black hover:bg-neutral-800 text-white dark:bg-zinc-100 dark:hover:bg-zinc-200 dark:text-zinc-950 text-xs font-bold transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Ke Beranda
                    </a>
                    <button type="button"
                            onclick="if(history.length > 1){history.back()}else{window.location.href='/'}"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 dark:bg-zinc-800 dark:hover:bg-zinc-750 dark:text-zinc-200 dark:border-zinc-700 text-xs font-bold border border-slate-200 transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Kembali
                    </button>
                </div>
            </div>

            <!-- Footer / Support -->
            <p class="text-xs text-slate-500 dark:text-zinc-500 mt-6">
                Butuh bantuan lebih lanjut?
                <a href="{{ route('status') }}" class="font-semibold text-slate-700 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-zinc-100 underline underline-offset-2">
                    Status Layanan
                </a>
            </p>

        </div>
    </div>
</body>
</html>
