@extends('layouts.dashboard', ['title' => 'Recovery Codes', 'headerTitle' => 'Recovery Codes'])

@section('content')
<div class="max-w-xl space-y-5">
    <div>
        <a href="{{ route('security.settings') }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
            <span>←</span>
            <span>Kembali ke Pengaturan Keamanan</span>
        </a>
    </div>

    <div class="bg-white border border-ink p-6 sm:p-8">
        <h2 class="font-display text-xl font-bold text-ink">Recovery codes</h2>
        <p class="text-xs text-muted mt-2 leading-relaxed">Simpan seluruh kode pemulihan ini di password manager. Setiap kode hanya dapat dipakai satu kali.</p>

        <div class="grid grid-cols-2 gap-2.5 mt-5 bg-ink p-5 text-white font-mono text-xs border border-ink">
            @foreach($codes as $code)
                <div class="p-1.5 bg-white/10 text-center tracking-wider select-all">{{ $code }}</div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('security.recovery-codes.regenerate') }}" class="mt-5" data-confirm="Recovery codes lama akan tidak berlaku. Buat ulang sekarang?">
            @csrf
            <button type="submit" class="rounded-none border border-amber-600 px-5 py-2.5 font-mono text-xs font-bold uppercase text-amber-900 hover:bg-amber-50 transition-colors cursor-pointer">
                Generate ulang
            </button>
        </form>
    </div>
</div>
@endsection
