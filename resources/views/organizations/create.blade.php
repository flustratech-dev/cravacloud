@extends('layouts.dashboard', ['title' => 'Buat Organisasi', 'headerTitle' => 'Buat Organisasi'])

@section('content')
<div class="max-w-2xl space-y-5">
    <div>
        <a href="{{ route('organizations.index') }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
            <span>←</span>
            <span>Kembali ke Organisasi</span>
        </a>
    </div>

    <div class="bg-white border border-ink p-6 sm:p-8">
        <h2 class="font-display text-xl font-bold text-ink">Buat workspace baru</h2>
        <p class="text-xs text-muted mt-1">Gunakan organization untuk tim, proyek client, atau perusahaan.</p>

        <form method="POST" action="{{ route('organizations.store') }}" class="mt-6 space-y-4 text-xs">
            @csrf
            <div>
                <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Nama organisasi *</label>
                <input name="name" value="{{ old('name') }}" required
                       class="w-full rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm text-ink focus:outline-none focus:border-electric">
            </div>

            <div>
                <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Tipe workspace</label>
                <select name="type" class="w-full rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm text-ink font-mono focus:outline-none focus:border-electric">
                    <option value="company">Company</option>
                    <option value="personal">Personal</option>
                </select>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Nama billing</label>
                    <input name="billing_name" placeholder="PT / Nama penanggung jawab"
                           class="w-full rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm text-ink focus:outline-none focus:border-electric">
                </div>
                <div>
                    <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Email billing</label>
                    <input name="billing_email" type="email" placeholder="finance@domain.com"
                           class="w-full rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm text-ink font-mono focus:outline-none focus:border-electric">
                </div>
            </div>

            <div>
                <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Alamat billing</label>
                <textarea name="billing_address" rows="2" placeholder="Alamat lengkap penagihan..."
                          class="w-full rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm text-ink focus:outline-none focus:border-electric"></textarea>
            </div>

            <div>
                <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">NPWP (opsional)</label>
                <input name="tax_id" placeholder="Nomor NPWP perusahaan"
                       class="w-full rounded-none border border-hairline bg-white px-3.5 py-2.5 text-base sm:text-sm text-ink font-mono focus:outline-none focus:border-electric">
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded-none bg-electric hover:bg-electricHover px-6 py-3 font-mono text-xs font-bold uppercase tracking-wider text-white transition-colors cursor-pointer">
                    Buat organisasi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
