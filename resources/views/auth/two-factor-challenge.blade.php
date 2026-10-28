@extends('layouts.auth', ['title' => 'Verifikasi Dua Faktor'])

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-10 px-4">
    <div class="w-full max-w-md px-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm">
            <div class="text-center mb-7"><img src="{{ asset('images/logo.png') }}" alt="CravaCloud" class="h-12 w-auto mx-auto mb-4"><h1 class="text-2xl font-bold">Verifikasi dua faktor</h1><p class="text-sm text-slate-500 mt-2">Masukkan kode dari aplikasi authenticator atau recovery code.</p></div>
            @if(session('error'))<div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-800">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
                @csrf
                <div><label class="block text-sm font-semibold mb-1.5">Kode authenticator</label><input name="code" inputmode="numeric" autocomplete="one-time-code" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-center text-lg tracking-[.35em]" maxlength="8"></div>
                <div><label class="block text-sm font-semibold mb-1.5">Atau recovery code</label><input name="recovery_code" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></div>
                <button class="w-full rounded-lg bg-black px-4 py-2.5 text-sm font-bold text-white">Verifikasi & lanjutkan</button>
            </form>
        </div>

        <!-- Copyright -->
        <p class="mt-6 text-center text-xs text-slate-400 font-normal">
            &copy; 2026 CravaCloud. All rights reserved. Created by cravacloud.
        </p>
    </div>
</div>
@endsection
