@extends('layouts.admin', ['title' => 'Webhook Log', 'headerTitle' => 'Payment Gateway & Webhook', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{ openId: null }">

    @php
        $inputClass = 'px-3 py-2 rounded-lg border border-slate-300 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $failedWebhooks = $counts['failed'];
    @endphp

    @include('admin.partials.payment-tabs')

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Gagal Diproses</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold {{ $counts['failed'] > 0 ? 'text-red-600' : 'text-slate-900' }} font-mono-code">{{ $counts['failed'] }}</span>
                <span class="text-xs text-slate-500">Dapat Diproses Ulang</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Tertahan</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold {{ $counts['received'] > 0 ? 'text-amber-600' : 'text-slate-900' }} font-mono-code">{{ $counts['received'] }}</span>
                <span class="text-xs text-slate-500">Diterima, Belum Selesai</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Berhasil</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-emerald-600 font-mono-code">{{ $counts['processed'] }}</span>
                <span class="text-xs text-emerald-600 font-medium">Pembayaran Tercatat</span>
            </div>
        </div>
        <div class="bg-white p-5 rounded-lg border border-slate-200">
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Total Webhook</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-extrabold text-slate-900 font-mono-code">{{ $counts['total'] }}</span>
                <span class="text-xs text-slate-500">Sepanjang Waktu</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 space-y-3">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Riwayat Webhook</h3>
                <p class="text-xs text-slate-500">
                    Bukti saat pelanggan menyatakan sudah membayar tetapi pesanan belum aktif.
                    Hanya webhook dengan signature sah yang dapat diproses ulang.
                </p>
            </div>

            <form method="GET" action="{{ route('admin.webhooks.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari Ref ID"
                       class="{{ $inputClass }} font-mono-code">
                <select name="status" class="{{ $inputClass }}">
                    <option value="">Semua Status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select name="provider" class="{{ $inputClass }}">
                    <option value="">Semua Sumber</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider }}" {{ ($filters['provider'] ?? '') === $provider ? 'selected' : '' }}>{{ $provider }}</option>
                    @endforeach
                </select>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $inputClass }}">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $inputClass }}">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black hover:bg-neutral-800 text-white text-xs font-bold transition-colors">
                    Saring
                </button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.webhooks.index') }}"
                       class="px-4 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold border border-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider border-y border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Waktu</th>
                        <th class="px-5 py-3.5">Sumber & Ref ID</th>
                        <th class="px-5 py-3.5">Order</th>
                        <th class="px-5 py-3.5 text-center">HTTP</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($events as $event)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3.5 font-mono-code text-slate-700">
                                {{ $event->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}
                                @if($event->attempts > 1)
                                    <span class="text-[10px] text-slate-400 block">{{ $event->attempts }}× percobaan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-bold text-slate-900 block">{{ $event->provider }}</span>
                                <span class="text-[11px] text-slate-500 font-mono-code break-all">{{ $event->event_id }}</span>
                                @if($event->event_type)
                                    <span class="text-[10px] text-slate-400 block">{{ $event->event_type }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 font-mono-code">
                                @if($event->order_id)
                                    <a href="{{ route('admin.orders.history', $event->order_id) }}" class="font-semibold text-slate-900 underline underline-offset-2">
                                        #{{ $event->order_id }}
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-center font-mono-code font-semibold {{ ($event->http_status ?? 200) >= 400 ? 'text-red-700' : 'text-slate-600' }}">
                                {{ $event->http_status ?? '—' }}
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                @if($event->processing_status === 'processed')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>{{ $event->status_label }}
                                    </span>
                                @elseif($event->processing_status === 'failed')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-red-50 text-red-800 border border-red-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>{{ $event->status_label }}
                                    </span>
                                @elseif($event->processing_status === 'received')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>{{ $event->status_label }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-[11px] font-semibold bg-slate-50 text-slate-700 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>{{ $event->status_label }}
                                    </span>
                                @endif
                                @if($event->signature_verified)
                                    <span class="text-[10px] text-slate-400 block mt-1">Signature sah</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1 flex-wrap">
                                    <button type="button" @click="openId = openId === {{ $event->id }} ? null : {{ $event->id }}"
                                            class="px-2.5 py-1 rounded text-[11px] font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-200">
                                        Payload
                                    </button>
                                    @if($event->isReplayable())
                                        <form action="{{ route('admin.webhooks.replay', $event->id) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Proses ulang webhook ini dari payload yang tersimpan?');">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1 rounded text-[11px] font-semibold bg-black hover:bg-neutral-800 text-white">
                                                Proses Ulang
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr x-show="openId === {{ $event->id }}" x-cloak>
                            <td colspan="6" class="px-5 py-4 bg-slate-50">
                                @if($event->processing_error)
                                    <div class="rounded-lg border border-red-200 bg-red-50 p-3 mb-3">
                                        <span class="text-xs font-semibold text-red-700 uppercase tracking-wider block mb-1">Pesan Kesalahan</span>
                                        <p class="text-xs text-red-800 font-mono-code break-all">{{ $event->processing_error }}</p>
                                    </div>
                                @endif
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3 text-[11px]">
                                    <div><span class="text-slate-500">IP Pengirim:</span> <span class="font-mono-code text-slate-900">{{ $event->ip_address ?? '—' }}</span></div>
                                    <div><span class="text-slate-500">Diproses:</span> <span class="font-mono-code text-slate-900">{{ $event->processed_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') ?? '—' }}</span></div>
                                    <div>
                                        <span class="text-slate-500">Proses ulang terakhir:</span>
                                        <span class="font-mono-code text-slate-900">
                                            {{ $event->last_replayed_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}
                                            @if($event->replayer) oleh {{ $event->replayer->full_name ?? $event->replayer->email }} @endif
                                        </span>
                                    </div>
                                </div>
                                <pre class="font-mono-code text-[11px] text-slate-800 bg-white border border-slate-200 rounded-lg p-3 overflow-x-auto max-h-96">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-slate-500 text-sm">
                                Belum ada webhook yang tercatat{{ array_filter($filters) ? ' untuk saringan ini' : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $events->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
