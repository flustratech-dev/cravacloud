@extends('layouts.dashboard', ['title' => 'Buat Organisasi', 'headerTitle' => 'Buat Organisasi', 'backUrl' => route('organizations.index'), 'backLabel' => 'Kembali ke Organisasi'])

@section('content')
<div class="max-w-2xl space-y-4">
    <div>
        <a href="{{ route('organizations.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors py-1 group">
            <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Organisasi</span>
        </a>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h2 class="text-xl font-bold">Buat workspace baru</h2>
        <p class="text-sm text-slate-500 mt-1">Gunakan organization untuk tim atau perusahaan.</p>
        <form method="POST" action="{{ route('organizations.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold mb-1.5">Nama organisasi</label>
                <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1.5">Tipe</label>
                <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
                    <option value="company">Company</option>
                    <option value="personal">Personal</option>
                </select>
            </div>
            <div class="grid md:grid-cols-2 gap-3">
                <input name="billing_name" placeholder="Nama billing" class="rounded-lg border border-slate-300 px-3 py-2.5">
                <input name="billing_email" type="email" placeholder="Email billing" class="rounded-lg border border-slate-300 px-3 py-2.5">
            </div>
            <textarea name="billing_address" placeholder="Alamat billing" class="w-full rounded-lg border border-slate-300 px-3 py-2.5"></textarea>
            <input name="tax_id" placeholder="NPWP (opsional)" class="w-full rounded-lg border border-slate-300 px-3 py-2.5">
            <button class="rounded-lg bg-black px-5 py-2.5 text-sm font-bold text-white">Buat organisasi</button>
        </form>
    </div>
</div>
@endsection
