@extends('layouts.dashboard', ['title' => 'Recovery Codes', 'headerTitle' => 'Recovery Codes', 'backUrl' => route('security.settings'), 'backLabel' => 'Kembali ke Pengaturan Keamanan'])

@section('content')
<div class="max-w-xl space-y-4">
    <div>
        <a href="{{ route('security.settings') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors py-1 group">
            <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Pengaturan Keamanan</span>
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h2 class="text-xl font-bold">Recovery codes</h2>
        <p class="text-sm text-slate-500 mt-2">Simpan codes ini di password manager. Setiap code hanya bisa dipakai sekali.</p>
        <div class="grid grid-cols-2 gap-2 mt-5 rounded-lg bg-slate-900 p-5 text-white font-mono-code text-sm">
            @foreach($codes as $code)
                <div>{{ $code }}</div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('security.recovery-codes.regenerate') }}" class="mt-5" data-confirm="Recovery codes lama akan tidak berlaku. Buat ulang sekarang?">
            <button class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-700">Generate ulang</button>
        </form>
    </div>
</div>
@endsection
