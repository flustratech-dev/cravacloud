@extends('layouts.auth', ['title' => 'Masuk ke Akun — CravaCloud'])

@section('content')
<div class="min-h-screen lg:h-screen w-full flex flex-col lg:flex-row bg-[#F8FAFC] dark:bg-[#09090b] p-3 sm:p-4 lg:p-5 xl:p-6 overflow-y-auto lg:overflow-hidden">

    <!-- Left Column: Midtrans-style Floating Card (Prominent Wide Card, rocket-focused) -->
    <div class="hidden lg:flex lg:w-[48%] xl:w-[48%] 2xl:w-[48%] rounded-[2rem] bg-gradient-to-br from-[#1E3B66] via-[#2A5086] to-[#142846] dark:from-zinc-900 dark:via-zinc-900 dark:to-zinc-950 text-white p-6 xl:p-10 flex-col items-center justify-center relative overflow-hidden shadow-2xl border border-slate-700/30 dark:border-zinc-800 select-none">
        <!-- Ambient 3D Glowing Orbs -->
        <div class="pointer-events-none absolute -top-16 -left-16 w-80 h-80 rounded-full bg-gradient-to-br from-[#CADFF8] via-[#8BB5E8] to-[#4A6FA5] opacity-25 dark:opacity-10 filter blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-16 -right-16 w-80 h-80 rounded-full bg-gradient-to-tr from-[#7EA7DB] via-[#A8C7F0] to-[#E3EFFF] opacity-20 dark:opacity-10 filter blur-3xl"></div>
        <div class="pointer-events-none absolute top-1/2 -right-12 -translate-y-1/2 w-64 h-64 rounded-full bg-gradient-to-bl from-[#9BBFE6] via-[#658EC4] to-[#3B5F90] opacity-20 dark:opacity-10 filter blur-3xl"></div>

        <!-- Headline above rocket -->
        <div class="relative z-10 text-center mb-4 sm:mb-6 px-4">
            <h2 class="text-2xl sm:text-3xl xl:text-4xl 2xl:text-5xl font-black tracking-tight text-white leading-tight drop-shadow-md uppercase">
                CLOUD VPS
            </h2>
            <p class="text-xl sm:text-2xl xl:text-3xl 2xl:text-4xl font-black tracking-tight text-white leading-tight drop-shadow-md mt-2 sm:mt-3 uppercase">
                PERFORMA ENTERPRISE HARGA MAHASISWA
            </p>
        </div>

        <!-- Centerpiece: Animated Rocket Art (Enlarged) -->
        <div class="relative z-10 w-full flex items-center justify-center py-2">

            <!-- Vector Rocket Art with Animations (Adapted from Hero) -->
            <svg viewBox="0 0 500 520"
                 class="w-full h-auto max-w-[320px] lg:max-w-[360px] xl:max-w-[420px] 2xl:max-w-[460px] mx-auto filter drop-shadow-[0_25px_45px_rgba(0,0,0,0.4)]"
                 fill="none"
                 xmlns="http://www.w3.org/2000/svg"
                 role="img">
                <defs>
                    <linearGradient id="vhBlobGradAuth" x1="150" y1="50" x2="450" y2="520" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#4A6FA5" />
                        <stop offset="50%" stop-color="#3A5D8E" />
                        <stop offset="100%" stop-color="#233E65" />
                    </linearGradient>

                    <linearGradient id="vhRocketBodyGradAuth" x1="210" y1="150" x2="290" y2="350" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#FFFFFF" />
                        <stop offset="35%" stop-color="#F0F4FA" />
                        <stop offset="70%" stop-color="#DCE6F5" />
                        <stop offset="100%" stop-color="#C2D6EE" />
                    </linearGradient>

                    <linearGradient id="vhRocketShadowAuth" x1="250" y1="150" x2="290" y2="150" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="rgba(0,0,0,0)" />
                        <stop offset="100%" stop-color="rgba(20,40,70,0.25)" />
                    </linearGradient>

                    <linearGradient id="vhFinGradLeftAuth" x1="180" y1="280" x2="225" y2="360" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#3A5B8A" />
                        <stop offset="100%" stop-color="#1E3658" />
                    </linearGradient>

                    <linearGradient id="vhFinGradRightAuth" x1="320" y1="280" x2="275" y2="360" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#3A5B8A" />
                        <stop offset="100%" stop-color="#1E3658" />
                    </linearGradient>

                    <radialGradient id="vhWindowGlowAuth" cx="50%" cy="40%" r="60%">
                        <stop offset="0%" stop-color="#2D466E" />
                        <stop offset="70%" stop-color="#14243B" />
                        <stop offset="100%" stop-color="#0A1422" />
                    </radialGradient>

                    <linearGradient id="vhSmokeGradAuth" x1="250" y1="360" x2="250" y2="530" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#3B5F90" stop-opacity="0.8" />
                        <stop offset="40%" stop-color="#2D4C75" stop-opacity="0.95" />
                        <stop offset="100%" stop-color="#1A3353" />
                    </linearGradient>

                    <linearGradient id="vhFlameGradAuth" x1="250" y1="355" x2="250" y2="385" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#FFFFFF" />
                        <stop offset="35%" stop-color="#93C5FD" />
                        <stop offset="75%" stop-color="#3B82F6" stop-opacity="0.85" />
                        <stop offset="100%" stop-color="#1D4ED8" stop-opacity="0" />
                    </linearGradient>
                </defs>

                <!-- 1. Background Blob (Breathing Parallax) -->
                <g class="vh-blob-group">
                    <path d="M 230,80 C 340,65 460,110 470,220 C 480,310 440,360 455,440 C 468,505 400,535 340,530 C 270,525 220,535 150,520 C 80,505 105,430 140,370 C 180,300 130,220 150,150 C 168,88 190,85 230,80 Z" fill="url(#vhBlobGradAuth)" />
                    <path d="M 250,95 C 335,82 435,125 448,220 C 458,300 422,345 435,420 C 438,435 432,450 420,465 C 380,400 405,300 395,225 C 382,130 305,98 250,95 Z" fill="white" opacity="0.12" />
                </g>

                <!-- 2. Sparkles -->
                <g class="vh-sparkle pointer-events-none" style="animation-delay: 0s;">
                    <circle cx="170" cy="220" r="2.5" fill="#FFFFFF" opacity="0.75" />
                </g>
                <g class="vh-sparkle pointer-events-none" style="animation-delay: 1.2s;">
                    <circle cx="335" cy="175" r="3" fill="#FFFFFF" opacity="0.85" />
                </g>

                <!-- 3. Rocket Flight Unit -->
                <g class="vh-rocket-group">
                    <g class="vh-smoke-group">
                        <path d="M 235,370 C 230,400 215,420 220,450 C 225,480 200,510 240,525 C 255,530 265,515 275,510 C 290,525 310,490 290,460 C 275,435 270,400 265,370 Z" fill="url(#vhSmokeGradAuth)" />
                    </g>

                    <g class="vh-flame-group">
                        <ellipse cx="250" cy="365" rx="13" ry="18" fill="url(#vhFlameGradAuth)" filter="drop-shadow(0 0 8px rgba(96,165,250,0.85))" />
                        <ellipse cx="250" cy="362" rx="6" ry="10" fill="#FFFFFF" opacity="0.95" />
                    </g>

                    <!-- Left Fin -->
                    <path d="M 218,295 C 195,310 180,345 178,368 C 195,365 210,350 220,338 Z" fill="url(#vhFinGradLeftAuth)" />
                    <!-- Right Fin -->
                    <path d="M 282,295 C 305,310 320,345 322,368 C 305,365 290,350 280,338 Z" fill="url(#vhFinGradRightAuth)" />
                    <!-- Fuselage Body -->
                    <path d="M 250,150 C 230,195 218,255 218,340 C 232,348 268,348 282,340 C 282,255 270,195 250,150 Z" fill="url(#vhRocketBodyGradAuth)" filter="drop-shadow(0 6px 12px rgba(0,0,0,0.18))" />
                    <path d="M 250,150 C 260,195 275,255 282,340 C 265,345 250,345 250,345 C 250,255 250,195 250,150 Z" fill="url(#vhRocketShadowAuth)" />
                    <path d="M 250,150 C 246,162 242,180 240,200 C 244,198 252,198 256,200 C 255,180 252,162 250,150 Z" fill="white" opacity="0.55" />

                    <!-- Stripes -->
                    <path d="M 221,245 C 236,252 264,252 279,245 L 278,258 C 263,265 237,265 222,258 Z" fill="white" />
                    <path d="M 218,285 C 235,293 265,293 282,285 L 282,298 C 265,306 235,306 218,298 Z" fill="white" />

                    <!-- Portholes -->
                    <circle cx="250" cy="222" r="16" fill="white" />
                    <circle cx="250" cy="222" r="12" fill="url(#vhWindowGlowAuth)" />
                    <path d="M 243,215 A 8 8 0 0 1 257 215 A 8 8 0 0 0 243 215 Z" fill="white" opacity="0.45" />

                    <circle cx="250" cy="268" r="16" fill="white" />
                    <circle cx="250" cy="268" r="12" fill="url(#vhWindowGlowAuth)" />
                    <path d="M 243,261 A 8 8 0 0 1 257 261 A 8 8 0 0 0 243 261 Z" fill="white" opacity="0.45" />

                    <!-- Nozzle -->
                    <path d="M 238,344 L 235,358 C 245,362 255,362 265,358 L 262,344 Z" fill="#233B5E" />
                </g>
            </svg>
        </div>
    </div>

    <!-- Right Column: Login Form -->
    <div class="w-full lg:w-[52%] xl:w-[52%] 2xl:w-[52%] flex flex-col justify-between px-6 py-4 sm:px-10 lg:px-8 xl:px-12 h-full overflow-y-auto">

        <!-- Top Header: Logo CravaCloud centered & aligned with top of the card -->
        <div class="flex justify-center pt-2 sm:pt-3 xl:pt-4 shrink-0">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3 group">
                <img src="{{ asset('images/logo.png') }}" alt="CravaCloud" class="h-10 sm:h-11 w-auto object-contain transition-transform group-hover:scale-105">
                <span class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">Crava<span class="text-[#4A6FA5]">Cloud</span></span>
            </a>
        </div>

        <!-- Center: Form Box (Compact & Clean Size) -->
        <div class="w-full max-w-[370px] sm:max-w-[390px] mx-auto my-auto py-2">

            <!-- Title -->
            <div class="mb-4 text-center">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">Masuk ke Akun Anda</h1>
            </div>

            <!-- Flash Alerts -->
            @if(session('success'))
                <div class="mb-3 p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('info'))
                <div class="mb-3 p-2.5 rounded-lg bg-blue-50 border border-blue-200 text-xs text-blue-800">
                    {{ session('info') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-3 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-3 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Google OAuth Button -->
            <a href="{{ route('auth.google') }}"
               class="w-full flex items-center justify-center gap-2.5 px-4 py-2 sm:py-2.5 bg-white hover:bg-slate-50 text-slate-800 text-xs sm:text-sm font-semibold border border-slate-300 rounded-lg shadow-2xs hover:shadow-xs transition-all">
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.24v3.15C3.26 21.4 7.34 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.24C.45 8.15 0 9.92 0 12s.45 3.85 1.24 5.42l4.04-3.15z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.6 1.24 6.58l4.04 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                </svg>
                <span>Lanjutkan dengan Google</span>
            </a>

            <!-- Divider -->
            <div class="relative my-3 sm:my-3.5">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-200"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase tracking-wider">
                    <span class="bg-[#F8FAFC] dark:bg-[#09090b] px-2.5 text-slate-400 dark:text-zinc-400 font-medium text-[11px]">atau dengan email</span>
                </div>
            </div>

            <!-- Login Form -->
            <form action="{{ route('login') }}" method="POST" class="space-y-3 sm:space-y-3.5">
                @csrf

                <div>
                    <label for="username" class="block text-[11px] sm:text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Username atau Email</label>
                    <input type="text" id="username" name="username" required autofocus value="{{ old('username') }}"
                           placeholder="username atau nama@email.com"
                           class="w-full px-3.5 py-2 sm:py-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#4A6FA5] focus:bg-white transition-all">
                </div>

                <div x-data="{ showPassword: false }">
                    <div class="flex justify-between items-center mb-1">
                        <label for="password" class="text-[11px] sm:text-xs font-bold text-slate-700 uppercase tracking-wider">Password</label>
                        <a href="{{ route('password.request') }}" class="text-[11px] sm:text-xs text-[#4A6FA5] font-semibold hover:underline">Lupa Password?</a>
                    </div>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required placeholder="Masukkan password akun"
                               class="w-full px-3.5 py-2 sm:py-2.5 pr-10 bg-slate-50 border border-slate-300 rounded-lg text-xs sm:text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#4A6FA5] focus:bg-white transition-all">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 focus:outline-none"
                                tabindex="-1"
                                title="Tampilkan/sembunyikan password">
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Terms and Privacy Checkbox (Wajib / Required) -->
                <div>
                    <input type="hidden" name="terms" value="0">
                    <label for="agree_terms" class="flex items-start gap-2 cursor-pointer select-none group">
                        <input type="checkbox"
                               id="agree_terms"
                               name="terms"
                               value="1"
                               {{ old('terms') ? 'checked' : '' }}
                               required
                               oninvalid="this.setCustomValidity('Wajib menyetujui Ketentuan Layanan dan Kebijakan Privasi untuk masuk.')"
                               oninput="this.setCustomValidity('')"
                               class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-[#4A6FA5] focus:ring-[#4A6FA5] focus:ring-offset-0 cursor-pointer shrink-0">
                        <span class="text-[11px] sm:text-xs leading-snug text-slate-600 group-hover:text-slate-900 transition-colors">
                            Saya menyetujui
                            <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-slate-900 hover:text-[#4A6FA5] underline underline-offset-2">Ketentuan Layanan</a>
                            &amp;
                            <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-slate-900 hover:text-[#4A6FA5] underline underline-offset-2">Kebijakan Privasi</a>
                            CravaCloud. <span class="text-rose-500 font-bold" title="Wajib disetujui">*</span>
                        </span>
                    </label>
                    @error('terms')
                        <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Cloudflare Turnstile Widget -->
                @if(config('services.turnstile.key'))
                    <div class="my-2.5 flex flex-col items-center justify-center">
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.key') }}" data-theme="light"></div>
                        @error('cf-turnstile-response')
                            <p class="mt-1 text-xs text-rose-600 text-center font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <button type="submit"
                        class="w-full py-2.5 sm:py-3 bg-[#4A6FA5] hover:bg-[#3D5E8C] text-white text-xs sm:text-sm font-bold rounded-lg transition-all shadow-sm hover:shadow-md hover:scale-[1.01] active:scale-95 cursor-pointer">
                    Masuk ke Dashboard
                </button>
            </form>

            <p class="mt-3.5 text-center text-xs text-slate-600">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-bold text-slate-900 hover:text-[#4A6FA5] transition-colors">Daftar Sekarang</a>
            </p>

            <!-- Back to Dashboard / Home Button -->
            <div class="mt-3 pt-2.5 border-t border-slate-100 text-center">
                <a href="{{ auth()->check() ? route('dashboard.index') : route('home') }}"
                   class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Kembali ke {{ auth()->check() ? 'Dashboard' : 'Beranda' }}</span>
                </a>
            </div>

        </div>

        <!-- Right Bottom: Copyright Footer -->
        <div class="pt-3 border-t border-slate-100 text-center text-xs text-slate-400 font-normal shrink-0">
            &copy; 2026 CravaCloud. All rights reserved. Created by cravacloud.
        </div>

    </div>

</div>
@endsection

@if(config('services.turnstile.key'))
    @push('scripts')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endpush
@endif

