@extends('layouts.admin', ['title' => 'Template Surel', 'headerTitle' => 'Template Notifikasi Surel', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    editModalOpen: false,
    editData: { id: '', code: '', name: '', subject: '', body: '', is_active: true, variables: [] },
    openEdit(t) {
        this.editData = {
            id: t.id, code: t.code, name: t.name,
            subject: t.subject, body: t.body,
            is_active: Boolean(t.is_active),
            variables: t.variables || []
        };
        this.editModalOpen = true;
    }
}">

    @php
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
    @endphp

    @if($errors->any())
        <div class="bg-white p-5 border-2 border-ink">
            <span class="font-mono text-[10px] font-bold text-ink uppercase tracking-widest block mb-2">Periksa Kembali Isian Anda</span>
            <ul class="list-disc list-inside space-y-1 text-xs text-ink">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Template Aktif</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-electric">{{ $activeCount }}</span>
                <span class="font-mono text-[11px] text-electric font-bold">Siap Dipakai</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Total Template</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $totalCount }}</span>
                <span class="font-mono text-[11px] text-muted">Terdaftar</span>
            </div>
        </div>
    </div>

    <!-- Catatan pemisahan template -->
    <div class="bg-white p-5 border border-ink">
        <div class="flex items-start gap-3">
            <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream shrink-0 mt-0.5">
                Penting
            </span>
            <div>
                <div class="font-display text-sm font-bold text-ink">Jangan Gabungkan Dua Template Ini</div>
                <p class="text-xs text-muted mt-1 leading-relaxed">
                    Template <strong class="text-ink">Suspensi Karena Pelanggaran</strong> hanya untuk pelanggan yang terbukti melanggar.
                    Template <strong class="text-ink">Insiden Infrastruktur</strong> dipakai untuk broadcast massal dan tidak boleh menuduh
                    penerima melanggar, karena mayoritas penerimanya tidak bersalah.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline">
            <h3 class="font-display font-bold text-ink text-base">Daftar Template</h3>
            <p class="text-xs text-muted mt-0.5">Subjek dan isi dapat diubah tanpa deploy ulang.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Template</th>
                        <th class="px-5 py-3.5">Subjek</th>
                        <th class="px-5 py-3.5">Placeholder</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($templates as $template)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-ink text-sm block">{{ $template->name }}</span>
                                <span class="text-[11px] text-muted font-mono">{{ $template->code }}</span>
                                @if($template->description)
                                    <span class="text-[11px] text-muted italic block mt-0.5">{{ Str::limit($template->description, 90) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-ink">{{ Str::limit($template->subject, 50) }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    {{-- Kurung kurawal dirangkai satu per satu agar Blade
                                         tidak menganggapnya sebagai ekspresi. --}}
                                    @foreach($template->variables ?? [] as $variable)
                                        <span class="px-1.5 py-0.5 text-[10px] font-bold bg-creamDark text-ink border border-hairline font-mono">
                                            {{ '{' . '{' . $variable . '}' . '}' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($template->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">Aktif</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark border border-hairline text-muted">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button @click='openEdit(@json($template))'
                                        class="px-3 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">
                                    Sunting
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada template. Jalankan <span class="font-mono text-ink font-semibold">php artisan db:seed</span> untuk memuat template bawaan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Sunting Template -->
    <div x-show="editModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="editModalOpen = false">

        <div @click.away="editModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-2xl w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline flex items-center justify-between bg-creamDark/40">
                <div>
                    <h3 class="font-display font-bold text-ink text-base">Sunting Template Surel</h3>
                    <p class="text-xs text-muted">
                        Kode <span class="font-mono font-bold text-ink" x-text="editData.code"></span> tidak dapat diubah
                    </p>
                </div>
                <button @click="editModalOpen = false" class="text-muted hover:text-ink p-1 font-mono text-base cursor-pointer">&times;</button>
            </div>

            <form :action="'/admin/templates/' + editData.id" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="{{ $labelClass }}">Nama Template</label>
                    <input type="text" name="name" x-model="editData.name" required maxlength="120" class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Subjek Surel</label>
                    <input type="text" name="subject" x-model="editData.subject" required maxlength="200" class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Isi Surel</label>
                    <textarea name="body" x-model="editData.body" rows="12" required maxlength="5000"
                              class="{{ $inputClass }} font-mono"></textarea>
                    <div class="mt-2 flex flex-wrap gap-1 items-center">
                        <span class="font-mono text-[10px] text-muted mr-1">Placeholder tersedia:</span>
                        <template x-for="v in editData.variables" :key="v">
                            <span class="px-1.5 py-0.5 text-[10px] font-bold bg-creamDark text-ink border border-hairline font-mono"
                                  x-text="'{' + '{' + v + '}' + '}'"></span>
                        </template>
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" x-model="editData.is_active"
                           class="w-4 h-4 border-ink accent-electric">
                    <span class="text-xs text-ink">Template aktif dan dapat dipakai sistem</span>
                </label>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                    <button type="button" @click="editModalOpen = false"
                            class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Simpan Template
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection