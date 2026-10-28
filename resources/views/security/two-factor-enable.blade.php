@extends('layouts.dashboard', ['title' => 'Aktifkan 2FA', 'headerTitle' => 'Aktifkan 2FA', 'backUrl' => route('security.settings'), 'backLabel' => 'Kembali ke Pengaturan Keamanan'])

@section('content')
<div class="max-w-xl space-y-4">
    <div>
        <a href="{{ route('security.settings') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors py-1 group">
            <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Pengaturan Keamanan</span>
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h2 class="text-xl font-bold">Aktifkan authenticator</h2>
        <p class="text-sm text-slate-500 mt-2">Tambahkan secret ini ke Google Authenticator, Authy, atau aplikasi TOTP lain.</p>
        <div class="mt-5 rounded-lg bg-slate-50 border p-4">
            <p class="text-xs text-slate-500">Secret</p>
            <code class="block mt-1 font-mono-code break-all">{{ $secret }}</code>
            <p class="text-xs text-slate-500 mt-4">Provisioning URI</p>
            <code class="block mt-1 text-xs break-all">{{ $qrUri }}</code>
        </div>
        <form method="POST" action="{{ route('security.two-factor.confirm') }}" class="mt-6 flex gap-2">
            @csrf
            <input name="code" required inputmode="numeric" placeholder="Kode 6 digit" class="flex-1 rounded-lg border border-slate-300 px-3 py-2.5">
            <button class="rounded-lg bg-black px-4 py-2.5 text-sm font-bold text-white">Konfirmasi</button>
        </form>
    </div>
</div>
@endsection
