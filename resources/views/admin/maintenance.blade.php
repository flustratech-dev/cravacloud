@extends('layouts.admin', ['title' => 'Jadwal Maintenance', 'headerTitle' => 'Jadwal Maintenance Terencana', 'backUrl' => route('admin.settings.index'), 'backLabel' => 'Kembali ke Pengaturan'])

@section('content')
<div class="space-y-6" x-data="{
    createModalOpen: false,
    editModalOpen: false,
    editData: { id: '', title: '', description: '', starts_at: '', ends_at: '', notice_days: 3, scopes: [], notify_customers: true },
    openEdit(w) {
        this.editData = {
            id: w.id,
            title: w.title,
            description: w.description || '',
            starts_at: w.starts_at,
            ends_at: w.ends_at,
            notice_days: w.notice_days,
            scopes: w.scopes || [],
            notify_customers: Boolean(w.notify_customers)
        };
        this.editModalOpen = true;
    }
}">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
    @endphp

    <div class="bg-amber-50 p-4 rounded-lg border border-amber-200 text-xs text-amber-900">
        <strong class="font-bold block mb-0.5">Mode manual</strong>
        Jadwal otomatis (maintenance:sync) dimatikan untuk menghemat beban server. Jendela maintenance tidak mulai atau selesai sendiri,
        dan email pemberitahuan tidak terkirim otomatis. Gunakan tombol Mulai, Selesaikan, dan Beritahu secara manual.
    </div>

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

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Sedang Berlangsung</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold {{ $runningCount > 0 ? 'text-sky-600' : 'text-slate-900' }} font-mono-code">{{ $runningCount }}</span>
                <span class="text-xs text-slate-500">Jendela Aktif</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Terjadwal</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $scheduledCount }}</span>
                <span class="text-xs text-slate-500">Menunggu Jadwal</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Total Riwayat</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $windows->total() }}</span>
                <span class="text-xs text-slate-500">Seluruh Jendela</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Cakupan Tersedia</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ count($scopes) }}</span>
                <span class="text-xs text-slate-500">Dapat Ditutup</span>
            </div>
        </div>
    </div>

    <!-- Tabel Jadwal -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Daftar Jendela Maintenance</h3>
                <p class="text-xs text-slate-500">Jendela berjalan dan berakhir otomatis mengikuti jadwal.</p>
            </div>
            <button @click="createModalOpen = true"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors shrink-0">
                Buat Jadwal Baru
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Kegiatan</th>
                        <th class="px-5 py-3.5">Jadwal</th>
                        <th class="px-5 py-3.5">Cakupan</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($windows as $window)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-slate-900 text-sm block">{{ $window->title }}</span>
                                @if($window->description)
                                    <span class="text-[11px] text-slate-500 italic block">{{ Str::limit($window->description, 70) }}</span>
                                @endif
                                <span class="text-[11px] text-slate-400 font-mono-code">ID: #MW-{{ str_pad($window->id, 3, '0', STR_PAD_LEFT) }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">
                                <div class="font-mono-code font-semibold text-slate-900">
                                    {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono-code">
                                    s/d {{ $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                </div>
                                <div class="text-[11px] text-slate-400">Durasi {{ $window->duration_minutes }} menit</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($window->scopes ?? [] as $scope)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ $scopes[$scope] ?? $scope }}
                                        </span>
                                    @endforeach
                                </div>
                                @if(!empty($window->spec_ids))
                                    <span class="text-[11px] text-slate-500 block mt-1">Terbatas {{ count($window->spec_ids) }} paket</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($window->status === 'in_progress')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-sky-50 text-sky-800 border border-sky-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-600"></span>
                                        {{ $window->status_label }}
                                    </span>
                                @elseif($window->status === 'scheduled')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                        {{ $window->status_label }}
                                    </span>
                                @elseif($window->status === 'completed')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                        {{ $window->status_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        {{ $window->status_label }}
                                    </span>
                                @endif

                                @if($window->customers_notified_at)
                                    <span class="text-[10px] text-slate-400 block mt-1">Sudah diberitahukan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1 flex-wrap">
                                    @if($window->status === 'scheduled')
                                        <button @click='openEdit(@json($window))'
                                                class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">
                                            Ubah
                                        </button>
                                        <form action="{{ route('admin.maintenance.start', $window->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">
                                                Mulai
                                            </button>
                                        </form>
                                    @endif

                                    @if($window->status === 'in_progress')
                                        <form action="{{ route('admin.maintenance.complete', $window->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">
                                                Selesaikan
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($window->status, ['scheduled', 'in_progress'], true))
                                        <form action="{{ route('admin.maintenance.notify', $window->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">
                                                Beritahu
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.maintenance.cancel', $window->id) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Batalkan jadwal maintenance ini?');">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-red-50 text-red-700 border border-red-200">
                                                Batalkan
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm">
                                Belum ada jadwal maintenance. Klik <strong>Buat Jadwal Baru</strong> untuk menambahkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($windows->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $windows->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Buat Jadwal -->
    <div x-show="createModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="createModalOpen = false">

        <div @click.away="createModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Buat Jadwal Maintenance</h3>
                    <p class="text-xs text-slate-500">Jendela akan berjalan otomatis sesuai waktu yang ditentukan</p>
                </div>
                <button @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('admin.maintenance.store') }}" method="POST" class="p-6 space-y-4">
                @csrf

                <div>
                    <label class="{{ $labelClass }}">Judul Kegiatan</label>
                    <input type="text" name="title" required maxlength="120"
                           placeholder="Contoh: Upgrade Node Jakarta" class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Keterangan</label>
                    <textarea name="description" rows="2" maxlength="500"
                              placeholder="Pesan yang tampil ke pelanggan" class="{{ $inputClass }}"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Waktu Mulai</label>
                        <input type="datetime-local" name="starts_at" required class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Waktu Selesai</label>
                        <input type="datetime-local" name="ends_at" required class="{{ $inputClass }}">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Cakupan Terdampak</label>
                    <div class="space-y-1.5">
                        @foreach($scopes as $key => $label)
                            <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="scopes[]" value="{{ $key }}"
                                       class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                                <span class="text-sm text-slate-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Batasi ke Paket Tertentu (opsional)</label>
                    <select name="spec_ids[]" multiple size="4"
                            class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black">
                        @foreach($specs as $spec)
                            <option value="{{ $spec->id }}">{{ $spec->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">Kosongkan bila berlaku untuk seluruh paket.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="{{ $labelClass }}">Beritahu H-berapa Hari</label>
                        <input type="number" name="notice_days" required min="0" max="30" value="3"
                               class="{{ $inputClass }} font-mono-code">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer pb-2">
                        <input type="checkbox" name="notify_customers" value="1" checked
                               class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                        <span class="text-sm text-slate-700">Kirim surel otomatis</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="createModalOpen = false"
                            class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                        Simpan Jadwal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ubah Jadwal -->
    <div x-show="editModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="editModalOpen = false">

        <div @click.away="editModalOpen = false"
             class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Ubah Jadwal Maintenance</h3>
                    <p class="text-xs text-slate-500">Memperbarui <span class="font-semibold text-slate-800" x-text="editData.title"></span></p>
                </div>
                <button @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form :action="'/admin/maintenance/' + editData.id" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="{{ $labelClass }}">Judul Kegiatan</label>
                    <input type="text" name="title" x-model="editData.title" required maxlength="120" class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Keterangan</label>
                    <textarea name="description" x-model="editData.description" rows="2" maxlength="500" class="{{ $inputClass }}"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Waktu Mulai</label>
                        <input type="datetime-local" name="starts_at" required
                               :value="editData.starts_at ? editData.starts_at.substring(0,16).replace(' ','T') : ''"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Waktu Selesai</label>
                        <input type="datetime-local" name="ends_at" required
                               :value="editData.ends_at ? editData.ends_at.substring(0,16).replace(' ','T') : ''"
                               class="{{ $inputClass }}">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Cakupan Terdampak</label>
                    <div class="space-y-1.5">
                        @foreach($scopes as $key => $label)
                            <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                <input type="checkbox" name="scopes[]" value="{{ $key }}"
                                       :checked="editData.scopes.includes('{{ $key }}')"
                                       class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                                <span class="text-sm text-slate-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="{{ $labelClass }}">Beritahu H-berapa Hari</label>
                        <input type="number" name="notice_days" x-model="editData.notice_days" required min="0" max="30"
                               class="{{ $inputClass }} font-mono-code">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer pb-2">
                        <input type="checkbox" name="notify_customers" value="1" x-model="editData.notify_customers"
                               class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                        <span class="text-sm text-slate-700">Kirim surel otomatis</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="editModalOpen = false"
                            class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
