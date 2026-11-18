{{--
    Spanduk selama admin masuk sebagai pelanggan.
--}}
@php $impersonation = session(\App\Services\ImpersonationService::SESSION_KEY); @endphp
@if(is_array($impersonation))
    <div class="fixed bottom-4 inset-x-4 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 z-[60]">
        <div class="bg-ink text-white border-2 border-electric shadow-2xl px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400"></span>
                </span>
                <div class="min-w-0">
                    <div class="font-mono text-xs font-bold uppercase tracking-wider truncate">
                        Masuk sebagai {{ $impersonation['target_name'] ?? 'pelanggan' }}
                    </div>
                    <div class="font-mono text-[10px] text-cream/70">Mode lihat saja. Aksi mutasi data diblokir.</div>
                </div>
            </div>
            <form action="{{ route('impersonate.leave') }}" method="POST" class="shrink-0">
                @csrf
                <button type="submit"
                        class="w-full sm:w-auto px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Kembali ke Admin ↗
                </button>
            </form>
        </div>
    </div>
@endif
