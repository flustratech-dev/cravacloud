@extends('layouts.dashboard', ['title' => 'Pengaturan Akun', 'headerTitle' => 'Profil & Pengaturan Akun'])

@section('content')
<div class="space-y-6 max-w-3xl">
    <!-- Profile & Account Summary -->
    <div class="bg-white border border-ink p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
            <div class="w-12 h-12 bg-electric text-white font-display font-bold text-lg flex items-center justify-center shrink-0">
                {{ strtoupper(substr($user->full_name ?: 'U', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <h2 class="font-display text-lg font-bold text-ink leading-tight truncate">{{ $user->full_name }}</h2>
                <p class="font-mono text-xs text-muted mt-0.5 truncate">{{ $user->email }}</p>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-creamDark text-ink">
                        {{ $user->channel === 'shopee' ? 'Shopee (' . ($user->shopee_order_id ?? 'N/A') . ')' : 'Website Direct' }}
                    </span>
                    <span class="font-mono text-[11px] text-muted">· Terdaftar {{ $user->created_at->timezone('Asia/Jakarta')->format('d M Y') }}</span>
                </div>
            </div>
        </div>
        <div class="text-left sm:text-right border-t sm:border-t-0 pt-3 sm:pt-0 border-hairline shrink-0">
            <span class="font-mono text-[10px] uppercase tracking-wider text-muted block">Username</span>
            <p class="font-mono font-bold text-ink text-sm">&#64;{{ $user->username }}</p>
        </div>
    </div>

    <!-- Edit Profile Form -->
    <div class="bg-white border border-hairline p-6">
        <h3 class="font-display text-base font-bold text-ink mb-4 border-b border-hairline pb-3">Informasi Profil Akun</h3>

        <form action="{{ route('dashboard.settings.profile') }}" method="POST" class="space-y-4 text-xs">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">Nama Lengkap *</label>
                    <input type="text" name="full_name" required value="{{ old('full_name', $user->full_name) }}"
                           class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                </div>

                <div>
                    <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">Alamat Email</label>
                    <input type="email" value="{{ $user->email }}" disabled
                           class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-creamDark/60 text-muted font-mono cursor-not-allowed">
                </div>

                <div>
                    <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">Nomor HP / WhatsApp</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="081234567890"
                           class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink font-mono focus:outline-none focus:border-electric">
                </div>

                <div>
                    <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">Instansi / Perusahaan</label>
                    <input type="text" name="company" value="{{ old('company', $user->company) }}" placeholder="Kampus / Perusahaan"
                           class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                </div>
            </div>

            <div>
                <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">Alamat Lengkap</label>
                <textarea name="address" rows="2" placeholder="Alamat domisili..."
                          class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">{{ old('address', $user->address) }}</textarea>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="rounded-none px-6 py-3 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password Form -->
    <div class="bg-white border border-hairline p-6">
        <h3 class="font-display text-base font-bold text-ink mb-4 border-b border-hairline pb-3">Ganti Password Akun</h3>

        <form action="{{ route('dashboard.settings.password') }}" method="POST" class="space-y-4 text-xs">
            @csrf
            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">Password Saat Ini *</label>
                    <input type="password" name="current_password" required placeholder="••••••••"
                           class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                </div>

                <div>
                    <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">Password Baru *</label>
                    <input type="password" name="new_password" required minlength="8" placeholder="Min 8 karakter"
                           class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                </div>

                <div>
                    <label class="font-mono text-[11px] font-bold uppercase tracking-wider text-ink block mb-1.5">Konfirmasi Password Baru *</label>
                    <input type="password" name="new_password_confirmation" required minlength="8" placeholder="Konfirmasi"
                           class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="rounded-none px-6 py-3 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Perbarui Password
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
