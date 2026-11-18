@extends('layouts.dashboard', ['title' => 'Session Aktif', 'headerTitle' => 'Session Aktif'])

@section('content')
<div class="max-w-5xl space-y-5">
    <div>
        <a href="{{ route('security.settings') }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
            <span>←</span>
            <span>Kembali ke Pengaturan Keamanan</span>
        </a>
    </div>

    <div class="bg-white border border-hairline p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="font-display text-xl font-bold text-ink">Session aktif</h2>
            <p class="text-xs text-muted mt-1">Cabut akses perangkat atau browser lain yang tidak Anda kenali.</p>
        </div>
        <form method="POST" action="{{ route('security.sessions.revoke-all') }}" class="flex flex-wrap gap-2">
            @csrf
            <input type="password" name="password" required placeholder="Password akun"
                   class="rounded-none border border-hairline bg-white px-3.5 py-2 text-base sm:text-xs focus:outline-none focus:border-electric">
            <button type="submit" class="rounded-none border border-red-500 px-4 py-2 font-mono text-xs font-bold uppercase text-red-700 hover:bg-red-50 cursor-pointer">
                Cabut semua lainnya
            </button>
        </form>
    </div>

    <div class="bg-white border border-hairline divide-y divide-hairline">
        @forelse($sessions as $session)
            <div class="p-5 flex items-center justify-between gap-4">
                <div>
                    <p class="font-display font-bold text-sm text-ink">
                        {{ $session->user_agent ?: 'Perangkat tidak diketahui' }}
                        @if($session->is_current)
                            <span class="ml-2 px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-emerald-100 text-emerald-900 border border-emerald-400">Session ini</span>
                        @endif
                    </p>
                    <p class="font-mono text-xs text-muted mt-1">{{ $session->ip_address ?: '-' }} · {{ $session->last_activity?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                </div>
                @unless($session->is_current)
                    <form method="POST" action="{{ route('security.sessions.revoke', $session->id) }}" data-confirm="Cabut session perangkat ini?">
                        @csrf
                        <button type="submit" class="font-mono text-xs font-bold uppercase text-red-700 hover:underline cursor-pointer">Cabut</button>
                    </form>
                @endunless
            </div>
        @empty
            <div class="p-8 text-center font-mono text-xs text-muted">Tidak ada session aktif.</div>
        @endforelse
    </div>
</div>
@endsection
