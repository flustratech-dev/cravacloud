<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CravaCloud {{ $title ?? 'Client Portal' }}</title>
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased bg-cream text-ink font-sans selection:bg-electric selection:text-white" x-data="{ sidebarOpen: false, userDropdown: false }">
    @include('partials.impersonation-banner')

    <div class="h-screen w-full flex flex-col md:flex-row overflow-hidden bg-cream">

        <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-ink/50 md:hidden" @click="sidebarOpen = false"></div>

        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 w-72 md:w-64 bg-white border-r border-hairline flex flex-col transition-transform duration-200 md:static md:inset-auto md:h-full md:shrink-0 md:translate-x-0">

            <!-- Brand Header -->
            <div class="flex h-16 shrink-0 items-center justify-between gap-3 px-5 border-b border-hairline">
                <a href="{{ route('dashboard.index') }}" class="flex min-w-0 items-center gap-3">
                    <img src="{{ asset('images/logo.svg') }}" alt="CravaCloud" class="h-8 w-auto shrink-0 object-contain">
                    <div class="min-w-0">
                        <span class="block truncate font-display font-bold tracking-tight text-ink text-base leading-none">CRAVACLOUD</span>
                        <span class="block font-mono text-[9px] uppercase tracking-widest text-muted mt-1 leading-none">CLIENT CONSOLE</span>
                    </div>
                </a>
                <button type="button" @click="sidebarOpen = false" class="md:hidden p-1.5 border border-hairline text-ink hover:border-electric transition-colors cursor-pointer" aria-label="Tutup Menu">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('dashboard.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 font-mono text-xs uppercase tracking-wider transition-colors {{ request()->routeIs('dashboard.index') || request()->routeIs('dashboard.vps.*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-semibold' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                    <span>VPS</span>
                </a>

                <a href="{{ route('dashboard.billing') }}"
                   class="flex items-center gap-3 px-3 py-2.5 font-mono text-xs uppercase tracking-wider transition-colors {{ request()->routeIs('dashboard.billing') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-semibold' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <span>Tagihan &amp; Invoice</span>
                </a>

                <a href="{{ route('dashboard.support') }}"
                   class="flex items-center justify-between px-3 py-2.5 font-mono text-xs uppercase tracking-wider transition-colors {{ request()->routeIs('dashboard.support*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-semibold' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        <span>Tiket Bantuan</span>
                    </div>
                    @php $userOpenTickets = $userOpenTickets ?? 0; @endphp
                    @if($userOpenTickets > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('dashboard.support*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $userOpenTickets }}</span>
                    @endif
                </a>

                <a href="{{ route('dashboard.settings') }}"
                   class="flex items-center gap-3 px-3 py-2.5 font-mono text-xs uppercase tracking-wider transition-colors {{ request()->routeIs('dashboard.settings') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-semibold' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Pengaturan Akun</span>
                </a>

                <a href="{{ route('organizations.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 font-mono text-xs uppercase tracking-wider transition-colors {{ request()->routeIs('organizations.*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-semibold' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5V4H2v16h5m10 0v-4H7v4m10 0H7m3-8h4m-6-4h8"/></svg>
                    <span>Organisasi</span>
                </a>

                <a href="{{ route('security.settings') }}"
                   class="flex items-center gap-3 px-3 py-2.5 font-mono text-xs uppercase tracking-wider transition-colors {{ request()->routeIs('security.*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-semibold' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>Keamanan</span>
                </a>

                <div class="pt-4 pb-1 px-3 border-t border-hairline mt-3">
                    <p class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Deploy Layanan</p>
                </div>

                <a href="{{ route('home') }}#pricing"
                   class="flex items-center justify-between px-3 py-2 text-xs text-ink hover:bg-creamDark/70 hover:text-electric font-medium transition-colors group">
                    <span>Tambah VPS</span>
                    <span class="font-mono text-muted group-hover:text-electric">↗</span>
                </a>
            </nav>

            <!-- Sidebar Footer -->
            <div class="shrink-0 border-t border-hairline p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2.5 font-mono text-xs uppercase tracking-wider font-bold text-red-700 hover:bg-red-50 transition-colors cursor-pointer">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>Keluar Akun</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 h-full overflow-y-auto">
            <header class="h-16 shrink-0 bg-white border-b border-hairline flex items-center justify-between px-5 sm:px-8">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" @click="sidebarOpen = true" class="md:hidden p-2 border border-hairline text-ink hover:border-electric cursor-pointer" aria-label="Buka Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="font-display text-base sm:text-lg font-bold text-ink truncate">{{ $headerTitle ?? 'Dashboard' }}</h1>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <a href="{{ route('home') }}#pricing"
                       class="hidden sm:inline-flex items-center gap-1.5 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase tracking-wider px-4 py-2 transition-colors">
                        <span>+ Deploy</span>
                    </a>

                    <div class="relative" @click.away="userDropdown = false">
                        <button type="button" @click="userDropdown = !userDropdown"
                                class="flex items-center gap-2 border border-hairline hover:border-ink bg-creamDark/40 px-3 py-1.5 transition-colors cursor-pointer"
                                title="{{ auth()->user()->full_name }}">
                            <span class="w-5 h-5 bg-ink text-cream font-mono text-[10px] font-bold flex items-center justify-center shrink-0">
                                {{ strtoupper(substr(auth()->user()->full_name ?: 'U', 0, 1)) }}
                            </span>
                            <span class="hidden md:inline font-mono text-xs font-semibold text-ink max-w-[140px] truncate">{{ auth()->user()->full_name }}</span>
                        </button>

                        <div x-show="userDropdown" x-cloak x-transition
                             class="absolute right-0 mt-2 w-64 bg-white border-2 border-ink py-1.5 z-50 text-xs">
                            <div class="px-4 py-3 border-b border-hairline bg-creamDark/40">
                                <p class="font-display font-bold text-ink text-sm truncate">{{ auth()->user()->full_name }}</p>
                                <p class="font-mono text-[11px] text-muted truncate mt-0.5">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('dashboard.settings') }}" class="block px-4 py-2.5 text-ink hover:bg-creamDark/60 font-medium">Pengaturan Akun</a>
                            @if(auth()->user()->is_admin)
                                <a href="{{ route('admin.index') }}" class="block px-4 py-2.5 text-electric hover:bg-creamDark/60 font-mono font-bold uppercase">Panel Admin ↗</a>
                            @endif
                            <a href="{{ route('home') }}" class="block px-4 py-2.5 text-ink hover:bg-creamDark/60 font-medium">Halaman Utama</a>
                            <div class="border-t border-hairline my-1"></div>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2.5 text-red-700 hover:bg-red-50 font-mono font-bold uppercase cursor-pointer">Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash Alerts -->
            <div class="px-5 sm:px-8 pt-5">
                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                         class="border-l-4 border-emerald-600 bg-white border border-hairline p-4 text-xs text-emerald-900 mb-4 flex items-center justify-between">
                        <span class="font-medium">{{ session('success') }}</span>
                        <button type="button" @click="show = false" class="text-muted hover:text-ink px-2 font-mono text-sm">&times;</button>
                    </div>
                @endif
                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                         class="border-l-4 border-red-600 bg-white border border-hairline p-4 text-xs text-red-900 mb-4 flex items-center justify-between">
                        <span class="font-medium">{{ session('error') }}</span>
                        <button type="button" @click="show = false" class="text-muted hover:text-ink px-2 font-mono text-sm">&times;</button>
                    </div>
                @endif
                @if(session('warning'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                         class="border-l-4 border-amber-500 bg-white border border-hairline p-4 text-xs text-amber-950 mb-4 flex items-center justify-between">
                        <span class="font-medium">{{ session('warning') }}</span>
                        <button type="button" @click="show = false" class="text-muted hover:text-ink px-2 font-mono text-sm">&times;</button>
                    </div>
                @endif
                @if(session('info'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                         class="border-l-4 border-electric bg-white border border-hairline p-4 text-xs text-ink mb-4 flex items-center justify-between">
                        <span class="font-medium">{{ session('info') }}</span>
                        <button type="button" @click="show = false" class="text-muted hover:text-ink px-2 font-mono text-sm">&times;</button>
                    </div>
                @endif
            </div>

            <!-- Main Page Body -->
            <main class="flex-1 px-5 sm:px-8 pb-12 pt-2">
                @if(!empty($upcomingMaintenance) && count($upcomingMaintenance) > 0)
                    @foreach($upcomingMaintenance as $window)
                        <div class="bg-white p-4 border-2 border-amber-500 mb-5 flex items-start gap-3">
                            <span class="font-mono text-[10px] font-bold uppercase px-2 py-1 bg-amber-100 text-amber-950 border border-amber-400 shrink-0">
                                Maintenance
                            </span>
                            <div class="min-w-0">
                                <div class="font-display text-sm font-bold text-ink">{{ $window->title }}</div>
                                <p class="text-xs text-muted mt-0.5 leading-relaxed">
                                    Terjadwal {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} &ndash; {{ $window->ends_at->timezone('Asia/Jakarta')->format('H:i') }} WIB.
                                    @if($window->description) {{ $window->description }} @endif
                                </p>
                            </div>
                        </div>
                    @endforeach
                @endif

                @yield('content')
            </main>

            <footer class="py-4 px-5 sm:px-8 border-t border-hairline font-mono text-[11px] uppercase tracking-wider text-muted shrink-0">
                &copy; 2026 CravaCloud — Cloud Infrastructure Platform
            </footer>
        </div>
    </div>

    @include('partials.invoice-modal')
    @stack('scripts')
</body>
</html>
