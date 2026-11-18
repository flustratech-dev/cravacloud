@extends('layouts.auth', ['title' => 'CravaCloud Undangan Tidak Berlaku'])

@section('content')
<div class="min-h-screen flex flex-col justify-between py-8 px-5 sm:px-10">
    <div class="max-w-md w-full mx-auto flex items-center justify-between border-b border-hairline pb-4">
        <a href="{{ route('home') }}" class="inline-flex items-center space-x-2.5">
            <img src="{{ asset('images/logo.svg') }}" onerror="this.src='{{ asset('images/logo.png') }}'" alt="CravaCloud" class="h-7 w-7 object-contain">
            <span class="font-display font-extrabold text-lg tracking-tighter text-ink uppercase">CRAVACLOUD</span>
        </a>
        <a href="{{ route('home') }}" class="font-mono text-xs uppercase text-ink hover:text-electric font-semibold">Beranda →</a>
    </div>

    <div class="w-full max-w-md mx-auto my-8">
        <div class="bg-white border-2 border-ink p-7 sm:p-9 shadow-sm">
            <span class="font-mono text-[10px] font-bold tracking-[0.2em] uppercase text-rose-700 block">● INVITATION EXPIRED</span>
            <h1 class="mt-2 font-display font-bold text-2xl text-ink uppercase">Undangan Tidak Berlaku</h1>
            <p class="mt-2 text-xs font-mono text-muted leading-relaxed">
                Tautan undangan ini sudah pernah diterima, dicabut oleh admin organisasi, atau melewati batas waktu kedaluwarsa.
            </p>
            <a href="{{ route('home') }}" class="mt-6 w-full bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase py-3.5 px-5 flex items-center justify-between transition-colors">
                <span>Kembali ke Beranda</span>
                <span>↗</span>
            </a>
        </div>
    </div>

    <p class="text-center font-mono text-[11px] text-muted">
        © {{ date('Y') }} CravaCloud Indonesia. Created by cravacloud.
    </p>
</div>
@endsection
