@extends('layouts.auth', ['title' => 'CravaCloud Masuk ke Akun'])

@push('head')
<style>
    html, body {
        height: 100%;
        overflow: hidden;
    }
</style>
@endpush

@section('content')
<div class="flex-1 h-full min-h-0 grid grid-cols-1 lg:grid-cols-12 overflow-hidden">

    @include('auth.partials.editorial-aside', [
        'heading' => 'KELOLA INFRASTRUKTUR CLOUD DALAM SATU PORTAL',
    ])

    <!-- Right Column: Form Panel -->
    <div class="lg:col-span-7 h-full flex flex-col px-5 sm:px-10 lg:px-14 py-4 sm:py-6 bg-cream overflow-hidden">

        <!-- Top Utility Bar -->
        <div class="flex items-center justify-between lg:justify-end border-b border-hairline pb-3 shrink-0">
            <a href="{{ route('home') }}" class="inline-flex items-center space-x-2.5 lg:hidden">
                <img src="{{ asset('images/logo.svg') }}"
                     onerror="this.src='{{ asset('images/logo.png') }}'"
                     alt="CravaCloud"
                     class="h-7 w-7 object-contain">
                <span class="font-display font-extrabold text-lg tracking-tighter text-ink uppercase">CRAVACLOUD</span>
            </a>
            <a href="{{ auth()->check() ? route('dashboard.index') : route('home') }}"
               class="inline-flex items-center gap-2 font-mono text-xs font-semibold uppercase tracking-wider text-ink hover:text-electric transition-colors">
                <span>← Kembali ke {{ auth()->check() ? 'Dashboard' : 'Beranda' }}</span>
            </a>
        </div>

        <!-- Center Card -->
        <div class="my-auto">
            <div class="w-full max-w-md mx-auto bg-white border-2 border-ink p-5 sm:p-6 shadow-sm">
            <div class="mb-4">
                <h1 class="font-display font-bold text-2xl sm:text-3xl tracking-tight text-ink uppercase">
                    Masuk ke Portal
                </h1>
            </div>

            @if(session('success'))
                <x-ui.alert type="success" class="mb-4">{{ session('success') }}</x-ui.alert>
            @endif
            @if(session('info'))
                <x-ui.alert type="info" class="mb-4">{{ session('info') }}</x-ui.alert>
            @endif
            @if(session('error'))
                <x-ui.alert type="danger" class="mb-4">{{ session('error') }}</x-ui.alert>
            @endif
            @if($errors->any())
                <x-ui.alert type="danger" class="mb-4">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <!-- Google OAuth Button -->
            <a href="{{ route('auth.google') }}"
               class="w-full flex items-center justify-center gap-3 px-4 py-2.5 bg-cream hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider border border-ink transition-colors">
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.24v3.15C3.26 21.4 7.34 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.24C.45 8.15 0 9.92 0 12s.45 3.85 1.24 5.42l4.04-3.15z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.6 1.24 6.58l4.04 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                </svg>
                <span>Lanjutkan dengan Google</span>
            </a>

            <!-- Divider -->
            <div class="relative my-4">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-hairline"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-3 font-mono text-[10px] uppercase tracking-widest text-muted">atau dengan kredensial</span>
                </div>
            </div>

            <!-- Login Form -->
            <form action="{{ route('login') }}" method="POST" class="space-y-3">
                @csrf

                <div>
                    <label for="username" class="block font-mono text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Username atau Email</label>
                    <input type="text" id="username" name="username" required autofocus value="{{ old('username') }}"
                           placeholder="username atau nama@email.com"
                           class="w-full rounded-none px-3.5 py-2 bg-cream/60 border border-hairline text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric focus:bg-white transition-colors">
                </div>

                <div x-data="{ showPassword: false }">
                    <div class="flex justify-between items-center mb-1">
                        <label for="password" class="font-mono text-[11px] font-bold uppercase tracking-wider text-muted">Password</label>
                        <a href="{{ route('password.request') }}" class="font-mono text-[11px] text-electric font-bold hover:underline">Lupa Password?</a>
                    </div>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required placeholder="Masukkan password akun"
                               class="w-full rounded-none px-3.5 py-2 pr-10 bg-cream/60 border border-hairline text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric focus:bg-white transition-colors">
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-muted hover:text-ink focus:outline-none cursor-pointer"
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

                <!-- Terms and Privacy Checkbox -->
                <div>
                    <input type="hidden" name="terms" value="0">
                    <label for="agree_terms" class="flex items-start gap-2.5 cursor-pointer select-none group">
                        <input type="checkbox"
                               id="agree_terms"
                               name="terms"
                               value="1"
                               {{ old('terms') ? 'checked' : '' }}
                               required
                               oninvalid="this.setCustomValidity('Wajib menyetujui Ketentuan Layanan dan Kebijakan Privasi untuk masuk.')"
                               oninput="this.setCustomValidity('')"
                               class="mt-0.5 h-4 w-4 rounded-none border-ink text-electric focus:ring-electric cursor-pointer shrink-0">
                        <span class="text-xs leading-snug text-muted group-hover:text-ink transition-colors">
                            Saya menyetujui
                            <a href="{{ route('terms') }}" target="_blank" class="font-semibold text-ink hover:text-electric underline underline-offset-2">Ketentuan Layanan</a>
                            &amp;
                            <a href="{{ route('privacy') }}" target="_blank" class="font-semibold text-ink hover:text-electric underline underline-offset-2">Kebijakan Privasi</a>
                            CravaCloud. <span class="text-rose-700 font-bold" title="Wajib disetujui">*</span>
                        </span>
                    </label>
                    @error('terms')
                        <p class="mt-1 font-mono text-xs text-rose-700 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Cloudflare Turnstile Widget -->
                @if(config('services.turnstile.key'))
                    <div class="my-2 flex flex-col items-center justify-center">
                        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.key') }}" data-theme="light"></div>
                        @error('cf-turnstile-response')
                            <p class="mt-1 font-mono text-xs text-rose-700 text-center font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <button type="submit"
                        class="w-full py-3 px-5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider rounded-none flex items-center justify-between transition-colors cursor-pointer">
                    <span>Masuk ke Dashboard</span>
                    <span>↗</span>
                </button>
            </form>

            <p class="mt-4 pt-3.5 border-t border-hairline text-center font-mono text-xs text-muted">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-bold text-electric hover:underline">Daftar Sekarang →</a>
            </p>
            </div>
        </div>

    </div>
</div>
@endsection

@if(config('services.turnstile.key'))
    @push('scripts')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endpush
@endif
