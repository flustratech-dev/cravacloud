@use('App\Services\SupplierCoverageService', 'Coverage')
@extends('layouts.admin', ['title' => 'Pembelian Supplier', 'headerTitle' => 'Catatan Pembelian Supplier', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ purchaseModalOpen: false, presetInstance: '', editModalOpen: false, editId: null }">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $fmt = fn ($d) => $d?->timezone('Asia/Jakarta')->format('d M Y') ?? '—';
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

    <!-- Ringkasan kecocokan (klik untuk menyaring) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            Coverage::STATUS_URGENT => ['Perpanjang Segera', 'text-red-600', 'Habis ≤ ' . Coverage::URGENT_DAYS . ' hari, pelanggan masih aktif'],
            Coverage::STATUS_GAP => ['Belum Tertutup', 'text-amber-600', 'Supplier habis sebelum masa pelanggan'],
            Coverage::STATUS_MISSING => ['Tanpa Catatan', 'text-amber-600', 'Instance tanpa catatan pembelian'],
            Coverage::STATUS_COVERED => ['Tertutup', 'text-emerald-600', 'Masa Supplier menutup masa pelanggan'],
        ] as $status => [$label, $color, $hint])
            <a href="{{ route('admin.supplier.index', $filter === $status ? [] : ['status' => $status]) }}"
               class="bg-white p-5 rounded-lg border transition-colors {{ $filter === $status ? 'border-black' : 'border-slate-200 hover:border-slate-300' }}">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">{{ $label }}</span>
                <span class="text-2xl font-extrabold {{ $counts[$status] > 0 ? $color : 'text-slate-900' }} font-mono-code">{{ $counts[$status] }}</span>
                <span class="text-[11px] text-slate-500 block mt-1">{{ $hint }}</span>
            </a>
        @endforeach
    </div>

    <!-- Kecocokan masa aktif -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Masa Aktif: Pelanggan vs Supplier</h3>
                <p class="text-xs text-slate-500">Bila Supplier habis lebih dulu, VPS akan mati padahal pelanggan masih berhak memakainya.</p>
            </div>
            <button type="button" @click="presetInstance = ''; purchaseModalOpen = true"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold shrink-0">Catat Pembelian</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Instance</th>
                        <th class="px-5 py-3.5">Aktif s/d (Pelanggan)</th>
                        <th class="px-5 py-3.5">Aktif s/d (Supplier)</th>
                        <th class="px-5 py-3.5 text-center">Selisih</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $row)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-slate-900 font-mono-code block">{{ $row['instance']->hostname ?? '#' . $row['instance']->id }}</span>
                                <span class="text-[11px] text-slate-500">{{ $row['instance']->customer?->full_name ?? '-' }} · {{ $row['instance']->status }}</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono-code text-slate-900">{{ $fmt($row['customer_expires_at']) }}</td>
                            <td class="px-5 py-3.5 font-mono-code text-slate-900">
                                {{ $fmt($row['supplier_expires_at']) }}
                                @if($row['latest'])
                                    <span class="text-[10px] text-slate-400 block">{{ $row['latest']->supplier_order_no }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono-code font-semibold {{ ($row['drift_days'] ?? 0) < 0 ? 'text-red-700' : 'text-slate-600' }}">
                                {{ $row['drift_days'] !== null ? ($row['drift_days'] > 0 ? '+' : '') . $row['drift_days'] . ' hari' : '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($row['status'] === Coverage::STATUS_URGENT)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-red-50 text-red-800 border border-red-200"><span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>{{ Coverage::statusLabel($row['status']) }}</span>
                                @elseif($row['status'] === Coverage::STATUS_COVERED)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>{{ Coverage::statusLabel($row['status']) }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>{{ Coverage::statusLabel($row['status']) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                @unless($row['status'] === Coverage::STATUS_COVERED)
                                    <button type="button" @click="presetInstance = '{{ $row['instance']->id }}'; purchaseModalOpen = true"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white whitespace-nowrap">Catat Perpanjangan</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 text-sm">Tidak ada instance{{ $filter ? ' dengan status ini' : ' yang sedang berjalan' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ringkasan bulanan -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base">Pengeluaran vs Penerimaan per Bulan</h3>
            <p class="text-xs text-slate-500">Total biaya ke Supplier dibandingkan total faktur lunas dari pelanggan.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Bulan</th>
                        <th class="px-5 py-3.5 text-right">Biaya Supplier</th>
                        <th class="px-5 py-3.5 text-right">Penerimaan</th>
                        <th class="px-5 py-3.5 text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($monthly as $m)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5 font-semibold text-slate-900">{{ $m['label'] }}</td>
                            <td class="px-5 py-3.5 text-right font-mono-code">{{ $rp($m['cost']) }}</td>
                            <td class="px-5 py-3.5 text-right font-mono-code">{{ $rp($m['revenue']) }}</td>
                            <td class="px-5 py-3.5 text-right font-mono-code font-bold {{ $m['margin'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $m['margin'] < 0 ? '−' : '' }}{{ $rp(abs($m['margin'])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Riwayat pembelian -->
    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base">Riwayat Pembelian</h3>
            <p class="text-xs text-slate-500">Satu baris per periode pembelian. Perubahan dan penghapusan tercatat di audit log.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">No. Pesanan Supplier</th>
                        <th class="px-5 py-3.5">Instance / Order</th>
                        <th class="px-5 py-3.5">Periode</th>
                        <th class="px-5 py-3.5 text-right">Biaya</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($purchases as $p)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-slate-900 font-mono-code block">{{ $p->supplier_order_no }}</span>
                                @if($p->notes)
                                    <span class="text-[11px] text-slate-500 italic">{{ \Illuminate\Support\Str::limit($p->notes, 60) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">
                                <span class="font-mono-code">{{ $p->vpsInstance?->hostname ?? '—' }}</span>
                                <span class="text-[11px] text-slate-500 block">
                                    {{ $p->order_id ? '#ORD-' . str_pad($p->order_id, 3, '0', STR_PAD_LEFT) : '' }}
                                    {{ ($p->vpsInstance?->customer ?? $p->order?->customer)?->full_name }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 font-mono-code text-slate-700">{{ $fmt($p->purchased_at) }} – {{ $fmt($p->supplier_expires_at) }}</td>
                            <td class="px-5 py-3.5 text-right font-mono-code font-bold text-slate-900">{{ $rp($p->actual_cost) }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" @click="editId = {{ $p->id }}; editModalOpen = true"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">Ubah</button>
                                    <form action="{{ route('admin.supplier.destroy', $p->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Hapus catatan pembelian {{ addslashes($p->supplier_order_no) }}? Biaya ini tidak lagi dihitung di margin.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-red-50 text-red-700 border border-red-200">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada catatan pembelian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchases->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">{{ $purchases->links() }}</div>
        @endif
    </div>

    <!-- Modal Catat Pembelian -->
    <div x-show="purchaseModalOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="purchaseModalOpen = false">
        <div @click.away="purchaseModalOpen = false" class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Catat Pembelian Supplier</h3>
                <p class="text-xs text-slate-500">Untuk perpanjangan bulanan instance yang sudah berjalan.</p>
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
                    <input type="text" name="supplier_order_no" required maxlength="120" class="{{ $inputClass }} font-mono-code">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Biaya Riil (Rp)</label>
                    <input type="number" name="actual_cost" required min="0" step="1" class="{{ $inputClass }} font-mono-code">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Tanggal Beli</label>
                        <input type="date" name="purchased_at" required value="{{ now()->format('Y-m-d') }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Aktif s/d</label>
                        <input type="date" name="supplier_expires_at" class="{{ $inputClass }}">
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 -mt-2">Kosongkan "Aktif s/d" untuk memakai satu bulan dari tanggal beli.</p>
                <div>
                    <label class="{{ $labelClass }}">Catatan</label>
                    <textarea name="notes" rows="2" maxlength="500" class="{{ $inputClass }}"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="purchaseModalOpen = false" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200">Batal</button>
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Ubah Pembelian -->
    <div x-show="editModalOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="editModalOpen = false">
        <div @click.away="editModalOpen = false" class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            @foreach($purchases as $p)
                <form x-show="editId === {{ $p->id }}" x-cloak action="{{ route('admin.supplier.update', $p->id) }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')
                    <h3 class="font-bold text-slate-900 text-base">Ubah Catatan {{ $p->supplier_order_no }}</h3>
                    <div>
                        <label class="{{ $labelClass }}">Nomor Pesanan Supplier</label>
                        <input type="text" name="supplier_order_no" required maxlength="120" value="{{ $p->supplier_order_no }}" class="{{ $inputClass }} font-mono-code">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Biaya Riil (Rp)</label>
                        <input type="number" name="actual_cost" required min="0" step="1" value="{{ (int) $p->actual_cost }}" class="{{ $inputClass }} font-mono-code">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $labelClass }}">Tanggal Beli</label>
                            <input type="date" name="purchased_at" required value="{{ $p->purchased_at?->format('Y-m-d') }}" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Aktif s/d</label>
                            <input type="date" name="supplier_expires_at" value="{{ $p->supplier_expires_at?->format('Y-m-d') }}" class="{{ $inputClass }}">
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Catatan</label>
                        <textarea name="notes" rows="2" maxlength="500" class="{{ $inputClass }}">{{ $p->notes }}</textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200">Batal</button>
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold">Simpan Perubahan</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>

</div>
@endsection
