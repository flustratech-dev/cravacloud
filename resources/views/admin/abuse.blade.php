@extends('layouts.admin', ['title' => 'Kasus Pelanggaran', 'headerTitle' => 'Kasus Pelanggaran Ketentuan Penggunaan', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    createModalOpen: false,
    notifyModalOpen: false,
    resolveModalOpen: false,
    target: { id: '', type_label: '', customer: '' },
    openNotify(c) { this.target = c; this.notifyModalOpen = true; },
    openResolve(c) { this.target = c; this.resolveModalOpen = true; }
}">

    @php
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
    @endphp

    @include('admin.partials.helpdesk-tabs')

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

    <!-- Peringatan risiko akun -->
    <div class="bg-white p-5 border border-ink">
        <div class="flex items-start gap-3">
            <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream shrink-0 mt-0.5">
                Penting
            </span>
            <div>
                <div class="font-display text-sm font-bold text-ink">Pelanggaran Satu Pelanggan Berdampak ke Semua</div>
                <p class="text-xs text-muted mt-1 leading-relaxed">
                    Acceptable Use Policy Supplier menyasar <strong class="text-ink">akun</strong>, bukan hanya layanan.
                    Satu pelanggan yang menjalankan VPN, proxy, scraping, torrent, atau penambangan kripto
                    dapat menyebabkan seluruh akun CravaCloud disuspend dan saldo hangus.
                </p>
            </div>
        </div>
    </div>

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Belum Selesai</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $openCount }}</span>
                <span class="font-mono text-[11px] text-muted">Perlu Ditangani</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Tingkat Kritis</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold {{ $criticalCount > 0 ? 'text-electric' : 'text-ink' }}">{{ $criticalCount }}</span>
                <span class="font-mono text-[11px] text-muted">Segera</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Diterminasi</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $terminatedCount }}</span>
                <span class="font-mono text-[11px] text-muted">Layanan Dihentikan</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Total Kasus</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $totalCount }}</span>
                <span class="font-mono text-[11px] text-muted">Sepanjang Waktu</span>
            </div>
        </div>
    </div>

    <!-- Tabel Kasus -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-display font-bold text-ink text-base">Daftar Kasus</h3>
                <p class="text-xs text-muted mt-0.5">Catatan ini menjadi bukti saat pelanggan menyanggah di kemudian hari.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('admin.abuse.index') }}" class="flex items-center gap-2">
                    <select name="status" onchange="this.form.submit()"
                            class="px-3 py-2 border border-hairline font-mono text-xs text-ink bg-white focus:outline-none focus:border-electric">
                        <option value="">Semua Status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ $filterStatus === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <select name="severity" onchange="this.form.submit()"
                            class="px-3 py-2 border border-hairline font-mono text-xs text-ink bg-white focus:outline-none focus:border-electric">
                        <option value="">Semua Tingkat</option>
                        @foreach($severities as $severity)
                            <option value="{{ $severity }}" {{ $filterSeverity === $severity ? 'selected' : '' }}>{{ ucfirst($severity) }}</option>
                        @endforeach
                    </select>
                </form>
                <button @click="createModalOpen = true"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors shrink-0 cursor-pointer">
                    Catat Kasus
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Kasus</th>
                        <th class="px-5 py-3.5">Pelanggan</th>
                        <th class="px-5 py-3.5">Layanan</th>
                        <th class="px-5 py-3.5 text-center">Tingkat</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($cases as $case)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-ink text-sm block">{{ $case->type_label }}</span>
                                <span class="text-[11px] text-muted font-mono">
                                    ID: #AB-{{ str_pad($case->id, 3, '0', STR_PAD_LEFT) }}
                                </span>
                                <span class="text-[11px] font-mono text-muted block">
                                    {{ $case->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-ink">
                                <div class="font-semibold text-ink">{{ $case->user?->full_name ?? '-' }}</div>
                                <div class="text-[11px] font-mono text-muted">{{ $case->user?->email ?? '-' }}</div>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-ink">
                                {{ $case->vpsInstance?->hostname ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($case->severity === 'critical')
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">{{ $case->severity_label }}</span>
                                @elseif($case->severity === 'high')
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-ink">{{ $case->severity_label }}</span>
                                @else
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-white text-muted border border-hairline">{{ $case->severity_label }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($case->status === 'resolved')
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">
                                        {{ $case->status_label }}
                                    </span>
                                @elseif($case->status === 'terminated')
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">
                                        {{ $case->status_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">
                                        {{ $case->status_label }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    @if(!in_array($case->status, ['resolved', 'terminated'], true))
                                        <button @click='openNotify(@json(["id" => $case->id, "type_label" => $case->type_label, "customer" => $case->user?->email]))'
                                                class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">
                                            Beritahu
                                        </button>
                                        <button @click='openResolve(@json(["id" => $case->id, "type_label" => $case->type_label, "customer" => $case->user?->email]))'
                                                class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-ink hover:bg-electric text-white cursor-pointer">
                                            Tutup
                                        </button>
                                    @else
                                        <span class="font-mono text-[11px] text-muted">
                                            {{ $case->resolved_at?->timezone('Asia/Jakarta')->format('d M Y') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada kasus pelanggaran yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cases->hasPages())
            <div class="px-5 py-4 border-t border-hairline">
                {{ $cases->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Catat Kasus -->
    <div x-show="createModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="createModalOpen = false">

        <div @click.away="createModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline flex items-center justify-between bg-creamDark/40">
                <div>
                    <h3 class="font-display font-bold text-ink text-base">Catat Kasus Pelanggaran</h3>
                    <p class="text-xs text-muted">Sertakan bukti selengkap mungkin untuk keperluan sengketa</p>
                </div>
                <button @click="createModalOpen = false" class="text-muted hover:text-ink p-1 font-mono text-base cursor-pointer">&times;</button>
            </div>

            <form action="{{ route('admin.abuse.store') }}" method="POST" class="p-6 space-y-4">
                @csrf

                <div>
                    <label class="{{ $labelClass }}">Layanan Terkait</label>
                    <select name="vps_instance_id" class="{{ $inputClass }}">
                        <option value="">— Tidak terkait layanan tertentu —</option>
                        @foreach($instances as $instance)
                            <option value="{{ $instance->id }}" data-user="{{ $instance->customer_id }}">
                                {{ $instance->hostname ?? 'Instance #' . $instance->id }}
                                — {{ $instance->customer?->name ?? 'Tanpa pemilik' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Pemilik Layanan</label>
                    <select name="user_id" required class="{{ $inputClass }}">
                        <option value="">— Pilih pelanggan —</option>
                        @foreach($instances->pluck('customer')->filter()->unique('id') as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Jenis Pelanggaran</label>
                        <select name="type" required class="{{ $inputClass }}">
                            @foreach($types as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Tingkat Keparahan</label>
                        <select name="severity" required class="{{ $inputClass }}">
                            @foreach($severities as $severity)
                                <option value="{{ $severity }}" {{ $severity === 'medium' ? 'selected' : '' }}>{{ ucfirst($severity) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Keterangan &amp; Bukti</label>
                    <textarea name="evidence" rows="4" maxlength="2000"
                              placeholder="Contoh: trafik keluar 900 Mbps berkelanjutan ke port 1080, terdeteksi 18 Sep 03:12 WIB"
                              class="{{ $inputClass }}"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                    <button type="button" @click="createModalOpen = false"
                            class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Simpan Kasus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Beritahu Pelanggan -->
    <div x-show="notifyModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="notifyModalOpen = false">

        <div @click.away="notifyModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline bg-creamDark/40">
                <h3 class="font-display font-bold text-ink text-base">Kirim Pemberitahuan Pelanggaran</h3>
                <p class="text-xs text-muted">
                    Dikirim hanya ke <span class="font-mono font-semibold text-ink" x-text="target.customer"></span>
                </p>
            </div>

            <form :action="'/admin/abuse/' + target.id + '/notify'" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Batas Waktu Tanggapan (hari)</label>
                    <input type="number" name="deadline_days" required min="1" max="30" value="3"
                           class="{{ $inputClass }} font-mono">
                    <p class="text-[11px] text-muted mt-1">
                        Untuk VPS, sebaiknya di bawah 5 hari agar masih tersisa waktu sebelum layanan ditangguhkan/dihentikan permanen.
                    </p>
                </div>

                <label class="flex items-start gap-2 cursor-pointer text-xs text-ink">
                    <input type="checkbox" name="suspend_upstream" value="1" class="w-4 h-4 mt-0.5 border-ink accent-electric">
                    <span>Sekaligus suspend VPS di upstream (jika server otomatis)</span>
                </label>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-hairline">
                    <button type="button" @click="notifyModalOpen = false"
                            class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Kirim Pemberitahuan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tutup Kasus -->
    <div x-show="resolveModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="resolveModalOpen = false">

        <div @click.away="resolveModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline bg-creamDark/40">
                <h3 class="font-display font-bold text-ink text-base">Tutup Kasus</h3>
                <p class="text-xs text-muted">
                    Kasus <span class="font-semibold text-ink" x-text="target.type_label"></span>
                </p>
            </div>

            <form :action="'/admin/abuse/' + target.id + '/resolve'" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Hasil Akhir</label>
                    <select name="status" required class="{{ $inputClass }}">
                        <option value="resolved">Selesai — pelanggan memperbaiki</option>
                        <option value="terminated">Diterminasi — layanan dihentikan</option>
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Catatan Penyelesaian</label>
                    <textarea name="resolution" rows="3" required maxlength="1000" class="{{ $inputClass }}"></textarea>
                </div>

                <label class="flex items-start gap-2 cursor-pointer text-xs text-ink">
                    <input type="checkbox" name="terminate_upstream" value="1" class="w-4 h-4 mt-0.5 border-ink accent-electric">
                    <span>Sekaligus terminate permanen VPS di upstream (khusus hasil Diterminasi)</span>
                </label>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-hairline">
                    <button type="button" @click="resolveModalOpen = false"
                            class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Tutup Kasus
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection