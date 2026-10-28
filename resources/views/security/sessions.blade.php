@extends('layouts.dashboard', ['title' => 'Session Aktif', 'headerTitle' => 'Session Aktif', 'backUrl' => route('security.settings'), 'backLabel' => 'Kembali ke Pengaturan Keamanan'])

@section('content')
<div class="max-w-5xl space-y-5">
    <div>
        <a href="{{ route('security.settings') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors py-1 group">
            <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Pengaturan Keamanan</span>
        </a>
    </div>

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold">Session aktif</h2>
            <p class="text-sm text-slate-500 mt-1">Cabut akses perangkat yang tidak Anda kenali.</p>
        </div>
        <form method="POST" action="{{ route('security.sessions.revoke-all') }}" class="flex gap-2">
            @csrf
            <input type="password" name="password" required placeholder="Password" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700">Cabut semua lainnya</button>
        </form>
    </div>
    <div class="bg-white border border-slate-200 rounded-lg divide-y">
        @forelse($sessions as $session)
            <div class="p-5 flex items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-sm">{{ $session->user_agent ?: 'Perangkat tidak diketahui' }} @if($session->is_current)<span class="ml-2 rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700">Session ini</span>@endif</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $session->ip_address ?: '-' }} · {{ $session->last_activity?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</p>
                </div>
                @unless($session->is_current)
                    <form method="POST" action="{{ route('security.sessions.revoke', $session->id) }}" data-confirm="Cabut session perangkat ini?">
                        <button class="text-sm font-semibold text-rose-700 underline">Cabut</button>
                    </form>
                @endunless
            </div>
        @empty
            <div class="p-8 text-center text-sm text-slate-500">Tidak ada session aktif.</div>
        @endforelse
    </div>
</div>
@endsection
