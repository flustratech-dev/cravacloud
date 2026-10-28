@extends('layouts.dashboard', ['title' => 'Pengaturan Akun', 'headerTitle' => 'Profil & Pengaturan Akun', 'backUrl' => route('dashboard.index'), 'backLabel' => 'Kembali ke Dashboard'])

@section('content')
<div class="space-y-6 max-w-3xl">
    <!-- Profile & Account Info -->
    <div class="bg-white rounded-lg border border-slate-200 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
            <div class="rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0"
                 style="width: 48px; height: 48px; min-width: 48px; min-height: 48px; border-radius: 9999px; aspect-ratio: 1 / 1;">
                <svg class="text-blue-600 shrink-0" style="width: 26px; height: 26px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-bold text-slate-900 leading-tight truncate">{{ $user->full_name }}</h2>
                <p class="text-xs text-slate-500 font-mono-code mt-0.5 truncate">{{ $user->email }}</p>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    @if($user->channel === 'shopee')
                        <span class="px-2 py-0.5 rounded text-xs font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                            Shopee (Order: {{ $user->shopee_order_id ?? 'N/A' }})
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-xs font-semibold uppercase border border-slate-300 bg-slate-50 text-slate-900">
                            Website Direct
                        </span>
                    @endif
                    <span class="text-xs text-slate-500">· Terdaftar {{ $user->created_at->timezone('Asia/Jakarta')->format('d M Y') }}</span>
                </div>
            </div>
        </div>
        <div class="text-left sm:text-right border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-100 shrink-0">
            <span class="text-xs text-slate-400 font-medium block">Username</span>
            <p class="font-mono-code font-bold text-slate-900 text-sm">&#64;{{ $user->username }}</p>
        </div>
    </div>

    <!-- Edit Profile Form (No lines under headings) -->
    <div class="bg-white rounded-lg border border-slate-200 p-6">
        <h3 class="text-base font-bold text-slate-900 mb-4">Informasi Profil Akun</h3>

        <form action="{{ route('dashboard.settings.profile') }}" method="POST" class="space-y-4 text-xs">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Nama Lengkap *</label>
                    <input type="text" name="full_name" required value="{{ old('full_name', $user->full_name) }}"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Alamat Email</label>
                    <input type="email" value="{{ $user->email }}" disabled
                           class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-slate-500 cursor-not-allowed">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Nomor HP / WhatsApp</label>
                    <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="081234567890"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Instansi / Perusahaan</label>
                    <input type="text" name="company" value="{{ old('company', $user->company) }}" placeholder="Kampus / Perusahaan"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block font-medium text-slate-700 mb-1">Alamat Lengkap</label>
                <textarea name="address" rows="2" placeholder="Alamat domisili..."
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">{{ old('address', $user->address) }}</textarea>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold transition-colors">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <!-- Password Change Form (No lines under headings) -->
    <div class="bg-white rounded-lg border border-slate-200 p-6">
        <h3 class="text-base font-bold text-slate-900 mb-4">Ganti Password Akun</h3>

        <form action="{{ route('dashboard.settings.password') }}" method="POST" class="space-y-4 text-xs">
            @csrf
            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block font-medium text-slate-700 mb-1">Password Saat Ini *</label>
                    <input type="password" name="current_password" required placeholder="••••••••"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Password Baru *</label>
                    <input type="password" name="new_password" required minlength="8" placeholder="Min 8 karakter"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>

                <div>
                    <label class="block font-medium text-slate-700 mb-1">Konfirmasi Password Baru *</label>
                    <input type="password" name="new_password_confirmation" required minlength="8" placeholder="Konfirmasi"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 focus:outline-none">
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-black hover:bg-neutral-800 text-white font-bold transition-colors">
                    Perbarui Password
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
