{{--
    PHASE 7 - Spanduk selama admin masuk sebagai pelanggan.

    Melayang di bawah layar agar tidak menggeser tata letak (dasbor memakai
    h-screen), namun tetap selalu terlihat di seluruh halaman. Mencegah admin
    lupa dan mengira sedang berada di akunnya sendiri.
--}}
@php $impersonation = session(\App\Services\ImpersonationService::SESSION_KEY); @endphp
@if(is_array($impersonation))
    <div class="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 z-[60]">
        <div class="bg-black text-white rounded-lg shadow-xl border border-neutral-700 px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400"></span>
                </span>
                <div class="min-w-0">
                    <div class="text-xs font-bold truncate">
                        Masuk sebagai {{ $impersonation['target_name'] ?? 'pelanggan' }}
                    </div>
                    <div class="text-[11px] text-neutral-400">Mode lihat saja. Aksi yang mengubah data diblokir.</div>
                </div>
            </div>
            <form action="{{ route('impersonate.leave') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit"
                        class="w-full sm:w-auto px-4 py-2 rounded-lg bg-white hover:bg-neutral-200 text-black text-xs font-bold transition-colors">
                    Kembali ke Admin
                </button>
            </form>
        </div>
    </div>
@endif
