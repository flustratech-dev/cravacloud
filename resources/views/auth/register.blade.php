@extends('layouts.auth', ['title' => 'CravaCloud Daftar Akun Baru'])

@push('head')
<style>
    @media (min-width: 1024px) {
        html, body {
            height: 100%;
            overflow: hidden;
        }
    }
</style>
@endpush

@section('content')
@php
    $googlePrefill = session('google_prefill', []);
    $prefilledName = old('full_name', $googlePrefill['full_name'] ?? '');
    $prefilledEmail = old('email', $googlePrefill['email'] ?? '');
    $googleId = old('google_id', $googlePrefill['google_id'] ?? '');
    $avatar = old('avatar', $googlePrefill['avatar'] ?? '');
@endphp

<div class="flex-1 min-h-screen lg:min-h-0 lg:h-full grid grid-cols-1 lg:grid-cols-12 lg:overflow-hidden">

    @include('auth.partials.editorial-aside', [
        'heading' => 'DEPLOY SERVER NVME DALAM 60 DETIK',
    ])

    <!-- Right Column: Registration Panel -->
    <div class="lg:col-span-7 lg:h-full flex flex-col px-5 sm:px-10 lg:px-12 py-4 sm:py-6 bg-cream lg:overflow-hidden">

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

        <!-- Center Form Card -->
        <div class="my-auto">
            <div class="w-full max-w-lg mx-auto bg-white border-2 border-ink p-5 sm:p-6 shadow-sm">
            <div class="mb-4">
                <h1 class="font-display font-bold text-2xl sm:text-3xl tracking-tight text-ink uppercase">
                    Buat Akun Baru
                </h1>
            </div>

            @if(session('info'))
                <x-ui.alert type="info" class="mb-4">{{ session('info') }}</x-ui.alert>
            @endif
            @if(session('success'))
                <x-ui.alert type="success" class="mb-4">{{ session('success') }}</x-ui.alert>
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
                <span>Daftar Cepat dengan Google</span>
            </a>

            <!-- Divider -->
            <div class="relative my-3.5">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-hairline"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-3 font-mono text-[10px] uppercase tracking-widest text-muted">atau lengkapi formulir</span>
                </div>
            </div>

            <!-- Register Form with Alpine State -->
            <form action="{{ route('register') }}" method="POST" class="space-y-2.5"
                  x-data="{
                      password: '',
                      confirmPassword: '',
                      showPassword: false,
                      showConfirm: false,
                      get hasLength() { return this.password.length >= 8; },
                      get hasCase() { return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password); },
                      get hasNumber() { return /\d/.test(this.password); },
                      get hasSymbol() { return /[^A-Za-z0-9]/.test(this.password); },
                      get strength() {
                          if (!this.password) return 0;
                          let s = 0;
                          if (this.hasLength) s++;
                          if (this.hasCase) s++;
                          if (this.hasNumber) s++;
                          if (this.hasSymbol) s++;
                          return s;
                      },
                      get label() {
                          if (!this.password) return 'Belum diisi';
                          switch(this.strength) {
                              case 1: return 'Sangat Lemah';
                              case 2: return 'Cukup';
                              case 3: return 'Kuat';
                              case 4: return 'Sangat Kuat';
                              default: return 'Terlalu Pendek';
                          }
                      },
                      get labelColor() {
                          switch(this.strength) {
                              case 1: return 'text-rose-700';
                              case 2: return 'text-amber-700';
                              case 3: return 'text-electric';
                              case 4: return 'text-emerald-700';
                              default: return 'text-muted';
                          }
                      }
                  }">
                @csrf

                @if($googleId)
                    <input type="hidden" name="google_id" value="{{ $googleId }}">
                    <input type="hidden" name="avatar" value="{{ $avatar }}">
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="full_name" class="block font-mono text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Nama Lengkap *</label>
                        <input type="text" id="full_name" name="full_name" required autofocus value="{{ $prefilledName }}"
                               placeholder="Nama lengkap Anda"
                               class="w-full rounded-none px-3 py-2 bg-cream/60 border border-hairline text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric focus:bg-white transition-colors">
                    </div>

                    <div>
                        <label for="username" class="block font-mono text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Username *</label>
                        <input type="text" id="username" name="username" required value="{{ old('username') }}"
                               placeholder="contoh: daffa_dev"
                               class="w-full rounded-none px-3 py-2 bg-cream/60 border border-hairline text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric focus:bg-white transition-colors">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="email" class="font-mono text-[11px] font-bold uppercase tracking-wider text-muted">Alamat Email *</label>
                            @if($googleId)
                                <span class="font-mono text-[9px] font-bold uppercase bg-emerald-100 text-emerald-900 border border-emerald-400 px-1.5 py-0.5">Google</span>
                            @endif
                        </div>
                        <input type="email" id="email" name="email" required value="{{ $prefilledEmail }}"
                               placeholder="nama@email.com"
                               class="w-full rounded-none px-3 py-2 bg-cream/60 border border-hairline text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric focus:bg-white transition-colors">
                    </div>

                    <div>
                        <label for="phone" class="block font-mono text-[11px] font-bold uppercase tracking-wider text-muted mb-1">Nomor WA <span class="text-muted/70 font-normal lowercase">(opsional)</span></label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                               placeholder="081234567890"
                               class="w-full rounded-none px-3 py-2 bg-cream/60 border border-hairline text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric focus:bg-white transition-colors">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="password" class="font-mono text-[11px] font-bold uppercase tracking-wider text-muted">Password *</label>
                            <span class="font-mono text-[10px] font-bold" :class="labelColor" x-text="label">Belum diisi</span>
                        </div>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required minlength="8"
                                   x-model="password"
                                   placeholder="Min. 8 karakter"
                                   class="w-full rounded-none px-3 py-2 pr-9 bg-cream/60 border border-hairline text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric focus:bg-white transition-colors">
                            <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center px-2.5 text-muted hover:text-ink cursor-pointer"
                                    tabindex="-1">
                                <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                </svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-4 gap-1 mt-1.5">
                            <div class="h-1 transition-all duration-300" :class="strength >= 1 ? (strength === 1 ? 'bg-rose-600' : (strength === 2 ? 'bg-amber-600' : (strength === 3 ? 'bg-electric' : 'bg-emerald-700'))) : 'bg-hairline'"></div>
                            <div class="h-1 transition-all duration-300" :class="strength >= 2 ? (strength === 2 ? 'bg-amber-600' : (strength === 3 ? 'bg-electric' : 'bg-emerald-700')) : 'bg-hairline'"></div>
                            <div class="h-1 transition-all duration-300" :class="strength >= 3 ? (strength === 3 ? 'bg-electric' : 'bg-emerald-700') : 'bg-hairline'"></div>
                            <div class="h-1 transition-all duration-300" :class="strength >= 4 ? 'bg-emerald-700' : 'bg-hairline'"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="password_confirmation" class="font-mono text-[11px] font-bold uppercase tracking-wider text-muted">Konfirmasi Password *</label>
                            <template x-if="confirmPassword">
                                <span class="font-mono text-[10px] font-bold"
                                      :class="password === confirmPassword ? 'text-emerald-700' : 'text-rose-700'"
                                      x-text="password === confirmPassword ? 'Cocok' : 'Beda'"></span>
                            </template>
                        </div>
                        <div class="relative">
                            <input :type="showConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required minlength="8"
                                   x-model="confirmPassword"
                                   placeholder="Ulangi password"
                                   class="w-full rounded-none px-3 py-2 pr-9 bg-cream/60 border border-hairline text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric focus:bg-white transition-colors">
                            <button type="button" @click="showConfirm = !showConfirm"
                                    class="absolute inset-y-0 right-0 flex items-center px-2.5 text-muted hover:text-ink cursor-pointer"
                                    tabindex="-1">
                                <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Password Requirements -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 font-mono text-[10px] text-muted pt-0.5">
                    <span class="flex items-center gap-1" :class="hasLength ? 'text-emerald-700 font-bold' : ''">
                        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Min. 8 karakter
                    </span>
                    <span class="flex items-center gap-1" :class="hasCase ? 'text-emerald-700 font-bold' : ''">
                        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        A-Z &amp; a-z
                    </span>
                    <span class="flex items-center gap-1" :class="hasNumber ? 'text-emerald-700 font-bold' : ''">
                        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Angka
                    </span>
                    <span class="flex items-center gap-1" :class="hasSymbol ? 'text-emerald-700 font-bold' : ''">
                        <svg class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Simbol
                    </span>
                </div>

                <!-- Terms and Privacy -->
                <div>
                    <input type="hidden" name="terms" value="0">
                    <label for="agree_terms_register" class="flex items-start gap-2.5 cursor-pointer select-none group">
                        <input type="checkbox"
                               id="agree_terms_register"
                               name="terms"
                               value="1"
                               {{ old('terms') ? 'checked' : '' }}
                               required
                               oninvalid="this.setCustomValidity('Wajib menyetujui Ketentuan Layanan dan Kebijakan Privasi untuk mendaftar.')"
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

                <button type="submit"
                        class="w-full py-3 px-5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider rounded-none flex items-center justify-between transition-colors cursor-pointer">
                    <span>Buat Akun Sekarang</span>
                    <span>↗</span>
                </button>
            </form>

            <p class="mt-4 pt-3.5 border-t border-hairline text-center font-mono text-xs text-muted">
                Sudah memiliki akun?
                <a href="{{ route('login') }}" class="font-bold text-electric hover:underline">Masuk ke Akun →</a>
            </p>
            </div>
        </div>

    </div>
</div>
@endsection
