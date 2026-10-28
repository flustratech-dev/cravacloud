@extends('layouts.admin', ['title' => 'Billing Center', 'headerTitle' => 'Billing Center', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ couponModalOpen: false, editCouponId: null, taxModalOpen: false, editTaxId: null }">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
        $filterClass = 'px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $tabs = [
            'invoices' => 'Faktur',
            'subscriptions' => 'Langganan',
            'refunds' => 'Pengembalian Dana',
            'coupons' => 'Kupon',
            'taxes' => 'Pajak',
            'credits' => 'Saldo Pelanggan',
            'margin' => 'Margin Riil',
        ];
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

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Lunas Bulan Ini</span>
            <span class="text-xl font-extrabold text-emerald-600 font-mono-code">{{ $rp($summary['paid_this_month']) }}</span>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Faktur Belum Lunas</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $summary['unpaid_count'] }}</span>
                <span class="text-xs text-slate-500">Faktur</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Pengembalian Menunggu</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold {{ $summary['pending_refunds'] > 0 ? 'text-amber-600' : 'text-slate-900' }} font-mono-code">{{ $summary['pending_refunds'] }}</span>
                <span class="text-xs text-slate-500">Pengajuan</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Langganan Aktif</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $summary['active_subscriptions'] }}</span>
                <span class="text-xs text-slate-500">Langganan</span>
            </div>
        </div>
    </div>

    <!-- Navigasi Tab -->
    <div class="bg-white p-2 rounded-lg border border-slate-200 flex items-center gap-1 overflow-x-auto">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.billing.index', ['tab' => $key]) }}"
               class="px-4 py-2 rounded-lg text-xs font-bold transition-colors whitespace-nowrap {{ $tab === $key ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- ================================================================== --}}
    {{-- FAKTUR --}}
    {{-- ================================================================== --}}
    @if($tab === 'invoices')
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Daftar Faktur</h3>
                    <p class="text-xs text-slate-500">Menandai lunas memakai alur order yang sudah ada agar status order ikut sinkron.</p>
                </div>
                <form method="GET" action="{{ route('admin.billing.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tab" value="invoices">
                    <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="No. faktur / pelanggan" class="{{ $filterClass }}">
                    <select name="status" class="{{ $filterClass }}">
                        <option value="">Semua Status</option>
                        @foreach($invoiceStatuses as $status)
                            <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold">Saring</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Faktur</th>
                            <th class="px-5 py-3.5">Pelanggan</th>
                            <th class="px-5 py-3.5 text-right">Nominal</th>
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($invoices as $invoice)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-slate-900 font-mono-code block">{{ $invoice->invoice_number }}</span>
                                    <span class="text-[11px] text-slate-500">
                                        {{ $invoice->order?->vpsSpec?->name ?? 'Tanpa paket' }}
                                        · terbit {{ $invoice->issued_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-' }}
                                    </span>
                                    @if($invoice->is_renewal)
                                        <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-sky-100 text-sky-800 border border-sky-200">Perpanjangan</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <div class="font-semibold text-slate-900">{{ $invoice->order?->customer?->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $invoice->order?->customer?->email ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono-code font-bold text-slate-900">{{ $rp($invoice->amount) }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    @if($invoice->status === 'paid')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Lunas</span>
                                    @elseif($invoice->status === 'cancelled')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Batal</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>{{ ucfirst($invoice->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1 flex-wrap">
                                        <a href="{{ route('dashboard.invoice.print', $invoice->id) }}" target="_blank"
                                           class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">PDF</a>
                                        <form action="{{ route('admin.billing.invoices.resend', $invoice->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">Kirim Ulang</button>
                                        </form>
                                        @if($invoice->status !== 'paid' && $invoice->order && $invoice->order->status === 'pending')
                                            <form action="{{ route('admin.orders.mark-paid', $invoice->order_id) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('Tandai order dan faktur ini lunas secara manual?');">
                                                @csrf
                                                <input type="hidden" name="reference" value="{{ $invoice->invoice_number }}">
                                                <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">Tandai Lunas</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada faktur{{ array_filter($filters) ? ' untuk saringan ini' : '' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($invoices->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $invoices->links() }}</div>
            @endif
        </div>
    @endif

    {{-- ================================================================== --}}
    {{-- LANGGANAN --}}
    {{-- ================================================================== --}}
    @if($tab === 'subscriptions')
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Daftar Langganan</h3>
                    <p class="text-xs text-slate-500">Riwayat perpanjangan dan kegagalan penagihan otomatis.</p>
                </div>
                <form method="GET" action="{{ route('admin.billing.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="subscriptions">
                    <select name="status" onchange="this.form.submit()" class="{{ $filterClass }}">
                        <option value="">Semua Status</option>
                        @foreach($subscriptionStatuses as $status)
                            <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Langganan</th>
                            <th class="px-5 py-3.5">Pelanggan</th>
                            <th class="px-5 py-3.5">Tagihan Berikutnya</th>
                            <th class="px-5 py-3.5 text-center">Faktur</th>
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Perpanjang Otomatis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($subscriptions as $sub)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-slate-900 block">{{ $sub->vpsSpec?->name ?? 'Paket' }}</span>
                                    <span class="text-[11px] text-slate-400 font-mono-code">#SUB-{{ str_pad($sub->id, 3, '0', STR_PAD_LEFT) }} · {{ $rp($sub->unit_amount) }}/{{ $sub->billing_cycle }}</span>
                                    @if($sub->renewal_failures > 0)
                                        <span class="block text-[11px] text-red-700 mt-0.5">{{ $sub->renewal_failures }}× gagal: {{ \Illuminate\Support\Str::limit($sub->last_renewal_error, 60) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <div class="font-semibold text-slate-900">{{ $sub->customer?->full_name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $sub->customer?->email ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 font-mono-code text-slate-700">{{ $sub->next_billing_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-center font-mono-code font-semibold">{{ $sub->invoices_count }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ in_array($sub->status, ['active'], true) ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200' }}">{{ str_replace('_', ' ', $sub->status) }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <form action="{{ route('admin.billing.subscriptions.auto-renew', $sub->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold border {{ $sub->auto_renew ? 'bg-black text-white border-black' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' }}">
                                            {{ $sub->auto_renew ? 'Aktif' : 'Nonaktif' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada langganan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($subscriptions->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $subscriptions->links() }}</div>
            @endif
        </div>
    @endif

    {{-- ================================================================== --}}
    {{-- PENGEMBALIAN DANA --}}
    {{-- ================================================================== --}}
    @if($tab === 'refunds')
        <form action="{{ route('admin.billing.refunds.store') }}" method="POST" class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Ajukan Pengembalian Dana</h3>
                <p class="text-xs text-slate-500">Pengajuan harus disetujui terlebih dahulu sebelum saldo bertambah. Status order tidak berubah; batalkan atau terminasi layanan secara terpisah bila perlu.</p>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">Faktur Lunas</label>
                    <select name="invoice_id" required class="{{ $inputClass }}">
                        <option value="">— Pilih faktur —</option>
                        @foreach($refundableInvoices as $row)
                            <option value="{{ $row['invoice']->id }}" {{ old('invoice_id') == $row['invoice']->id ? 'selected' : '' }}>
                                {{ $row['invoice']->invoice_number }} — {{ $row['invoice']->order?->customer?->full_name ?? '-' }} — sisa {{ $rp($row['refundable']) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Nominal (Rp)</label>
                    <input type="number" name="amount" required min="1" step="1" value="{{ old('amount') }}" class="{{ $inputClass }} font-mono-code">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Metode</label>
                    <select name="method" required class="{{ $inputClass }}">
                        <option value="credit_balance">Saldo pelanggan</option>
                        <option value="manual_transfer">Transfer manual</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">Alasan</label>
                    <input type="text" name="reason" required maxlength="255" value="{{ old('reason') }}" class="{{ $inputClass }}">
                </div>
            </div>
            <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold">Ajukan</button>
            </div>
        </form>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Riwayat Pengembalian Dana</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Faktur</th>
                            <th class="px-5 py-3.5">Pelanggan</th>
                            <th class="px-5 py-3.5 text-right">Nominal</th>
                            <th class="px-5 py-3.5">Metode & Alasan</th>
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($refunds as $refund)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5 font-mono-code font-semibold text-slate-900">{{ $refund->invoice?->invoice_number ?? '-' }}</td>
                                <td class="px-5 py-3.5 text-slate-700">{{ $refund->customer?->full_name ?? '-' }}</td>
                                <td class="px-5 py-3.5 text-right font-mono-code font-bold text-slate-900">{{ $rp($refund->amount) }}</td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    <span class="font-semibold">{{ $refund->method === 'credit_balance' ? 'Saldo pelanggan' : 'Transfer manual' }}</span>
                                    <span class="text-[11px] text-slate-500 block">{{ $refund->reason }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    @if($refund->status === 'processed')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Diproses</span>
                                    @elseif($refund->status === 'pending')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>Menunggu</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>{{ ucfirst($refund->status) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($refund->status === 'pending')
                                        <div class="flex items-center justify-end gap-1">
                                            <form action="{{ route('admin.billing.refunds.approve', $refund->id) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('Proses pengembalian dana ini?');">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">Setujui</button>
                                            </form>
                                            <form action="{{ route('admin.billing.refunds.cancel', $refund->id) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-red-50 text-red-700 border border-red-200">Batalkan</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-[11px] text-slate-400 block text-right">{{ $refund->processed_at?->timezone('Asia/Jakarta')->format('d M Y') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada pengembalian dana.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($refunds->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $refunds->links() }}</div>
            @endif
        </div>
    @endif

    {{-- ================================================================== --}}
    {{-- KUPON --}}
    {{-- ================================================================== --}}
    @if($tab === 'coupons')
        <div class="bg-white p-5 rounded-lg border border-amber-200">
            <div class="flex items-start gap-3">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">Penting</span>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Kupon <strong>belum dapat ditukarkan di checkout</strong>. Mesin kupon sudah ada di sistem penagihan, tetapi
                    halaman checkout belum menyediakan kolom kode kupon. Kupon yang dibuat di sini tersimpan dan siap dipakai
                    begitu penukaran di checkout diaktifkan.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Daftar Kupon</h3>
                    <p class="text-xs text-slate-500">Kode kupon tidak dapat diubah setelah dibuat.</p>
                </div>
                <button type="button" @click="editCouponId = 'new'; couponModalOpen = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold shrink-0">Buat Kupon</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Kupon</th>
                            <th class="px-5 py-3.5">Diskon</th>
                            <th class="px-5 py-3.5">Masa Berlaku</th>
                            <th class="px-5 py-3.5 text-center">Terpakai</th>
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($coupons as $coupon)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-slate-900 font-mono-code block">{{ $coupon->code }}</span>
                                    <span class="text-[11px] text-slate-500">{{ $coupon->name }}</span>
                                </td>
                                <td class="px-5 py-3.5 font-mono-code text-slate-900">
                                    {{ $coupon->type === 'percent' ? rtrim(rtrim(number_format((float) $coupon->value, 2, ',', '.'), '0'), ',') . '%' : $rp($coupon->value) }}
                                    @if($coupon->max_discount)
                                        <span class="text-[11px] text-slate-500 block">maks. {{ $rp($coupon->max_discount) }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 font-mono-code text-[11px] text-slate-600">
                                    {{ $coupon->starts_at?->timezone('Asia/Jakarta')->format('d M Y') ?? 'Sekarang' }} – {{ $coupon->ends_at?->timezone('Asia/Jakarta')->format('d M Y') ?? 'Tanpa batas' }}
                                </td>
                                <td class="px-5 py-3.5 text-center font-mono-code font-semibold">{{ $coupon->redemptions_count }}{{ $coupon->max_redemptions ? ' / ' . $coupon->max_redemptions : '' }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    @if($coupon->is_active)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Aktif</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <button type="button" @click="editCouponId = {{ $coupon->id }}; couponModalOpen = true"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">Ubah</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada kupon.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($coupons->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $coupons->links() }}</div>
            @endif
        </div>

        <!-- Modal Kupon (buat & ubah) -->
        <div x-show="couponModalOpen"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             style="display: none;" @keydown.escape.window="couponModalOpen = false">
            <div @click.away="couponModalOpen = false" class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
                @foreach(array_merge([null], $coupons->items()) as $c)
                    <form x-show="editCouponId === {{ $c ? $c->id : "'new'" }}" x-cloak
                          action="{{ $c ? route('admin.billing.coupons.update', $c->id) : route('admin.billing.coupons.store') }}"
                          method="POST" class="p-6 space-y-4">
                        @csrf
                        @if($c) @method('PUT') @endif
                        <div>
                            <h3 class="font-bold text-slate-900 text-base">{{ $c ? 'Ubah Kupon ' . $c->code : 'Buat Kupon Baru' }}</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            @unless($c)
                                <div>
                                    <label class="{{ $labelClass }}">Kode</label>
                                    <input type="text" name="code" required maxlength="40" placeholder="HEMAT10" class="{{ $inputClass }} font-mono-code uppercase">
                                </div>
                            @endunless
                            <div class="{{ $c ? 'col-span-2' : '' }}">
                                <label class="{{ $labelClass }}">Nama</label>
                                <input type="text" name="name" required maxlength="120" value="{{ $c?->name }}" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Jenis</label>
                                <select name="type" class="{{ $inputClass }}">
                                    <option value="percent" {{ $c?->type === 'percent' ? 'selected' : '' }}>Persen</option>
                                    <option value="fixed" {{ $c?->type === 'fixed' ? 'selected' : '' }}>Nominal (Rp)</option>
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Nilai</label>
                                <input type="number" name="value" required min="0.01" step="0.01" value="{{ $c?->value }}" class="{{ $inputClass }} font-mono-code">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Maks. Diskon (Rp)</label>
                                <input type="number" name="max_discount" min="0" step="1" value="{{ $c?->max_discount }}" class="{{ $inputClass }} font-mono-code">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Min. Pembelian (Rp)</label>
                                <input type="number" name="min_order_amount" min="0" step="1" value="{{ $c?->min_order_amount }}" class="{{ $inputClass }} font-mono-code">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Kuota Total</label>
                                <input type="number" name="max_redemptions" min="1" value="{{ $c?->max_redemptions }}" placeholder="Tanpa batas" class="{{ $inputClass }} font-mono-code">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Per Pelanggan</label>
                                <input type="number" name="max_per_customer" required min="0" value="{{ $c?->max_per_customer ?? 1 }}" class="{{ $inputClass }} font-mono-code">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Mulai</label>
                                <input type="date" name="starts_at" value="{{ $c?->starts_at?->format('Y-m-d') }}" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Berakhir</label>
                                <input type="date" name="ends_at" value="{{ $c?->ends_at?->format('Y-m-d') }}" class="{{ $inputClass }}">
                            </div>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" {{ ($c?->is_active ?? true) ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                            <span class="text-sm text-slate-700">Aktif</span>
                        </label>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="couponModalOpen = false" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200">Batal</button>
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold">Simpan Kupon</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ================================================================== --}}
    {{-- PAJAK --}}
    {{-- ================================================================== --}}
    @if($tab === 'taxes')
        <div class="bg-white p-5 rounded-lg border border-amber-200">
            <div class="flex items-start gap-3">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">Penting</span>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Tarif pajak aktif saat ini hanya diterapkan pada <strong>faktur perpanjangan langganan</strong>.
                    Harga di checkout belum menambahkan pajak. Bila lebih dari satu tarif aktif untuk negara yang sama,
                    yang dipakai adalah tarif yang paling baru dibuat.
                </p>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                <h3 class="font-bold text-slate-900 text-base">Tarif Pajak</h3>
                <button type="button" @click="editTaxId = 'new'; taxModalOpen = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold shrink-0">Tambah Tarif</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Kode</th>
                            <th class="px-5 py-3.5">Nama</th>
                            <th class="px-5 py-3.5 text-right">Tarif</th>
                            <th class="px-5 py-3.5 text-center">Negara</th>
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($taxRates as $tax)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5 font-mono-code font-bold text-slate-900">{{ $tax->code }}</td>
                                <td class="px-5 py-3.5 text-slate-700">{{ $tax->name }}</td>
                                <td class="px-5 py-3.5 text-right font-mono-code font-semibold">{{ rtrim(rtrim(number_format((float) $tax->rate * 100, 2, ',', '.'), '0'), ',') }}%</td>
                                <td class="px-5 py-3.5 text-center font-mono-code">{{ $tax->country }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    @if($tax->is_active)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Aktif</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <button type="button" @click="editTaxId = {{ $tax->id }}; taxModalOpen = true"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">Ubah</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada tarif pajak.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Pajak -->
        <div x-show="taxModalOpen"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             style="display: none;" @keydown.escape.window="taxModalOpen = false">
            <div @click.away="taxModalOpen = false" class="bg-white rounded-lg border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
                @foreach($taxRates->prepend(null) as $t)
                    <form x-show="editTaxId === {{ $t ? $t->id : "'new'" }}" x-cloak
                          action="{{ $t ? route('admin.billing.taxes.update', $t->id) : route('admin.billing.taxes.store') }}"
                          method="POST" class="p-6 space-y-4">
                        @csrf
                        @if($t) @method('PUT') @endif
                        <h3 class="font-bold text-slate-900 text-base">{{ $t ? 'Ubah ' . $t->code : 'Tambah Tarif Pajak' }}</h3>
                        @unless($t)
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="{{ $labelClass }}">Kode</label>
                                    <input type="text" name="code" required maxlength="30" placeholder="PPN_12" class="{{ $inputClass }} font-mono-code uppercase">
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Negara</label>
                                    <input type="text" name="country" required maxlength="2" value="ID" class="{{ $inputClass }} font-mono-code uppercase">
                                </div>
                            </div>
                        @endunless
                        <div>
                            <label class="{{ $labelClass }}">Nama</label>
                            <input type="text" name="name" required maxlength="100" value="{{ $t?->name }}" placeholder="PPN 12%" class="{{ $inputClass }}">
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Tarif (%)</label>
                            <input type="number" name="rate_percent" required min="0" max="100" step="0.01" value="{{ $t ? round((float) $t->rate * 100, 2) : '' }}" class="{{ $inputClass }} font-mono-code">
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" {{ ($t?->is_active ?? false) ? 'checked' : '' }} class="w-4 h-4 rounded border-slate-300 text-black focus:ring-black">
                            <span class="text-sm text-slate-700">Aktif</span>
                        </label>
                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="taxModalOpen = false" class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200">Batal</button>
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold">Simpan</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ================================================================== --}}
    {{-- SALDO PELANGGAN --}}
    {{-- ================================================================== --}}
    @if($tab === 'credits')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form action="{{ route('admin.billing.credits.adjust') }}" method="POST" class="bg-white rounded-lg border border-slate-200 overflow-hidden lg:col-span-1">
                @csrf
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900 text-base">Sesuaikan Saldo</h3>
                    <p class="text-xs text-slate-500">Saldo tidak dapat dikurangi melebihi saldo yang ada.</p>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="{{ $labelClass }}">Pelanggan</label>
                        <select name="customer_id" required class="{{ $inputClass }}">
                            <option value="">— Pilih pelanggan —</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->full_name }} ({{ $customer->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $labelClass }}">Arah</label>
                            <select name="direction" class="{{ $inputClass }}">
                                <option value="add">Tambah</option>
                                <option value="subtract">Kurangi</option>
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Nominal (Rp)</label>
                            <input type="number" name="amount" required min="1" step="1" value="{{ old('amount') }}" class="{{ $inputClass }} font-mono-code">
                        </div>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Alasan</label>
                        <input type="text" name="reason" required maxlength="255" value="{{ old('reason') }}" class="{{ $inputClass }}">
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-slate-100 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold">Simpan</button>
                </div>
            </form>

            <div class="bg-white rounded-lg border border-slate-200 overflow-hidden lg:col-span-2">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="font-bold text-slate-900 text-base">Saldo Terbesar</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                            <tr>
                                <th class="px-5 py-3.5">Pelanggan</th>
                                <th class="px-5 py-3.5 text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($balances as $row)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-slate-900">{{ $row->customer?->full_name ?? '-' }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $row->customer?->email ?? '-' }}</div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-mono-code font-bold text-slate-900">{{ $rp($row->balance) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada pelanggan dengan saldo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Mutasi Saldo</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Waktu</th>
                            <th class="px-5 py-3.5">Pelanggan</th>
                            <th class="px-5 py-3.5">Jenis & Alasan</th>
                            <th class="px-5 py-3.5 text-right">Mutasi</th>
                            <th class="px-5 py-3.5 text-right">Saldo Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($creditTransactions as $tx)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5 font-mono-code text-slate-600">{{ $tx->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                                <td class="px-5 py-3.5 text-slate-700">{{ $tx->customer?->full_name ?? '-' }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200">{{ $tx->type }}</span>
                                    <span class="text-[11px] text-slate-500 block mt-0.5">{{ $tx->reason }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono-code font-bold {{ (float) $tx->amount >= 0 ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ (float) $tx->amount >= 0 ? '+' : '−' }}{{ $rp(abs((float) $tx->amount)) }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono-code text-slate-900">{{ $rp($tx->balance_after) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada mutasi saldo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($creditTransactions->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $creditTransactions->links() }}</div>
            @endif
        </div>
    @endif

    {{-- ================================================================== --}}
    {{-- MARGIN RIIL --}}
    {{-- ================================================================== --}}
    @if($tab === 'margin')
        @if($missingCost > 0)
            <div class="bg-white p-5 rounded-lg border border-amber-200">
                <div class="flex items-start gap-3">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">Data Belum Lengkap</span>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        {{ $missingCost }} order lunas dalam 60 hari terakhir belum punya catatan pembelian Supplier, sehingga biayanya
                        belum ikut dihitung. Catat pembeliannya di <a href="{{ route('admin.fulfillment.index') }}" class="font-semibold text-slate-800 underline underline-offset-2">Papan Fulfillment</a>
                        agar margin di bawah akurat.
                    </p>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Margin per Bulan</h3>
                <p class="text-xs text-slate-500">Pendapatan dari faktur lunas dibandingkan biaya riil pembelian Supplier, bukan harga rencana paket.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Bulan</th>
                            <th class="px-5 py-3.5 text-right">Pendapatan</th>
                            <th class="px-5 py-3.5 text-right">Biaya Supplier</th>
                            <th class="px-5 py-3.5 text-right">Margin</th>
                            <th class="px-5 py-3.5 text-right">Margin %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($monthly as $row)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5 font-semibold text-slate-900">{{ $row['label'] }}</td>
                                <td class="px-5 py-3.5 text-right font-mono-code">{{ $rp($row['revenue']) }}</td>
                                <td class="px-5 py-3.5 text-right font-mono-code">{{ $rp($row['cost']) }}</td>
                                <td class="px-5 py-3.5 text-right font-mono-code font-bold {{ $row['margin'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $row['margin'] < 0 ? '−' : '' }}{{ $rp(abs($row['margin'])) }}</td>
                                <td class="px-5 py-3.5 text-right font-mono-code {{ ($row['margin_percent'] ?? 0) < 0 ? 'text-red-700' : 'text-slate-700' }}">{{ $row['margin_percent'] !== null ? $row['margin_percent'] . '%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Margin per Order (60 Hari)</h3>
                <p class="text-xs text-slate-500">Selisih terhadap harga rencana menunjukkan perubahan harga di Supplier.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Order</th>
                            <th class="px-5 py-3.5 text-right">Harga Jual</th>
                            <th class="px-5 py-3.5 text-right">Biaya Riil</th>
                            <th class="px-5 py-3.5 text-right">Selisih vs Rencana</th>
                            <th class="px-5 py-3.5 text-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($perOrder as $row)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-slate-900 block">{{ $row['order']->vpsSpec?->name ?? 'Paket' }}</span>
                                    <span class="text-[11px] text-slate-400 font-mono-code">#ORD-{{ str_pad($row['order']->id, 3, '0', STR_PAD_LEFT) }} · {{ $row['order']->customer?->full_name ?? '-' }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono-code">{{ $rp($row['revenue']) }}</td>
                                <td class="px-5 py-3.5 text-right font-mono-code">
                                    @if($row['cost'] !== null)
                                        {{ $rp($row['cost']) }}
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">Belum dicatat</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono-code {{ ($row['deviation'] ?? 0) > 0 ? 'text-red-700' : 'text-slate-600' }}">
                                    {{ $row['deviation'] !== null ? (($row['deviation'] > 0 ? '+' : ($row['deviation'] < 0 ? '−' : '')) . $rp(abs($row['deviation']))) : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono-code font-bold {{ ($row['margin'] ?? 0) < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                                    @if($row['margin'] !== null)
                                        {{ $row['margin'] < 0 ? '−' : '' }}{{ $rp(abs($row['margin'])) }}
                                        <span class="text-[10px] text-slate-500 block font-normal">{{ $row['margin_percent'] }}%</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500 text-sm">Belum ada order lunas dalam 60 hari terakhir.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
