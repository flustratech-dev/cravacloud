<div class="border border-hairline bg-white p-5 sm:p-8 space-y-6">
    <div class="border-b border-hairline pb-4 flex items-center justify-between gap-4">
        <div>
            <h2 class="font-display text-xl sm:text-2xl font-bold text-ink tracking-tight">Informasi Akun &amp; Kontak Server</h2>
        </div>
        <span class="w-8 h-8 bg-electric text-white font-mono text-xs font-bold flex items-center justify-center shrink-0">02</span>
    </div>

    {{-- Authenticated User Card --}}
    <template x-if="isLoggedIn">
        <div class="space-y-4">
            <div class="p-4 sm:p-5 bg-creamDark/60 border border-hairline flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="w-9 h-9 bg-emerald-600 text-white flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="square" stroke-linejoin="miter" d="M5 13l4 4L19 7"/>
                        </svg>
                    </span>
                    <div>
                        <span class="font-mono text-[10px] uppercase tracking-wider font-bold text-emerald-700 block">Terautentikasi</span>
                        <div class="font-display font-bold text-base text-ink mt-0.5">
                            <span x-text="currentUser.full_name || currentUser.email"></span>
                        </div>
                        <div class="font-mono text-xs text-muted mt-0.5" x-text="currentUser.email"></div>
                    </div>
                </div>
                <button type="button"
                        @click="document.getElementById('checkout-logout-form').submit()"
                        class="rounded-none bg-white border border-ink hover:bg-creamDark px-4 py-2 font-mono text-[11px] font-bold uppercase tracking-wider text-ink transition-colors cursor-pointer shrink-0">
                    Ganti Akun
                </button>
            </div>

            <div class="p-4 bg-white border border-hairline space-y-2.5">
                <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block">
                    Nomor WhatsApp (Kontak Darurat &amp; Bantuan)
                </label>
                <div class="flex items-center">
                    <span class="px-3.5 py-2.5 bg-creamDark border border-r-0 border-hairline text-xs font-mono font-bold text-ink select-none">+62</span>
                    <input type="text"
                           x-model="currentUser.phone"
                           placeholder="812xxxx (Aktif WhatsApp)"
                           class="flex-1 rounded-none px-3.5 py-2.5 text-base sm:text-sm bg-white border border-hairline text-ink font-mono focus:outline-none focus:border-electric transition-colors">
                </div>
                <p class="font-mono text-[11px] text-muted">Nomor ini digunakan sebagai kontak darurat/verifikasi akun dan bantuan CS. Notifikasi layanan dikirim melalui email.</p>
            </div>
        </div>
    </template>

    {{-- Guest Flow: Register vs Quick Login --}}
    <template x-if="!isLoggedIn">
        <div class="space-y-6">
            {{-- Segmented Auth Tabs --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-hairline border border-hairline">
                <button type="button"
                        @click="authTab = 'register'"
                        :class="authTab === 'register' ? 'bg-electric text-white' : 'bg-white text-muted hover:text-ink hover:bg-creamDark/40'"
                        class="py-3 px-4 font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer text-center">
                    1. Buat Akun Baru
                </button>
                <button type="button"
                        @click="authTab = 'login'"
                        :class="authTab === 'login' ? 'bg-electric text-white' : 'bg-white text-muted hover:text-ink hover:bg-creamDark/40'"
                        class="py-3 px-4 font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer text-center">
                    2. Sudah Punya Akun (Masuk)
                </button>
            </div>

            {{-- TAB A: REGISTRASI AKUN BARU --}}
            <div x-show="authTab === 'register'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-5">
                <p class="text-xs text-muted border-l-2 border-electric pl-3">
                    Daftar cepat saat checkout. Akun Anda akan otomatis dibuat begitu pembayaran diproses.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">
                            Nama Lengkap <span class="text-red-600">*</span>
                        </label>
                        <input type="text"
                               x-model="registerFullName"
                               placeholder="Nama Lengkap Anda"
                               class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm bg-white border border-hairline text-ink focus:outline-none focus:border-electric transition-colors">
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">
                            Username <span class="text-muted font-normal">(opsional)</span>
                        </label>
                        <input type="text"
                               x-model="registerUsername"
                               placeholder="contoh: ryan_dev"
                               class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm bg-white border border-hairline text-ink font-mono focus:outline-none focus:border-electric transition-colors">
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">
                            Alamat Email <span class="text-red-600">*</span>
                        </label>
                        <input type="email"
                               x-model="registerEmail"
                               placeholder="email@domain.com"
                               class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm bg-white border border-hairline text-ink font-mono focus:outline-none focus:border-electric transition-colors">
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">
                            Nomor WhatsApp <span class="text-muted font-normal">(Kontak Darurat / Bantuan)</span>
                        </label>
                        <input type="text"
                               x-model="registerPhone"
                               placeholder="0812xxxx"
                               class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm bg-white border border-hairline text-ink font-mono focus:outline-none focus:border-electric transition-colors">
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">
                            Password Akun (min 8 karakter) <span class="text-red-600">*</span>
                        </label>
                        <div class="relative">
                            <input :type="showRegisterPassword ? 'text' : 'password'"
                                   x-model="registerPassword"
                                   placeholder="Minimal 8 karakter"
                                   class="w-full rounded-none px-3.5 py-2.5 pr-11 text-base sm:text-sm bg-white border border-hairline text-ink focus:outline-none focus:border-electric transition-colors">
                            <button type="button"
                                    @click="showRegisterPassword = !showRegisterPassword"
                                    class="absolute right-0 top-0 bottom-0 px-3 text-muted hover:text-ink transition-colors cursor-pointer"
                                    tabindex="-1"
                                    aria-label="Lihat/Sembunyikan Password">
                                <svg x-show="!showRegisterPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showRegisterPassword" class="w-4 h-4 text-electric" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">
                            Konfirmasi Password <span class="text-red-600">*</span>
                        </label>
                        <div class="relative">
                            <input :type="showRegisterConfirmPassword ? 'text' : 'password'"
                                   x-model="registerPasswordConfirmation"
                                   placeholder="Ulangi password"
                                   class="w-full rounded-none px-3.5 py-2.5 pr-11 text-base sm:text-sm bg-white border border-hairline text-ink focus:outline-none focus:border-electric transition-colors">
                            <button type="button"
                                    @click="showRegisterConfirmPassword = !showRegisterConfirmPassword"
                                    class="absolute right-0 top-0 bottom-0 px-3 text-muted hover:text-ink transition-colors cursor-pointer"
                                    tabindex="-1"
                                    aria-label="Lihat/Sembunyikan Konfirmasi Password">
                                <svg x-show="!showRegisterConfirmPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showRegisterConfirmPassword" class="w-4 h-4 text-electric" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        <p x-show="registerPassword && registerPasswordConfirmation"
                           x-text="registerPassword === registerPasswordConfirmation ? 'Password cocok' : 'Konfirmasi password tidak cocok'"
                           :class="registerPassword === registerPasswordConfirmation ? 'text-emerald-700' : 'text-red-600'"
                           class="font-mono text-[11px] mt-1.5 font-semibold"></p>
                    </div>
                </div>

                <div class="p-4 bg-creamDark/40 border border-hairline font-mono text-[11px] text-ink/80">
                    Password root server VPS tetap dapat diatur dan dilihat kapan saja secara aman dari Dashboard setelah pembayaran selesai.
                </div>

                @if(config('services.google.client_id'))
                    <div class="relative flex items-center py-2">
                        <div class="flex-grow border-t border-hairline"></div>
                        <span class="flex-shrink mx-4 font-mono text-[10px] uppercase tracking-widest text-muted font-bold">atau lanjutkan instan</span>
                        <div class="flex-grow border-t border-hairline"></div>
                    </div>

                    <div>
                        <a href="{{ route('auth.google', ['intended' => url()->current()]) }}"
                           @click="saveDraft()"
                           class="w-full rounded-none px-4 py-3 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-3 transition-colors cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.14C3.26 21.3 7.31 24 12 24z"/>
                                <path fill="#FBBC05" d="M5.28 14.24c-.24-.72-.38-1.49-.38-2.24s.14-1.52.38-2.24V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.99-3.14z"/>
                                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.99 3.14c.95-2.85 3.6-4.96 6.72-4.96z"/>
                            </svg>
                            <span>Masuk dengan Akun Google</span>
                        </a>
                        <p class="text-center font-mono text-[10px] text-muted mt-2">Konfigurasi pesanan Anda tersimpan otomatis selama proses autentikasi.</p>
                    </div>
                @endif
            </div>

            {{-- TAB B: LOGIN AKUN YANG SUDAH ADA --}}
            <div x-show="authTab === 'login'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="space-y-5">
                <p class="text-xs text-muted border-l-2 border-electric pl-3">
                    Masuk ke akun CravaCloud Anda tanpa meninggalkan halaman checkout ini.
                </p>

                <div class="p-5 sm:p-6 bg-creamDark/40 border border-hairline space-y-4">
                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">
                            Email atau Username <span class="text-red-600">*</span>
                        </label>
                        <input type="text"
                               x-model="loginIdentifier"
                               @keydown.enter.prevent="performQuickLogin()"
                               placeholder="email@domain.com atau username"
                               class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm bg-white border border-hairline text-ink font-mono focus:outline-none focus:border-electric transition-colors">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink">
                                Password Akun <span class="text-red-600">*</span>
                            </label>
                            <a href="{{ route('password.request') }}" target="_blank" class="font-mono text-[11px] text-electric hover:underline">Lupa password?</a>
                        </div>
                        <div class="relative">
                            <input :type="showLoginPassword ? 'text' : 'password'"
                                   x-model="loginPassword"
                                   @keydown.enter.prevent="performQuickLogin()"
                                   placeholder="Masukkan password akun Anda"
                                   class="w-full rounded-none px-3.5 py-2.5 pr-11 text-base sm:text-sm bg-white border border-hairline text-ink focus:outline-none focus:border-electric transition-colors">
                            <button type="button"
                                    @click="showLoginPassword = !showLoginPassword"
                                    class="absolute right-0 top-0 bottom-0 px-3 text-muted hover:text-ink transition-colors cursor-pointer"
                                    tabindex="-1"
                                    aria-label="Lihat/Sembunyikan Password">
                                <svg x-show="!showLoginPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showLoginPassword" class="w-4 h-4 text-electric" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="square" stroke-linejoin="miter" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div x-show="loginError" x-cloak class="p-3 bg-red-50 border border-red-400 text-xs font-medium text-red-700" x-text="loginError"></div>

                    <button type="button"
                            @click="performQuickLogin()"
                            :disabled="isLoggingIn"
                            class="w-full rounded-none px-4 py-3 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50">
                        <svg x-show="isLoggingIn" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="isLoggingIn ? 'Memverifikasi...' : 'Masuk &amp; Lanjutkan Checkout'"></span>
                    </button>
                </div>

                @if(config('services.google.client_id'))
                    <div>
                        <a href="{{ route('auth.google', ['intended' => url()->current()]) }}"
                           @click="saveDraft()"
                           class="w-full rounded-none px-4 py-3 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-3 transition-colors cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/>
                                <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.14C3.26 21.3 7.31 24 12 24z"/>
                                <path fill="#FBBC05" d="M5.28 14.24c-.24-.72-.38-1.49-.38-2.24s.14-1.52.38-2.24V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.99-3.14z"/>
                                <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.99 3.14c.95-2.85 3.6-4.96 6.72-4.96z"/>
                            </svg>
                            <span>Masuk dengan Akun Google</span>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>

<form id="checkout-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
    @csrf
</form>
