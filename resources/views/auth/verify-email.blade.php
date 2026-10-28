@extends('layouts.auth', ['title' => 'Verifikasi Email'])

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-10 px-4">
    <div class="w-full max-w-md px-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm text-center">
            <img src="{{ asset('images/logo.png') }}" alt="CravaCloud" class="h-12 w-auto mx-auto mb-5">
            <h1 class="text-2xl font-bold">Verifikasi email Anda</h1>
            <p class="text-sm text-slate-500 mt-3">Kami sudah mengirim link verifikasi ke <strong>{{ auth()->user()->email }}</strong>. Verifikasi email untuk mengamankan akun.</p>
            @if(session('success'))<div class="mt-5 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
            <form method="POST" action="{{ route('verification.send') }}" class="mt-6">@csrf<button class="w-full rounded-lg bg-black px-4 py-2.5 text-sm font-bold text-white">Kirim ulang email verifikasi</button></form>
            <a href="{{ route('dashboard.index') }}" class="inline-block mt-5 text-sm text-slate-600 underline">Lanjut ke dashboard</a>
        </div>

        <!-- Copyright -->
        <p class="mt-6 text-center text-xs text-slate-400 font-normal">
            &copy; 2026 CravaCloud. All rights reserved. Created by cravacloud.
        </p>
    </div>
</div>
@endsection
