@extends('layouts.dashboard', ['title' => 'Aktifkan 2FA', 'headerTitle' => 'Aktifkan 2FA'])

@section('content')
<div class="max-w-xl space-y-5">
    <div>
        <a href="{{ route('security.settings') }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
            <span>←</span>
            <span>Kembali ke Pengaturan Keamanan</span>
        </a>
    </div>

    <div class="bg-white border border-ink p-6 sm:p-8">
        <h2 class="font-display text-xl font-bold text-ink">Aktifkan authenticator</h2>
        <p class="text-xs text-muted mt-2 leading-relaxed">Tambahkan secret TOTP di bawah ke Google Authenticator, Authy, atau aplikasi TOTP pilihan Anda.</p>

        <div class="mt-5 bg-creamDark/50 border border-hairline p-4 space-y-3">
            <div>
                <p class="font-mono text-[10px] uppercase text-muted font-bold">Secret Key</p>
                <code class="block mt-1 font-mono text-xs font-bold text-ink break-all select-all">{{ $secret }}</code>
            </div>
            <div>
                <p class="font-mono text-[10px] uppercase text-muted font-bold">Provisioning URI</p>
                <code class="block mt-1 font-mono text-[11px] text-ink break-all select-all">{{ $qrUri }}</code>
            </div>
        </div>

        <form method="POST" action="{{ route('security.two-factor.confirm') }}" class="mt-6 flex flex-col sm:flex-row gap-2.5">
            @csrf
            <input name="code" required inputmode="numeric" placeholder="Kode 6 digit"
                   class="flex-1 rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm font-mono focus:outline-none focus:border-electric">
            <button type="submit" class="rounded-none bg-electric hover:bg-electricHover px-6 py-2.5 font-mono text-xs font-bold uppercase tracking-wider text-white transition-colors cursor-pointer">
                Konfirmasi
            </button>
        </form>
    </div>
</div>
@endsection
