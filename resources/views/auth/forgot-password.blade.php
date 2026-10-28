@extends('layouts.auth', ['title' => 'Lupa Password'])

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-10 px-4">
    <div class="w-full max-w-md px-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm">
            <div class="text-center mb-7">
                <img src="{{ asset('images/logo.png') }}" alt="CravaCloud" class="h-12 w-auto mx-auto mb-4">
                <h1 class="text-2xl font-bold text-slate-900">Reset password</h1>
                <p class="text-sm text-slate-500 mt-2">Masukkan email akun Anda. Jika terdaftar, kami akan mengirim link reset.</p>
            </div>
            @if(session('success'))<div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <div><label class="block text-sm font-semibold mb-1.5">Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                <button class="w-full rounded-lg bg-black px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Kirim link reset</button>
            </form>
            <a href="{{ route('login') }}" class="block text-center mt-5 text-sm text-slate-600 underline">Kembali ke login</a>
        </div>

        <!-- Copyright -->
        <p class="mt-6 text-center text-xs text-slate-400 font-normal">
            &copy; 2026 CravaCloud. All rights reserved. Created by cravacloud.
        </p>
    </div>
</div>
@endsection
