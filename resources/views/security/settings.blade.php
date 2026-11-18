@extends('layouts.dashboard', ['title' => 'Keamanan Akun', 'headerTitle' => 'Keamanan Akun'])

@section('content')
<div class="max-w-5xl space-y-6">
    <div class="border-b border-hairline pb-4">
        <h2 class="font-display text-xl font-bold text-ink">Keamanan &amp; akses</h2>
        <p class="text-xs text-muted mt-1">Kelola two-factor authentication, session aktif, API key, dan SSH key Anda.</p>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="bg-white border border-hairline p-6 flex flex-col justify-between">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="font-display font-bold text-base text-ink">Two-factor authentication</h3>
                    <p class="text-xs text-muted mt-1">Tambahkan lapisan proteksi TOTP setiap kali masuk.</p>
                </div>
                <span class="px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase shrink-0 {{ $twoFactorEnabled ? 'bg-emerald-100 text-emerald-900 border border-emerald-400' : 'bg-amber-100 text-amber-950 border border-amber-400' }}">
                    {{ $twoFactorEnabled ? 'Aktif' : 'Belum aktif' }}
                </span>
            </div>

            <div class="mt-5 flex flex-wrap gap-2 pt-4 border-t border-hairline">
                @if($twoFactorEnabled)
                    <form method="POST" action="{{ route('security.two-factor.disable') }}" class="flex flex-wrap gap-2">
                        @csrf
                        <input type="password" name="password" required placeholder="Password"
                               class="w-36 rounded-none border border-hairline bg-white px-3 py-2 text-base sm:text-xs focus:outline-none focus:border-electric">
                        <button type="submit" class="rounded-none border border-red-500 px-4 py-2 font-mono text-xs font-bold uppercase text-red-700 hover:bg-red-50 cursor-pointer">
                            Nonaktifkan
                        </button>
                    </form>
                    <a href="{{ route('security.recovery-codes') }}" class="rounded-none border border-ink px-4 py-2 font-mono text-xs font-bold uppercase text-ink hover:bg-creamDark">
                        Recovery codes
                    </a>
                @else
                    <a href="{{ route('security.two-factor.enable') }}" class="rounded-none bg-electric hover:bg-electricHover px-5 py-2.5 font-mono text-xs font-bold uppercase text-white transition-colors">
                        Aktifkan 2FA
                    </a>
                @endif
            </div>
        </section>

        <section class="bg-white border border-hairline p-6 flex flex-col justify-between">
            <div>
                <h3 class="font-display font-bold text-base text-ink">Session &amp; login activity</h3>
                <p class="text-xs text-muted mt-1">Audit riwayat perangkat yang mengakses akun dan cabut session yang tidak dikenal.</p>
            </div>
            <div class="mt-5 flex flex-wrap gap-2 pt-4 border-t border-hairline">
                <a href="{{ route('security.sessions') }}" class="rounded-none border border-ink px-4 py-2 font-mono text-xs font-bold uppercase text-ink hover:bg-creamDark transition-colors">
                    Kelola session
                </a>
                <a href="{{ route('security.login-activity') }}" class="rounded-none border border-ink px-4 py-2 font-mono text-xs font-bold uppercase text-ink hover:bg-creamDark transition-colors">
                    Riwayat login
                </a>
            </div>
        </section>
    </div>

    <section class="bg-white border border-hairline p-6">
        <div class="border-b border-hairline pb-3">
            <h3 class="font-display font-bold text-base text-ink">API keys</h3>
            <p class="text-xs text-muted mt-0.5">Token hanya ditampilkan satu kali segera setelah dibuat.</p>
        </div>

        @if(session('api_key_plaintext'))
            <div class="mt-4 bg-amber-50 border-2 border-amber-500 p-4">
                <p class="font-mono text-xs font-bold uppercase text-amber-950">Salin sekarang — token tidak dapat ditampilkan ulang</p>
                <code class="block mt-2 break-all font-mono text-xs bg-white p-2.5 border border-amber-400 text-ink select-all">{{ session('api_key_plaintext') }}</code>
            </div>
        @endif

        <form method="POST" action="{{ route('security.api-keys.create') }}" class="mt-5 grid md:grid-cols-[1fr_1fr_auto] gap-2.5">
            @csrf
            <input name="name" required placeholder="Nama key, mis. CI production"
                   class="rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm focus:outline-none focus:border-electric">
            <input type="date" name="expires_at"
                   class="rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm font-mono focus:outline-none focus:border-electric">
            <button type="submit" class="rounded-none bg-electric hover:bg-electricHover px-5 py-2.5 font-mono text-xs font-bold uppercase text-white transition-colors cursor-pointer">
                Buat API key
            </button>
        </form>

        <div class="mt-5 divide-y divide-hairline">
            @forelse($apiKeys as $key)
                <div class="py-3.5 flex items-center justify-between gap-4">
                    <div>
                        <p class="font-display font-bold text-sm text-ink">{{ $key->name }}</p>
                        <p class="font-mono text-xs text-muted mt-0.5">{{ $key->prefix }} · {{ $key->revoked_at ? 'Dicabut' : ($key->isExpired() ? 'Kadaluarsa' : 'Aktif') }}</p>
                    </div>
                    <div class="flex gap-3">
                        @if(!$key->revoked_at)
                            <form method="POST" action="{{ route('security.api-keys.rotate', $key->id) }}">
                                @csrf
                                <button type="submit" class="font-mono text-xs font-bold uppercase text-electric hover:underline cursor-pointer">Rotasi</button>
                            </form>
                            <form method="POST" action="{{ route('security.api-keys.revoke', $key->id) }}" data-confirm="Cabut API key {{ $key->name }}?">
                                @csrf
                                <button type="submit" class="font-mono text-xs font-bold uppercase text-red-700 hover:underline cursor-pointer">Cabut</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-4 font-mono text-xs text-muted">Belum ada API key.</p>
            @endforelse
        </div>
    </section>

    <section class="bg-white border border-hairline p-6">
        <div class="border-b border-hairline pb-3">
            <h3 class="font-display font-bold text-base text-ink">SSH public keys</h3>
            <p class="text-xs text-muted mt-0.5">Autentikasi kunci publik SSH agar tidak bergantung pada root password.</p>
        </div>

        <form method="POST" action="{{ route('security.ssh-keys.create') }}" class="mt-5 space-y-3">
            @csrf
            <div class="grid md:grid-cols-2 gap-3">
                <input name="name" required placeholder="Nama key"
                       class="rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm focus:outline-none focus:border-electric">
                <input name="public_key" required placeholder="ssh-ed25519 AAAA..."
                       class="rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm font-mono focus:outline-none focus:border-electric">
            </div>
            <button type="submit" class="rounded-none bg-ink hover:bg-electric px-5 py-2.5 font-mono text-xs font-bold uppercase text-white transition-colors cursor-pointer">
                Tambah SSH key
            </button>
        </form>

        <div class="mt-5 divide-y divide-hairline">
            @forelse($sshKeys as $key)
                <div class="py-3.5 flex justify-between items-center gap-4">
                    <div>
                        <p class="font-display text-sm font-bold text-ink">{{ $key->name }}</p>
                        <p class="text-xs text-muted font-mono mt-0.5">{{ $key->fingerprint }}</p>
                    </div>
                    <form method="POST" action="{{ route('security.ssh-keys.delete', $key->id) }}" data-confirm="Hapus SSH key {{ $key->name }}?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-mono text-xs font-bold uppercase text-red-700 hover:underline cursor-pointer">Hapus</button>
                    </form>
                </div>
            @empty
                <p class="py-4 font-mono text-xs text-muted">Belum ada SSH key.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
