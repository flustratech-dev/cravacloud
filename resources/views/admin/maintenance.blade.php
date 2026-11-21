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
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
    @endphp

    @include('admin.partials.system-tabs')

    <div class="bg-creamDark/60 p-4 border border-ink text-xs text-ink">
        <strong class="font-mono text-[11px] font-bold uppercase tracking-wider block mb-0.5">Mode Manual</strong>
        Jadwal otomatis (<code class="font-mono bg-white px-1 border border-hairline">maintenance:sync</code>) dimatikan untuk menghemat beban server. Jendela maintenance tidak mulai atau selesai sendiri,
        dan email pemberitahuan tidak terkirim otomatis. Gunakan tombol Mulai, Selesaikan, dan Beritahu secara manual.
    </div>

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

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Sedang Berlangsung</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold {{ $runningCount > 0 ? 'text-electric' : 'text-ink' }}">{{ $runningCount }}</span>
                <span class="font-mono text-[11px] text-muted">Jendela Aktif</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Terjadwal</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $scheduledCount }}</span>
                <span class="font-mono text-[11px] text-muted">Menunggu Jadwal</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Total Riwayat</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $windows->total() }}</span>
                <span class="font-mono text-[11px] text-muted">Seluruh Jendela</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Cakupan Tersedia</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ count($scopes) }}</span>
                <span class="font-mono text-[11px] text-muted">Dapat Ditutup</span>
            </div>
        </div>
    </div>

    <!-- Tabel Jadwal -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-display font-bold text-ink text-base">Daftar Jendela Maintenance</h3>
                <p class="text-xs text-muted mt-0.5">Jendela maintenance dikendalikan langsung dari panel ini.</p>
            </div>
            <button @click="createModalOpen = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors shrink-0 cursor-pointer">
                Buat Jadwal Baru
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Kegiatan</th>
                        <th class="px-5 py-3.5">Jadwal</th>
                        <th class="px-5 py-3.5">Cakupan</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($windows as $window)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-ink text-sm block">{{ $window->title }}</span>
                                @if($window->description)
                                    <span class="text-[11px] text-muted italic block">{{ Str::limit($window->description, 70) }}</span>
                                @endif
                                <span class="text-[11px] text-muted font-mono">ID: #MW-{{ str_pad($window->id, 3, '0', STR_PAD_LEFT) }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-ink">
                                <div class="font-mono font-bold text-ink">
                                    {{ $window->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                </div>
                                <div class="text-[11px] text-muted font-mono">
                                    s/d {{ $window->ends_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                </div>
                                <div class="text-[11px] font-mono text-muted">Durasi {{ $window->duration_minutes }} menit</div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($window->scopes ?? [] as $scope)
                                        <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">
                                            {{ $scopes[$scope] ?? $scope }}
                                        </span>
                                    @endforeach
                                </div>
                                @if(!empty($window->spec_ids))
                                    <span class="font-mono text-[11px] text-muted block mt-1">Terbatas {{ count($window->spec_ids) }} paket</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($window->status === 'in_progress')
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">
                                        {{ $window->status_label }}
                                    </span>
                                @elseif($window->status === 'scheduled')
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-ink">
                                        {{ $window->status_label }}
                                    </span>
                                @elseif($window->status === 'completed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">
                                        {{ $window->status_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-white text-muted border border-hairline">
                                        {{ $window->status_label }}
                                    </span>
                                @endif

                                @if($window->customers_notified_at)
                                    <span class="font-mono text-[10px] text-muted block mt-1">Sudah diberitahukan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    @if($window->status === 'scheduled')
                                        <button @click='openEdit(@json($window))'
                                                class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">
                                            Ubah
                                        </button>
                                        <form action="{{ route('admin.maintenance.start', $window->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-electric hover:bg-electricHover text-white cursor-pointer">
                                                Mulai
                                            </button>
                                        </form>
                                    @endif

                                    @if($window->status === 'in_progress')
                                        <form action="{{ route('admin.maintenance.complete', $window->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-ink hover:bg-electric text-white cursor-pointer">
                                                Selesaikan
                                            </button>
                                        </form>
                                    @endif

                                    @if(in_array($window->status, ['scheduled', 'in_progress'], true))
                                        <form action="{{ route('admin.maintenance.notify', $window->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">
                                                Beritahu
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.maintenance.cancel', $window->id) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Batalkan jadwal maintenance ini?');">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:bg-ink hover:text-white text-ink border border-hairline cursor-pointer">
                                                Batalkan
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada jadwal maintenance. Klik <strong>Buat Jadwal Baru</strong> untuk menambahkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($windows->hasPages())
            <div class="px-5 py-4 border-t border-hairline">
                {{ $windows->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Buat Jadwal -->
    <div x-show="createModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="createModalOpen = false">

        <div @click.away="createModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline flex items-center justify-between bg-creamDark/40">
                <div>
                    <h3 class="font-display font-bold text-ink text-base">Buat Jadwal Maintenance</h3>
                    <p class="text-xs text-muted">Jendela maintenance tercatat untuk kontrol operasional</p>
                </div>
                <button @click="createModalOpen = false" class="text-muted hover:text-ink p-1 font-mono text-base cursor-pointer">&times;</button>
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
                        <input type="datetime-local" name="starts_at" required class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Waktu Selesai</label>
                        <input type="datetime-local" name="ends_at" required class="{{ $inputClass }} font-mono">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Cakupan Terdampak</label>
                    <div class="space-y-1.5">
                        @foreach($scopes as $key => $label)
                            <label class="flex items-center gap-2 px-3 py-2 border border-hairline hover:bg-creamDark/30 cursor-pointer">
                                <input type="checkbox" name="scopes[]" value="{{ $key }}"
                                       class="w-4 h-4 border-ink accent-electric">
                                <span class="text-xs text-ink">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Batasi ke Paket Tertentu (opsional)</label>
                    <select name="spec_ids[]" multiple size="4"
                            class="w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric font-mono">
                        @foreach($specs as $spec)
                            <option value="{{ $spec->id }}">{{ $spec->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-muted mt-1">Kosongkan bila berlaku untuk seluruh paket.</p>
                </div>

                <div class="grid grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="{{ $labelClass }}">Beritahu H-berapa Hari</label>
                        <input type="number" name="notice_days" required min="0" max="30" value="3"
                               class="{{ $inputClass }} font-mono">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer pb-2">
                        <input type="checkbox" name="notify_customers" value="1" checked
                               class="w-4 h-4 border-ink accent-electric">
                        <span class="text-xs text-ink">Kirim surel otomatis</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                    <button type="button" @click="createModalOpen = false"
                            class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Simpan Jadwal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ubah Jadwal -->
    <div x-show="editModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="editModalOpen = false">

        <div @click.away="editModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline flex items-center justify-between bg-creamDark/40">
                <div>
                    <h3 class="font-display font-bold text-ink text-base">Ubah Jadwal Maintenance</h3>
                    <p class="text-xs text-muted">Memperbarui <span class="font-semibold text-ink" x-text="editData.title"></span></p>
                </div>
                <button @click="editModalOpen = false" class="text-muted hover:text-ink p-1 font-mono text-base cursor-pointer">&times;</button>
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
                               class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Waktu Selesai</label>
                        <input type="datetime-local" name="ends_at" required
                               :value="editData.ends_at ? editData.ends_at.substring(0,16).replace(' ','T') : ''"
                               class="{{ $inputClass }} font-mono">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Cakupan Terdampak</label>
                    <div class="space-y-1.5">
                        @foreach($scopes as $key => $label)
                            <label class="flex items-center gap-2 px-3 py-2 border border-hairline hover:bg-creamDark/30 cursor-pointer">
                                <input type="checkbox" name="scopes[]" value="{{ $key }}"
                                       :checked="editData.scopes.includes('{{ $key }}')"
                                       class="w-4 h-4 border-ink accent-electric">
                                <span class="text-xs text-ink">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="{{ $labelClass }}">Beritahu H-berapa Hari</label>
                        <input type="number" name="notice_days" x-model="editData.notice_days" required min="0" max="30"
                               class="{{ $inputClass }} font-mono">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer pb-2">
                        <input type="checkbox" name="notify_customers" value="1" x-model="editData.notify_customers"
                               class="w-4 h-4 border-ink accent-electric">
                        <span class="text-xs text-ink">Kirim surel otomatis</span>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                    <button type="button" @click="editModalOpen = false"
                            class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection