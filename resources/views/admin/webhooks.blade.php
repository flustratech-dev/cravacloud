@extends('layouts.admin', ['title' => 'Webhook Log', 'headerTitle' => 'Payment Gateway & Webhook', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ openId: null }">

    @php
        $inputClass = 'px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $failedWebhooks = $counts['failed'];
    @endphp

    @include('admin.partials.payment-tabs')

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Gagal Diproses</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $counts['failed'] }}</span>
                <span class="font-mono text-[11px] text-muted">Dapat Diproses Ulang</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Tertahan</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $counts['received'] }}</span>
                <span class="font-mono text-[11px] text-muted">Diterima, Belum Selesai</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Berhasil</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-electric">{{ $counts['processed'] }}</span>
                <span class="font-mono text-[11px] text-electric font-bold">Pembayaran Tercatat</span>
            </div>
        </div>
        <div class="bg-white p-5">
            <span class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest block mb-1">Total Webhook</span>
            <div class="flex items-baseline gap-2">
                <span class="font-display text-2xl font-bold text-ink">{{ $counts['total'] }}</span>
                <span class="font-mono text-[11px] text-muted">Sepanjang Waktu</span>
            </div>
        </div>
    </div>

    <div class="bg-white border border-hairline overflow-hidden">
        <div class="px-5 py-4 border-b border-hairline space-y-3">
            <div>
                <h3 class="font-display font-bold text-ink text-base">Riwayat Webhook</h3>
                <p class="text-xs text-muted mt-0.5">
                    Bukti saat pelanggan menyatakan sudah membayar tetapi pesanan belum aktif.
                    Hanya webhook dengan signature sah yang dapat diproses ulang.
                </p>
            </div>

            <form method="GET" action="{{ route('admin.webhooks.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari Ref ID"
                       class="{{ $inputClass }} font-mono">
                <select name="status" class="{{ $inputClass }} font-mono">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select name="provider" class="{{ $inputClass }} font-mono">
                    <option value="">Semua Sumber</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider }}" {{ ($filters['provider'] ?? '') === $provider ? 'selected' : '' }}>{{ $provider }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $inputClass }} font-mono">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $inputClass }} font-mono">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Saring
                </button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.webhooks.index') }}"
                       class="px-4 py-2 bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase border border-hairline transition-colors">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-y border-hairline">
                    <tr>
                        <th class="px-5 py-3.5">Waktu</th>
                        <th class="px-5 py-3.5">Sumber &amp; Ref ID</th>
                        <th class="px-5 py-3.5">Order</th>
                        <th class="px-5 py-3.5 text-center">HTTP</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($events as $event)
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5 font-mono text-ink">
                                {{ $event->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}
                                @if($event->attempts > 1)
                                    <span class="text-[10px] text-muted block">{{ $event->attempts }}&times; percobaan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-ink block">{{ $event->provider }}</span>
                                <span class="text-[11px] text-muted font-mono break-all">{{ $event->event_id }}</span>
                                @if($event->event_type)
                                    <span class="text-[10px] text-muted block font-mono">{{ $event->event_type }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-mono">
                                @if($event->order_id)
                                    <a href="{{ route('admin.orders.history', $event->order_id) }}" class="font-bold text-electric hover:underline">
                                        #{{ $event->order_id }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono font-bold {{ ($event->http_status ?? 200) >= 400 ? 'text-ink' : 'text-muted' }}">
                                {{ $event->http_status ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($event->processing_status === 'processed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">
                                        {{ $event->status_label }}
                                    </span>
                                @elseif($event->processing_status === 'failed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">
                                        {{ $event->status_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-creamDark border border-hairline text-ink">
                                        {{ $event->status_label }}
                                    </span>
                                @endif
                                @if($event->signature_verified)
                                    <span class="font-mono text-[10px] text-muted block mt-1">Signature sah</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <button type="button" @click="openId = openId === {{ $event->id }} ? null : {{ $event->id }}"
                                            class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-white hover:border-ink text-ink border border-hairline cursor-pointer">
                                        Payload
                                    </button>
                                    @if($event->isReplayable())
                                        <form action="{{ route('admin.webhooks.replay', $event->id) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Proses ulang webhook ini dari payload yang tersimpan?');">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase bg-electric hover:bg-electricHover text-white cursor-pointer">
                                                Proses Ulang
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr x-show="openId === {{ $event->id }}" x-cloak>
                            <td colspan="6" class="px-5 py-4 bg-creamDark/40">
                                @if($event->processing_error)
                                    <div class="border-2 border-ink bg-white p-3 mb-3">
                                        <span class="font-mono text-[10px] font-bold text-ink uppercase tracking-wider block mb-1">Pesan Kesalahan</span>
                                        <p class="text-xs text-ink font-mono break-all">{{ $event->processing_error }}</p>
                                    </div>
                                @endif
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3 text-[11px]">
                                    <div><span class="text-muted">IP Pengirim:</span> <span class="font-mono text-ink">{{ $event->ip_address ?? '—' }}</span></div>
                                    <div><span class="text-muted">Diproses:</span> <span class="font-mono text-ink">{{ $event->processed_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') ?? '—' }}</span></div>
                                    <div>
                                        <span class="text-muted">Proses ulang terakhir:</span>
                                        <span class="font-mono text-ink">
                                            {{ $event->last_replayed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}
                                            @if($event->replayer) oleh {{ $event->replayer->full_name ?? $event->replayer->email }} @endif
                                        </span>
                                    </div>
                                </div>
                                <pre class="font-mono text-[11px] text-ink bg-white border border-hairline p-3 overflow-x-auto max-h-96">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada webhook yang tercatat{{ array_filter($filters) ? ' untuk saringan ini' : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
            <div class="px-5 py-4 border-t border-hairline">
                {{ $events->links() }}
            </div>
        @endif
    </div>

</div>
@endsection