<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Client Portal' }} — CravaCloud</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-192x192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>
        (function() {
            var theme = localStorage.getItem('theme');
            var isDark = theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
        })();
    </script>
    <style>
        [x-cloak] { display: none !important; }
        html.dark { color-scheme: dark; background-color: #09090b; }
        html.dark body { background-color: #09090b; color: #fafafa; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="h-full antialiased bg-white dark:bg-[#09090b] text-slate-900 dark:text-zinc-100" x-data="{ sidebarOpen: false, userDropdown: false }">
    @include('partials.impersonation-banner')
    <div class="h-screen w-full flex flex-col md:flex-row overflow-hidden bg-white dark:bg-[#09090b]">

        <div x-show="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-xs md:hidden" @click="sidebarOpen = false" style="display:none;"></div>

        <!-- Sidebar (Stationary on Desktop, Border-r) -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 w-full md:w-64 bg-white dark:bg-zinc-950 border-r border-slate-200 dark:border-zinc-800 flex flex-col transition-transform duration-200 md:static md:inset-auto md:h-full md:shrink-0 md:translate-x-0">

            <!-- Logo -->
            <div class="flex h-20 shrink-0 items-center gap-3.5 px-5">
                <a href="{{ route('dashboard.index') }}" class="flex min-w-0 items-center gap-3.5">
                    <img src="{{ asset('images/logo.png') }}" alt="CravaCloud" class="h-11 w-auto shrink-0 object-contain">
                    <span class="truncate text-lg font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">CravaCloud</span>
                </a>
                <button @click="sidebarOpen = false" class="ml-auto md:hidden p-1 rounded-md text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors" aria-label="Tutup Menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 px-3 py-3.5 space-y-1 overflow-y-auto">
                <a href="{{ route('dashboard.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('dashboard.index') || request()->routeIs('dashboard.vps.*') ? 'bg-slate-100 dark:bg-zinc-900 text-slate-900 dark:text-white font-semibold' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium' }}">
                    <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                    <span>VPS</span>
                </a>

                <a href="{{ route('dashboard.billing') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('dashboard.billing') ? 'bg-slate-100 dark:bg-zinc-900 text-slate-900 dark:text-white font-semibold' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium' }}">
                    <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <span>Tagihan & Invoice</span>
                </a>

                <a href="{{ route('dashboard.support') }}"
                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('dashboard.support*') ? 'bg-slate-100 dark:bg-zinc-900 text-slate-900 dark:text-white font-semibold' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        <span>Tiket Bantuan</span>
                    </div>
                    @php $userOpenTickets = $userOpenTickets ?? 0; @endphp
                    @if($userOpenTickets > 0)
                        <span class="text-[11px] font-bold bg-black dark:bg-zinc-800 text-white px-1.5 py-0.5 rounded leading-none">{{ $userOpenTickets }}</span>
                    @endif
                </a>

                <a href="{{ route('dashboard.settings') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('dashboard.settings') ? 'bg-slate-100 dark:bg-zinc-900 text-slate-900 dark:text-white font-semibold' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium' }}">
                    <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Pengaturan Akun</span>
                </a>

                <a href="{{ route('organizations.index') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('organizations.*') ? 'bg-slate-100 dark:bg-zinc-900 text-slate-900 dark:text-white font-semibold' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium' }}">
                    <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V4H2v16h5m10 0v-4H7v4m10 0H7m3-8h4m-6-4h8"/></svg>
                    <span>Organisasi</span>
                </a>

                <a href="{{ route('security.settings') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('security.*') ? 'bg-slate-100 dark:bg-zinc-900 text-slate-900 dark:text-white font-semibold' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium' }}">
                    <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>Keamanan</span>
                </a>

                <div class="pt-3 pb-1 px-3">
                    <p class="text-[11px] font-semibold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Layanan</p>
                </div>

                <a href="{{ route('home') }}#pricing"
                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium group">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                        <span>Tambah VPS</span>
                    </div>
                    <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-700 dark:group-hover:text-zinc-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7V17"/></svg>
                </a>

                <a href="{{ route('home') }}#ai-packages"
                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium group">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span>Tambah AI Agent</span>
                    </div>
                    <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-700 dark:group-hover:text-zinc-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7V17"/></svg>
                </a>

                <a href="{{ route('home') }}#database-packages"
                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium group">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        <span>Tambah Database</span>
                    </div>
                    <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-700 dark:group-hover:text-zinc-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7V17"/></svg>
                </a>

                <div class="pt-3 pb-1 px-3">
                    <p class="text-[11px] font-semibold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Layanan Lainnya</p>
                </div>

                <a href="https://wa.cravacloud.id" target="_blank" rel="noopener noreferrer"
                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium group">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span>WA Gateway</span>
                    </div>
                    <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-700 dark:group-hover:text-zinc-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7V17"/></svg>
                </a>

                <a href="https://build.cravacloud.id" target="_blank" rel="noopener noreferrer"
                   class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-900/70 hover:text-slate-900 dark:hover:text-white font-medium group">
                    <div class="flex items-center gap-3">
                        <svg class="w-4.5 h-4.5 text-slate-500 dark:text-zinc-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                        <span>Jasa Web</span>
                    </div>
                    <svg class="w-3.5 h-3.5 text-slate-400 group-hover:text-slate-700 dark:group-hover:text-zinc-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7V17"/></svg>
                </a>
            </nav>

            <!-- Sidebar Footer: Keluar Akun -->
            <div class="shrink-0 border-t border-slate-200 dark:border-zinc-800 p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors cursor-pointer group">
                        <svg class="w-4.5 h-4.5 shrink-0 text-rose-500 dark:text-rose-400 group-hover:text-rose-600 dark:group-hover:text-rose-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>Keluar Akun</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Area (Independent Scroll) -->
        <div class="flex-1 flex flex-col min-w-0 h-full overflow-y-auto">
            <!-- Header with divider border -->
            <header class="h-16 shrink-0 bg-white dark:bg-zinc-950 border-b border-slate-200 dark:border-zinc-800 flex items-center justify-between px-6 sm:px-10">
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Mobile Hamburger Button on the Left -->
                    <button @click="sidebarOpen = true" class="md:hidden p-2 rounded-lg text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 -ml-2" aria-label="Buka Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="text-base font-bold text-slate-900 dark:text-white">{{ $headerTitle ?? 'Dashboard' }}</h1>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Theme Toggle in Header -->
                    @include('partials.theme-toggle')

                    <!-- User Profile Dropdown in Header -->
                    <div class="relative" @click.away="userDropdown = false">
                        <button @click="userDropdown = !userDropdown" class="flex items-center p-1 rounded-full hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors focus:outline-none cursor-pointer" title="{{ auth()->user()->full_name }}">
                            <div class="rounded-full bg-slate-100 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 shrink-0"
                                 style="width: 32px; height: 32px; min-width: 32px; min-height: 32px; border-radius: 9999px; aspect-ratio: 1 / 1;">
                                <svg class="text-slate-700 dark:text-zinc-300 shrink-0" style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </button>

                        <div x-show="userDropdown" x-transition class="absolute right-0 mt-2 w-60 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-lg shadow-lg py-1.5 z-50 text-sm" style="display:none;">
                            <div class="px-4 py-3 border-b border-slate-100 dark:border-zinc-800 flex items-center gap-3">
                                <div class="rounded-full bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 shrink-0"
                                     style="width: 40px; height: 40px; min-width: 40px; min-height: 40px; border-radius: 9999px; aspect-ratio: 1 / 1;">
                                    <svg class="text-slate-700 dark:text-zinc-300 shrink-0" style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-900 dark:text-white truncate">{{ auth()->user()->full_name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 font-mono-code truncate">{{ auth()->user()->email }}</p>
                                </div>
                            </div>
                            <a href="{{ route('dashboard.settings') }}" class="block px-4 py-2 text-slate-700 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-800">Pengaturan Akun</a>
                            @if(auth()->user()->is_admin)
                                <a href="{{ route('admin.index') }}" class="block px-4 py-2 text-slate-700 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-800 font-medium">Panel Admin</a>
                            @endif
                            <a href="{{ route('home') }}" class="block px-4 py-2 text-slate-700 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-800">Halaman Utama</a>
                            <div class="border-t border-slate-100 dark:border-zinc-800 my-1"></div>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-slate-700 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-800 font-medium cursor-pointer">Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Alerts -->
            <div class="px-6 sm:px-10 pt-4">
                @if(session('success'))
                    <div x-data="{ show: true }"
                         x-show="show"
                         x-init="setTimeout(() => show = false, 4000)"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-950/40 p-3 rounded-lg text-sm text-emerald-800 dark:text-emerald-300 mb-4 flex items-center justify-between shadow-xs">
                        <span>{{ session('success') }}</span>
                        <button @click="show = false" class="text-emerald-500 hover:text-emerald-800 dark:hover:text-emerald-200 p-1 text-base leading-none">&times;</button>
                    </div>
                @endif
                @if(session('error'))
                    <div x-data="{ show: true }"
                         x-show="show"
                         x-init="setTimeout(() => show = false, 5000)"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="border border-rose-200 dark:border-rose-800/50 bg-rose-50 dark:bg-rose-950/40 p-3 rounded-lg text-sm text-rose-800 dark:text-rose-300 mb-4 flex items-center justify-between shadow-xs">
                        <span>{{ session('error') }}</span>
                        <button @click="show = false" class="text-rose-500 hover:text-rose-800 dark:hover:text-rose-200 p-1 text-base leading-none">&times;</button>
                    </div>
                @endif
                @if(session('warning'))
                    <div x-data="{ show: true }"
                         x-show="show"
                         x-init="setTimeout(() => show = false, 5000)"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="border border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-950/40 p-3 rounded-lg text-sm text-amber-800 dark:text-amber-300 mb-4 flex items-center justify-between shadow-xs">
                        <span>{{ session('warning') }}</span>
                        <button @click="show = false" class="text-amber-500 hover:text-amber-800 dark:hover:text-amber-200 p-1 text-base leading-none">&times;</button>
                    </div>
                @endif
                @if(session('info'))
                    <div x-data="{ show: true }"
                         x-show="show"
                         x-init="setTimeout(() => show = false, 4000)"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="border border-blue-200 dark:border-zinc-700 bg-blue-50 dark:bg-zinc-800 p-3 rounded-lg text-sm text-blue-800 dark:text-zinc-200 mb-4 flex items-center justify-between shadow-xs">
                        <span>{{ session('info') }}</span>
                        <button @click="show = false" class="text-blue-500 hover:text-blue-800 dark:text-zinc-400 dark:hover:text-zinc-200 p-1 text-base leading-none">&times;</button>
                    </div>
                @endif
            </div>

            <!-- Main Page Body -->
            <main class="flex-1 px-6 sm:px-10 pb-12 pt-2">
                {{-- PHASE 1 - Spanduk pemberitahuan maintenance terjadwal.
                     Muncul otomatis H-notice_days sebelum jadwal berjalan. --}}
                @if(!empty($upcomingMaintenance) && count($upcomingMaintenance) > 0)
                    @foreach($upcomingMaintenance as $window)
                        <div class="bg-white dark:bg-zinc-900 p-4 rounded-lg border border-amber-200 dark:border-amber-800/50 mb-4">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800/50 shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600 dark:bg-amber-400"></span>
                                    Maintenance
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-slate-900 dark:text-white">{{ $window->title }}</div>
                                    <p class="text-xs text-slate-600 dark:text-zinc-400 mt-0.5 leading-relaxed">
                                        Terjadwal
                                        {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }}
                                        &ndash;
                                        {{ $window->ends_at->timezone('Asia/Jakarta')->format('H:i') }} WIB.
                                        @if($window->description) {{ $window->description }} @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="py-4 px-6 sm:px-10 text-xs text-slate-400 dark:text-zinc-500 shrink-0">
                &copy; 2026 CravaCloud
            </footer>
        </div>
    </div>
    @include('partials.invoice-modal')
    @stack('scripts')
</body>
</html>
