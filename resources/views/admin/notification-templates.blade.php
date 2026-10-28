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
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
    @endphp

    @if($errors->any())
        <div class="bg-white p-5 rounded-lg border border-red-200">
            <span class="text-xs font-semibold text-red-600 uppercase tracking-wider block mb-2">Periksa Kembali Isian Anda</span>
            <ul class="list-disc list-inside space-y-1 text-xs text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Template Aktif</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-emerald-600 font-mono-code">{{ $activeCount }}</span>
                <span class="text-xs text-emerald-600 font-medium">Siap Dipakai</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Total Template</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $totalCount }}</span>
                <span class="text-xs text-slate-500">Terdaftar</span>
            </div>
        </div>
    </div>

    <!-- Catatan pemisahan template -->
    <div class="bg-white p-5 rounded-lg border border-amber-200">
        <div class="flex items-start gap-3">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">
                Penting
            </span>
            <div>
                <div class="text-sm font-bold text-slate-900">Jangan Gabungkan Dua Template Ini</div>
                <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                    Template <strong>Suspensi Karena Pelanggaran</strong> hanya untuk pelanggan yang terbukti melanggar.
                    Template <strong>Insiden Infrastruktur</strong> dipakai untuk broadcast massal dan tidak boleh menuduh
                    penerima melanggar, karena mayoritas penerimanya tidak bersalah.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base">Daftar Template</h3>
            <p class="text-xs text-slate-500">Subjek dan isi dapat diubah tanpa deploy ulang.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Template</th>
                        <th class="px-5 py-3.5">Subjek</th>
                        <th class="px-5 py-3.5">Placeholder</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($templates as $template)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-slate-900 text-sm block">{{ $template->name }}</span>
                                <span class="text-[11px] text-slate-400 font-mono-code">{{ $template->code }}</span>
                                @if($template->description)
                                    <span class="text-[11px] text-slate-500 italic block mt-0.5">{{ Str::limit($template->description, 90) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">{{ Str::limit($template->subject, 50) }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    {{-- Kurung kurawal dirangkai satu per satu agar Blade
                                         tidak menganggapnya sebagai ekspresi. --}}
                                    @foreach($template->variables ?? [] as $variable)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200 font-mono-code">
                                            {{ '{' . '{' . $variable . '}' . '}' }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($template->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <button @click='openEdit(@json($template))'
                                        class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">
                                    Sunting
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm">
                                Belum ada template. Jalankan <span class="font-mono-code">php artisan db:seed</span> untuk memuat template bawaan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Sunting Template -->
    <div x-show="editModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="editModalOpen = false">

        <div @click.away="editModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-2xl w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Sunting Template Surel</h3>
                    <p class="text-xs text-slate-500">
                        Kode <span class="font-mono-code font-semibold text-slate-800" x-text="editData.code"></span> tidak dapat diubah
                    </p>
                </div>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
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
                              class="{{ $inputClass }} font-mono-code text-xs"></textarea>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span class="text-[11px] text-slate-500 mr-1">Placeholder tersedia:</span>
                        <template x-for="v in editData.variables" :key="v">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200 font-mono-code"
                                  x-text="'{' + '{' + v + '}' + '}'"></span>
                        </template>
                    </div>
                </div>

                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" x-model="editData.is_active"
                           class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                    <span class="text-sm text-slate-700">Template aktif dan dapat dipakai sistem</span>
                </label>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="editModalOpen = false"
                            class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                        Simpan Template
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
