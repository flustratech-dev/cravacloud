@extends('layouts.admin', ['title' => 'Fulfillment', 'headerTitle' => 'Papan Kerja Fulfillment', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    purchaseModalOpen: false,
    deliverModalOpen: false,
    target: { id: null, label: '', needsUrl: false },
    copied: null,
    openPurchase(id, label) { this.target = { id: id, label: label, needsUrl: false }; this.purchaseModalOpen = true; },
    openDeliver(id, label, needsUrl) { this.target = { id: id, label: label, needsUrl: needsUrl }; this.deliverModalOpen = true; },
    copy(id) {
        const el = document.getElementById('handover-' + id);
        if (!el) return;
        navigator.clipboard.writeText(el.value).then(() => { this.copied = id; setTimeout(() => this.copied = null, 2000); });
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

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Sedang Dikerjakan</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $workingCount }}</span>
                <span class="font-mono text-[11px] text-muted">Order Dibayar</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Melewati Ambang</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold {{ $overdueCount > 0 ? 'text-electric' : 'text-ink' }}">{{ $overdueCount }}</span>
                <span class="font-mono text-[11px] text-muted">Lebih dari {{ \App\Services\FulfillmentService::humanMinutes($slaMinutes) }}</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Diserahkan Hari Ini</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-electric">{{ $deliveredTodayCount }}</span>
                <span class="font-mono text-[11px] text-electric font-bold">Layanan</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Ambang Waktu Tanggap</span>
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-xl font-bold text-ink">{{ \App\Services\FulfillmentService::humanMinutes($slaMinutes) }}</span>
                <a href="{{ route('admin.settings.index', ['tab' => 'notification']) }}" class="font-mono text-xs text-electric hover:underline">Ubah</a>
            </div>
        </div>
    </div>

    <!-- Papan -->
    <div class="overflow-x-auto pb-2">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 lg:min-w-[1100px]">
            @foreach($columns as $columnKey => $columnLabel)
                @php $items = $board[$columnKey]; @endphp
                <div class="bg-creamDark/40 border border-hairline flex flex-col min-h-[12rem]">
                    <div class="px-4 py-3 border-b border-hairline bg-white flex items-center justify-between">
                        <span class="font-mono text-[11px] font-bold text-ink uppercase tracking-wider">{{ $columnLabel }}</span>
                        <span class="font-mono text-[10px] font-bold {{ $items->count() > 0 ? 'bg-electric text-white' : 'bg-creamDark text-muted border border-hairline' }} px-2 py-0.5">{{ $items->count() }}</span>
                    </div>

                    <div class="p-3 space-y-3 flex-1">
                        @forelse($items as $order)
                            @php
                                $overdue = $fulfillment->isOverdue($order);
                                $waited = $fulfillment->minutesSincePaid($order);
                                $label = '#' . $order->id . ' · ' . ($order->vpsSpec?->name ?? 'Paket');
                            @endphp
                            <div class="bg-white p-4 border {{ $overdue ? 'border-2 border-ink' : 'border-hairline' }}">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <div class="min-w-0">
                                        <span class="font-display font-bold text-ink text-sm block truncate">{{ $order->vpsSpec?->name ?? 'Paket' }}</span>
                                        <span class="text-[11px] text-muted font-mono">#ORD-{{ str_pad($order->id, 3, '0', STR_PAD_LEFT) }}</span>
                                    </div>
                                    @if($overdue)
                                        <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream shrink-0">Lewat</span>
                                    @endif
                                </div>

                                <div class="text-[11px] text-muted space-y-0.5 mb-3">
                                    <div class="truncate font-semibold text-ink">
                                        {{ $order->customer?->full_name ?? '-' }}
                                        @if($order->customer?->phone)
                                            <span class="text-muted font-normal">({{ $order->customer->phone }})</span>
                                        @endif
                                    </div>
                                    <div class="font-mono text-ink truncate">{{ $order->hostname ?? '-' }}</div>
                                    <div>{{ $order->provider_label }} &middot; {{ ucfirst($order->datacenter_location) }} &middot; {{ $order->os_label ?? $order->os }}</div>
                                    <div>{{ $order->control_panel_label }}</div>
                                    @if($order->isDatabasePackage())
                                        <div class="text-[10px] bg-creamDark border border-hairline px-1.5 py-0.5 text-ink font-mono w-fit mt-1">
                                            DB: {{ strtoupper($order->db_engine ?? 'PG') }} &middot; Mgr: {{ $order->db_manager }}
                                        </div>
                                    @endif
                                    <div class="font-mono text-ink font-bold pt-1">Rp {{ number_format((float) $order->amount, 0, ',', '.') }}</div>
                                    @if($waited !== null && $columnKey !== 'delivered')
                                        <div class="{{ $overdue ? 'text-ink font-bold' : 'text-muted' }} font-mono">
                                            Menunggu {{ \App\Services\FulfillmentService::humanMinutes($waited) }}
                                        </div>
                                    @endif
                                    @if($order->status === 'failed')
                                        <span class="inline-block mt-1 px-2 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">Provisioning Gagal</span>
                                    @endif
                                </div>

                                {{-- ===== Aksi per kolom ===== --}}
                                @if($columnKey === 'awaiting_payment')
                                    <div class="flex flex-wrap gap-1.5">
                                        <form action="{{ route('admin.orders.mark-paid', $order->id) }}" method="POST"
                                              onsubmit="return confirm('Tandai order ini lunas secara manual?');">
                                            @csrf
                                            <input type="hidden" name="note" value="Ditandai lunas dari papan fulfillment.">
                                            <button type="submit" class="px-2.5 py-1.5 font-mono text-[10px] font-bold uppercase bg-electric hover:bg-electricHover text-white cursor-pointer">Tandai Lunas</button>
                                        </form>
                                        <form action="{{ route('admin.orders.cancel', $order->id) }}" method="POST"
                                              onsubmit="return confirm('Batalkan order ini?');">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1.5 font-mono text-[10px] font-bold uppercase bg-white hover:bg-ink hover:text-white text-ink border border-hairline cursor-pointer">Batalkan</button>
                                        </form>
                                    </div>
                                @endif

                                @if($columnKey === 'ready')
                                    <button type="button" @click="openPurchase({{ $order->id }}, @js($label))"
                                            class="w-full px-2.5 py-2 font-mono text-[10px] font-bold uppercase bg-ink hover:bg-electric text-white cursor-pointer">
                                        Catat Pembelian Supplier
                                    </button>
                                @endif

                                @if($columnKey === 'purchased')
                                    @php $purchase = $order->supplierPurchases->sortByDesc('purchased_at')->first(); @endphp
                                    @if($purchase)
                                        <div class="border border-hairline bg-creamDark/40 p-2 mb-2 text-[11px]">
                                            <div class="font-mono font-bold text-ink truncate">{{ $purchase->supplier_order_no }}</div>
                                            <div class="font-mono text-muted">Biaya Rp {{ number_format((float) $purchase->actual_cost, 0, ',', '.') }}</div>
                                        </div>
                                    @endif
                                    <form action="{{ route('admin.fulfillment.stage', $order->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="stage" value="setup">
                                        <button type="submit" class="w-full px-2.5 py-2 font-mono text-[10px] font-bold uppercase bg-electric hover:bg-electricHover text-white cursor-pointer">Mulai Setup</button>
                                    </form>
                                @endif

                                @if($columnKey === 'setup')
                                    @php
                                        $checklist = $fulfillment->checklistFor($order);
                                        $progress = $fulfillment->progressFor($order);
                                        $needsUrl = $order->control_panel !== 'none'
                                            && !($order->isDatabasePackage() && $order->db_manager === 'cli_only');
                                    @endphp
                                    <div class="mb-2">
                                        <div class="flex items-center justify-between text-[11px] mb-1">
                                            <span class="text-muted">Daftar periksa</span>
                                            <span class="font-mono font-bold text-ink">{{ $progress }}%</span>
                                        </div>
                                        <div class="h-1.5 bg-creamDark border border-hairline overflow-hidden">
                                            <div class="h-full bg-electric" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 mb-3">
                                        @foreach($checklist as $item)
                                            <form action="{{ route('admin.fulfillment.step', [$order->id, $item['key']]) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-full flex items-start gap-2 text-left px-2 py-1 hover:bg-creamDark/40 cursor-pointer">
                                                    <span class="w-3.5 h-3.5 mt-0.5 border shrink-0 flex items-center justify-center {{ $item['done'] ? 'bg-electric border-electric' : 'border-ink bg-white' }}">
                                                        @if($item['done'])
                                                            <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                                        @endif
                                                    </span>
                                                    <span class="text-[11px] {{ $item['done'] ? 'text-muted line-through' : 'text-ink' }}">{{ $item['label'] }}</span>
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                    @if($progress < 100)
                                        <p class="font-mono text-[10px] text-muted mb-2">Masih ada langkah yang belum dicentang.</p>
                                    @endif
                                    <div class="flex gap-1.5">
                                        <button type="button" @click="openDeliver({{ $order->id }}, @js($label), {{ $needsUrl ? 'true' : 'false' }})"
                                                class="flex-1 px-2.5 py-1.5 font-mono text-[10px] font-bold uppercase bg-electric hover:bg-electricHover text-white cursor-pointer">
                                            Kirim Kredensial
                                        </button>
                                        <form action="{{ route('admin.fulfillment.stage', $order->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="stage" value="purchased">
                                            <button type="submit" title="Kembali ke tahap sebelumnya"
                                                    class="px-2.5 py-1.5 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">Kembali</button>
                                        </form>
                                    </div>
                                @endif

                                @if($columnKey === 'delivered')
                                    <div class="text-[11px] text-muted mb-2">
                                        Diserahkan
                                        <span class="font-mono text-ink font-semibold">{{ ($order->delivered_at ?? $order->starts_at)?->timezone('Asia/Jakarta')->format('d M H:i') ?? '-' }}</span>
                                        @if($order->paid_at && $order->delivered_at)
                                            &middot; {{ \App\Services\FulfillmentService::humanMinutes($fulfillment->minutesSincePaid($order)) }}
                                        @endif
                                    </div>
                                    @php $handover = $fulfillment->handoverText($order, $handoverTemplate); @endphp
                                    @if($handover)
                                        <textarea id="handover-{{ $order->id }}" readonly rows="3"
                                                  class="w-full px-2 py-1.5 border border-hairline bg-creamDark/40 text-[10px] text-ink font-mono mb-1.5">{{ $handover }}</textarea>
                                        <button type="button" @click="copy({{ $order->id }})"
                                                class="w-full px-2.5 py-1.5 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">
                                            <span x-show="copied !== {{ $order->id }}">Salin Teks Serah Terima</span>
                                            <span x-show="copied === {{ $order->id }}" x-cloak>Tersalin</span>
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <p class="font-mono text-[11px] text-muted text-center py-6">Kosong</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Modal Catat Pembelian -->
    <div x-show="purchaseModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="purchaseModalOpen = false">

        <div @click.away="purchaseModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline bg-creamDark/40">
                <h3 class="font-display font-bold text-ink text-base">Catat Pembelian Supplier</h3>
                <p class="text-xs text-muted">Untuk <span class="font-semibold text-ink" x-text="target.label"></span></p>
            </div>

            <form :action="'/admin/fulfillment/' + target.id + '/purchase'" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">Nomor Pesanan Supplier</label>
                    <input type="text" name="supplier_order_no" required maxlength="120" class="{{ $inputClass }} font-mono">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Biaya Riil (Rp)</label>
                    <input type="number" name="actual_cost" required min="0" step="1" class="{{ $inputClass }} font-mono">
                    <p class="text-[11px] text-muted mt-1">Harga yang benar-benar dibayar, termasuk bila ada promo atau selisih kurs.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Tanggal Beli</label>
                        <input type="datetime-local" name="purchased_at" class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Aktif di Supplier s/d</label>
                        <input type="datetime-local" name="supplier_expires_at" class="{{ $inputClass }} font-mono">
                    </div>
                </div>
                <p class="text-[11px] text-muted -mt-2">Kosongkan tanggal untuk memakai sekarang dan masa aktif satu bulan.</p>
                <div>
                    <label class="{{ $labelClass }}">Catatan</label>
                    <textarea name="notes" rows="2" maxlength="500" class="{{ $inputClass }}"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                    <button type="button" @click="purchaseModalOpen = false"
                            class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">Batal</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">Simpan Pembelian</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Kirim Kredensial -->
    <div x-show="deliverModalOpen" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto bg-ink/50 flex items-center justify-center p-4"
         style="display: none;" @keydown.escape.window="deliverModalOpen = false">

        <div @click.away="deliverModalOpen = false"
             class="bg-white border border-ink shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-6 py-4 border-b border-hairline bg-creamDark/40">
                <h3 class="font-display font-bold text-ink text-base">Kirim Kredensial ke Pelanggan</h3>
                <p class="text-xs text-muted">
                    Untuk <span class="font-semibold text-ink" x-text="target.label"></span>.
                    Order menjadi aktif dan surel kredensial terkirim otomatis.
                </p>
            </div>

            <form :action="'/admin/orders/' + target.id + '/provision'" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="{{ $labelClass }}">IP Publik</label>
                    <input type="text" name="public_ip" required placeholder="103.150.xxx.xxx" class="{{ $inputClass }} font-mono">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Username SSH</label>
                    <input type="text" name="ssh_username" value="root" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false" class="{{ $inputClass }} font-mono">
                    <p class="text-[10px] text-muted mt-1">Default <code class="font-mono">root</code>. Ganti bila supplier memberi user lain (mis. <code class="font-mono">ubuntu</code>).</p>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Password SSH Server <span class="text-electric">*</span></label>
                    <input type="text" name="root_password" required minlength="8" placeholder="Password dari retail (min 8 karakter)" class="{{ $inputClass }} font-mono">
                    <p class="text-[10px] text-muted mt-1">Kredensial login yang diterbitkan oleh provider retail.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Port SSH</label>
                        <input type="number" name="ssh_port" value="22" min="1" max="65535" class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">IP Privat</label>
                        <input type="text" name="private_ip" placeholder="Opsional" class="{{ $inputClass }} font-mono">
                    </div>
                </div>
                <div x-show="target.needsUrl">
                    <label class="{{ $labelClass }}">URL Aplikasi / Panel</label>
                    <input type="text" name="app_url" :required="target.needsUrl" :disabled="!target.needsUrl"
                           placeholder="https://panel.domain.com" class="{{ $inputClass }} font-mono">
                </div>
                <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                    <button type="button" @click="deliverModalOpen = false"
                            class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors cursor-pointer">Batal</button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">Kirim &amp; Aktifkan</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection