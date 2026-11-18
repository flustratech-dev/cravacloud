@use('App\Services\SupplierCoverageService', 'Coverage')
@extends('layouts.admin', ['title' => 'Pembelian Supplier', 'headerTitle' => 'Catatan Pembelian Supplier', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ purchaseModalOpen: false, presetInstance: '', editModalOpen: false, editId: null }">

    @php
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmt = fn ($d) => $d?->timezone('Asia/Jakarta')->format('d M Y') ?? '—';
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

    <!-- Ringkasan kecocokan (klik untuk menyaring) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        @foreach([
            Coverage::STATUS_URGENT => ['Perpanjang Segera', 'text-ink', 'Habis &le; ' . Coverage::URGENT_DAYS . ' hari, pelanggan masih aktif'],
            Coverage::STATUS_GAP => ['Belum Tertutup', 'text-ink', 'Supplier habis sebelum masa pelanggan'],
            Coverage::STATUS_MISSING => ['Tanpa Catatan', 'text-muted', 'Instance tanpa catatan pembelian'],
            Coverage::STATUS_COVERED => ['Tertutup', 'text-electric', 'Masa Supplier menutup masa pelanggan'],
        ] as $status => [$label, $color, $hint])
            <a href="{{ route('admin.supplier.index', $filter === $status ? [] : ['status' => $status]) }}"
               class="bg-white p-5 transition-colors block {{ $filter === $status ? 'ring-2 ring-inset ring-electric' : 'hover:bg-creamDark/30' }}">
                <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">{{ $label }}</span>
                <span class="font-display text-2xl font-bold {{ $counts[$status] > 0 ? $color : 'text-ink' }}">{{ $counts[$status] }}</span>
                <span class="text-[11px] text-muted block mt-1">{!! $hint !!}</span>
            </a>
        @endforeach
    </div>

    <!-- Kecocokan masa aktif -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-display font-bold text-ink text-base">Masa Aktif: Pelanggan vs Supplier</h3>
                <p class="text-xs text-muted mt-0.5">Bila Supplier habis lebih dulu, VPS akan mati padahal pelanggan masih berhak memakainya.</p>
            </div>
            <button type="button" @click="presetInstance = ''; purchaseModalOpen = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider shrink-0 cursor-pointer">Catat Pembelian</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Instance</th>
                        <th class="px-5 py-3.5">Aktif s/d (Pelanggan)</th>
                        <th class="px-5 py-3.5">Aktif s/d (Supplier)</th>
                        <th class="px-5 py-3.5 text-center">Selisih</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($rows as $row)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-ink font-mono block">{{ $row['instance']->hostname ?? '#' . $row['instance']->id }}</span>
                                <span class="text-[11px] text-muted">{{ $row['instance']->customer?->full_name ?? '-' }} &middot; {{ $row['instance']->status }}</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-ink">{{ $fmt($row['customer_expires_at']) }}</td>
                            <td class="px-5 py-3.5 font-mono text-ink">
                                {{ $fmt($row['supplier_expires_at']) }}
                                @if($row['latest'])
                                    <span class="text-[10px] text-muted block">{{ $row['latest']->supplier_order_no }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono font-bold {{ ($row['drift_days'] ?? 0) < 0 ? 'text-ink' : 'text-muted' }}">
                                {{ $row['drift_days'] !== null ? ($row['drift_days'] > 0 ? '+' : '') . $row['drift_days'] . ' hari' : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($row['status'] === Coverage::STATUS_URGENT)
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">{{ Coverage::statusLabel($row['status']) }}</span>
                                @elseif($row['status'] === Coverage::STATUS_COVERED)
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">{{ Coverage::statusLabel($row['status']) }}</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">{{ Coverage::statusLabel($row['status']) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @unless($row['status'] === Coverage::STATUS_COVERED)
                                    <button type="button" @click="presetInstance = '{{ $row['instance']->id }}'; purchaseModalOpen = true"
                                            class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-ink hover:bg-electric text-white whitespace-nowrap cursor-pointer">Catat Perpanjangan</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center font-mono text-xs text-muted">Tidak ada instance{{ $filter ? ' dengan status ini' : ' yang sedang berjalan' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ringkasan bulanan -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline">
            <h3 class="font-display font-bold text-ink text-base">Pengeluaran vs Penerimaan per Bulan</h3>
            <p class="text-xs text-muted mt-0.5">Total biaya ke Supplier dibandingkan total faktur lunas dari pelanggan.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Bulan</th>
                        <th class="px-5 py-3.5 text-right">Biaya Supplier</th>
                        <th class="px-5 py-3.5 text-right">Penerimaan</th>
                        <th class="px-5 py-3.5 text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @foreach($monthly as $m)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5 font-semibold text-ink">{{ $m['label'] }}</td>
                            <td class="px-5 py-3.5 text-right font-mono">{{ $rp($m['cost']) }}</td>
                            <td class="px-5 py-3.5 text-right font-mono">{{ $rp($m['revenue']) }}</td>
                            <td class="px-5 py-3.5 text-right font-mono font-bold {{ $m['margin'] < 0 ? 'text-ink' : 'text-electric' }}">{{ $m['margin'] < 0 ? '−' : '' }}{{ $rp(abs($m['margin'])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Riwayat pembelian -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline">
            <h3 class="font-display font-bold text-ink text-base">Riwayat Pembelian</h3>
            <p class="text-xs text-muted mt-0.5">Satu baris per periode pembelian. Perubahan dan penghapusan tercatat di audit log.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">No. Pesanan Supplier</th>
                        <th class="px-5 py-3.5">Instance / Order</th>
                        <th class="px-5 py-3.5">Periode</th>
                        <th class="px-5 py-3.5 text-right">Biaya</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($purchases as $p)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-ink font-mono block">{{ $p->supplier_order_no }}</span>
                                @if($p->notes)
                                    <span class="text-[11px] text-muted italic">{{ \Illuminate\Support\Str::limit($p->notes, 60) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-ink">
                                <span class="font-mono font-semibold">{{ $p->vpsInstance?->hostname ?? '—' }}</span>
                                <span class="text-[11px] text-muted block font-mono">
                                    {{ $p->order_id ? '#ORD-' . str_pad($p->order_id, 3, '0', STR_PAD_LEFT) : '' }}
                                    {{ ($p->vpsInstance?->customer ?? $p->order?->customer)?->full_name }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-mono text-ink">{{ $fmt($p->purchased_at) }} &ndash; {{ $fmt($p->supplier_expires_at) }}</td>
                            <td class="px-5 py-3.5 text-right font-mono font-bold text-ink">{{ $rp($p->actual_cost) }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" @click="editId = {{ $p->id }}; editModalOpen = true"
                                            class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">Ubah</button>
                                    <form action="{{ route('admin.supplier.destroy', $p->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Hapus catatan pembelian {{ addslashes($p->supplier_order_no) }}? Biaya ini tidak lagi dihitung di margin.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:bg-ink hover:text-white text-ink border border-hairline cursor-pointer">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center font-mono text-xs text-muted">Belum ada catatan pembelian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchases->hasPages())
            <div class="px-5 py-4 border-t border-hairline">{{ $purchases->links() }}</div>
        @endif
    </div>

    <!-- Modal Catat Pembelian -->
    <div x-show="purchaseModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="purchaseModalOpen = false">
        <div @click.away="purchaseModalOpen = false" class="bg-white border border-ink shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline bg-creamDark/40">
                <h3 class="font-display font-bold text-ink text-base">Catat Pembelian Supplier</h3>
                <p class="text-xs text-muted">Untuk perpanjangan bulanan instance yang sudah berjalan.</p>
            </div>
            <form action="{{ route('admin.supplier.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Instance</label>
                    <select name="vps_instance_id" required x-model="presetInstance" class="{{ $inputClass }}">
                        <option value="">— Pilih instance —</option>
                        @foreach($instances as $instance)
                            <option value="{{ $instance->id }}">{{ $instance->hostname ?? '#' . $instance->id }} — {{ $instance->customer?->full_name ?? '-' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Nomor Pesanan Supplier</label>
                    <input type="text" name="supplier_order_no" required maxlength="120" class="{{ $inputClass }} font-mono">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Biaya Riil (Rp)</label>
                    <input type="number" name="actual_cost" required min="0" step="1" class="{{ $inputClass }} font-mono">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Tanggal Beli</label>
                        <input type="date" name="purchased_at" required value="{{ now()->format('Y-m-d') }}" class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Aktif s/d</label>
                        <input type="date" name="supplier_expires_at" class="{{ $inputClass }} font-mono">
                    </div>
                </div>
                <p class="text-[11px] text-muted -mt-2">Kosongkan "Aktif s/d" untuk memakai satu bulan dari tanggal beli.</p>
                <div>
                    <label class="{{ $labelClass }}">Catatan</label>
                    <textarea name="notes" rows="2" maxlength="500" class="{{ $inputClass }}"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                    <button type="button" @click="purchaseModalOpen = false" class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline cursor-pointer">Batal</button>
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider cursor-pointer">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ubah Pembelian -->
    <div x-show="editModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="editModalOpen = false">
        <div @click.away="editModalOpen = false" class="bg-white border border-ink shadow-xl max-w-md w-full overflow-hidden">
            @foreach($purchases as $p)
                <form x-show="editId === {{ $p->id }}" x-cloak action="{{ route('admin.supplier.update', $p->id) }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')
                    <h3 class="font-display font-bold text-ink text-base border-b border-hairline pb-3">Ubah Catatan {{ $p->supplier_order_no }}</h3>
                    <div>
                        <label class="{{ $labelClass }}">Nomor Pesanan Supplier</label>
                        <input type="text" name="supplier_order_no" required maxlength="120" value="{{ $p->supplier_order_no }}" class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Biaya Riil (Rp)</label>
                        <input type="number" name="actual_cost" required min="0" step="1" value="{{ (int) $p->actual_cost }}" class="{{ $inputClass }} font-mono">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $labelClass }}">Tanggal Beli</label>
                            <input type="date" name="purchased_at" required value="{{ $p->purchased_at?->format('Y-m-d') }}" class="{{ $inputClass }} font-mono">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Aktif s/d</label>
                            <input type="date" name="supplier_expires_at" value="{{ $p->supplier_expires_at?->format('Y-m-d') }}" class="{{ $inputClass }} font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Catatan</label>
                        <textarea name="notes" rows="2" maxlength="500" class="{{ $inputClass }}">{{ $p->notes }}</textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline cursor-pointer">Batal</button>
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider cursor-pointer">Simpan Perubahan</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>

</div>
@endsection