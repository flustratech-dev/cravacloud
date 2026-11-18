<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CravaCloud @yield('title')</title>
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="h-full antialiased bg-cream text-ink paper-texture selection:bg-electric selection:text-white">
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md text-center">

            <div class="flex justify-center mb-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-3 group">
                    <img src="{{ asset('images/logo.svg') }}"
                         onerror="this.src='{{ asset('images/logo.png') }}'"
                         alt="CravaCloud Logo"
                         class="h-8 w-8 object-contain">
                    <span class="font-display font-extrabold text-xl tracking-tighter text-ink uppercase">CRAVACLOUD</span>
                </a>
            </div>

            <div class="bg-white p-8 border-2 border-ink shadow-sm text-left relative">
                <div class="flex items-center justify-between gap-3 mb-5 pb-3 border-b border-hairline">
                    <span class="inline-flex items-center px-2.5 py-1 font-mono text-xs font-bold bg-electric text-white uppercase">
                        HTTP @yield('code')
                    </span>
                    <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">
                        TERJADI KENDALA
                    </span>
                </div>

                <h1 class="font-display text-2xl font-black text-ink uppercase tracking-tight mb-2">
                    @yield('title')
                </h1>

                <p class="text-sm text-muted leading-relaxed mb-6">
                    @yield('message')
                </p>

                <div class="flex flex-col sm:flex-row gap-2.5 pt-2">
                    <a href="{{ url('/') }}"
                       class="flex-1 inline-flex items-center justify-between gap-2 px-5 py-3 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                        <span>Ke Beranda</span>
                        <span>↗</span>
                    </a>
                    <button type="button"
                            onclick="if(history.length > 1){history.back()}else{window.location.href='/'}"
                            class="inline-flex items-center justify-center gap-2 px-4 py-3 bg-cream hover:bg-ink hover:text-white text-ink font-mono text-xs font-bold uppercase border border-ink transition-colors cursor-pointer">
                        <span>← Kembali</span>
                    </button>
                </div>
            </div>

            <p class="font-mono text-xs text-muted mt-6">
                Butuh bantuan lebih lanjut?
                <a href="{{ route('status') }}" class="font-bold text-ink hover:text-electric underline underline-offset-2">
                    Status Layanan
                </a>
            </p>

        </div>
    </div>
</body>
</html>
