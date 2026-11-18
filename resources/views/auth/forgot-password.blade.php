@extends('layouts.auth', ['title' => 'CravaCloud Lupa Password'])

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
            <span class="font-mono text-[10px] font-bold tracking-[0.2em] uppercase text-electric block">● PASSWORD RECOVERY</span>
            <h1 class="mt-2 font-display font-bold text-2xl text-ink uppercase">Reset Password</h1>
            <p class="mt-1.5 text-xs font-mono text-muted leading-relaxed">Masukkan email akun Anda. Jika terdaftar, kami akan mengirim link reset password.</p>

            @if(session('success'))
                <x-ui.alert type="success" class="mt-4">{{ session('success') }}</x-ui.alert>
            @endif
            @if($errors->any())
                <x-ui.alert type="danger" class="mt-4">{{ $errors->first() }}</x-ui.alert>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                @csrf
                <x-ui.input type="email" name="email" label="Alamat Email" value="{{ old('email') }}" required autofocus placeholder="nama@email.com" />
                <button type="submit" class="w-full bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase py-3.5 px-5 flex items-center justify-between transition-colors cursor-pointer">
                    <span>Kirim Link Reset</span>
                    <span>↗</span>
                </button>
            </form>

            <a href="{{ route('login') }}" class="block text-center mt-5 font-mono text-xs text-ink underline hover:text-electric">Kembali ke halaman login</a>
        </div>
    </div>

    <p class="text-center font-mono text-[11px] text-muted">
        © {{ date('Y') }} CravaCloud Indonesia. Created by cravacloud.
    </p>
</div>
@endsection
