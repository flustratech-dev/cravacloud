@extends('layouts.auth', ['title' => 'Buat Password Baru'])

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-10 px-4">
    <div class="w-full max-w-md px-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-8 shadow-sm">
            <div class="text-center mb-7"><img src="{{ asset('images/logo.png') }}" alt="CravaCloud" class="h-12 w-auto mx-auto mb-4"><h1 class="text-2xl font-bold">Buat password baru</h1></div>
            @if($errors->any())<div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4" x-data="{ showPassword: false, showConfirm: false }">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div>
                    <label class="block text-sm font-semibold mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email', $email) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1.5">Password baru</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" name="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-10 text-sm">
                        <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 focus:outline-none" tabindex="-1">
                            <svg x-show="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Minimal 8 karakter, wajib mengandung huruf dan angka.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1.5">Ulangi password</label>
                    <div class="relative">
                        <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-10 text-sm">
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600 focus:outline-none" tabindex="-1">
                            <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        </button>
                    </div>
                </div>
                <button class="w-full rounded-lg bg-black px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Simpan password</button>
            </form>
        </div>

        <!-- Copyright -->
        <p class="mt-6 text-center text-xs text-slate-400 font-normal">
            &copy; 2026 CravaCloud. All rights reserved. Created by cravacloud.
        </p>
    </div>
</div>
@endsection
