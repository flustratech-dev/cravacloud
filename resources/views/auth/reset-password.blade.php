@extends('layouts.auth', ['title' => 'CravaCloud Buat Password Baru'])

@section('content')
<div class="min-h-screen flex flex-col justify-between py-8 px-5 sm:px-10">
    <div class="max-w-md w-full mx-auto flex items-center justify-between border-b border-hairline pb-4">
        <a href="{{ route('home') }}" class="inline-flex items-center space-x-2.5">
            <img src="{{ asset('images/logo.svg') }}" onerror="this.src='{{ asset('images/logo.png') }}'" alt="CravaCloud" class="h-7 w-7 object-contain">
            <span class="font-display font-extrabold text-lg tracking-tighter text-ink uppercase">CRAVACLOUD</span>
        </a>
        <a href="{{ route('login') }}" class="font-mono text-xs uppercase text-ink hover:text-electric font-semibold">← Login</a>
    </div>

    <div class="w-full max-w-md mx-auto my-8">
        <div class="bg-white border-2 border-ink p-7 sm:p-9 shadow-sm">
            <span class="font-mono text-[10px] font-bold tracking-[0.2em] uppercase text-electric block">● SET NEW PASSWORD</span>
            <h1 class="mt-2 font-display font-bold text-2xl text-ink uppercase">Buat Password Baru</h1>

            @if($errors->any())
                <x-ui.alert type="danger" class="mt-4">{{ $errors->first() }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4" x-data="{ showPassword: false, showConfirm: false }">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <x-ui.input type="email" name="email" label="Alamat Email" value="{{ old('email', $email) }}" required />

                <div>
                    <label class="block font-mono text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Password Baru</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" name="password" required
                               class="w-full rounded-none bg-white border border-hairline px-3.5 py-2.5 pr-10 text-xs sm:text-sm font-mono text-ink focus:outline-none focus:border-electric transition-colors">
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center px-3 text-muted hover:text-ink cursor-pointer" tabindex="-1">
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <p class="font-mono text-[10px] text-muted mt-1">Minimal 8 karakter, wajib mengandung huruf dan angka.</p>
                </div>

                <div>
                    <label class="block font-mono text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Ulangi Password</label>
                    <div class="relative">
                        <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required
                               class="w-full rounded-none bg-white border border-hairline px-3.5 py-2.5 pr-10 text-xs sm:text-sm font-mono text-ink focus:outline-none focus:border-electric transition-colors">
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 flex items-center px-3 text-muted hover:text-ink cursor-pointer" tabindex="-1">
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase py-3.5 px-5 flex items-center justify-between transition-colors cursor-pointer">
                    <span>Simpan Password</span>
                    <span>↗</span>
                </button>
            </form>
        </div>
    </div>

    <p class="text-center font-mono text-[11px] text-muted">
        © {{ date('Y') }} CravaCloud Indonesia. Created by cravacloud.
    </p>
</div>
@endsection
