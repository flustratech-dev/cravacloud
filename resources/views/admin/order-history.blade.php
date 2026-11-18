@extends('layouts.admin', ['title' => 'Riwayat Status Order #' . $order->id, 'headerTitle' => 'Riwayat Status Order #' . $order->id, 'backUrl' => route('admin.orders'), 'backLabel' => 'Kembali ke Daftar Order'])

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Order summary card --}}
    <div class="bg-white border border-hairline p-6">
        <div class="flex flex-wrap justify-between items-start gap-4 mb-5 pb-4 border-b border-hairline">
            <div>
                <h2 class="font-display text-lg font-bold text-ink">Order #{{ $order->id }}</h2>
                <p class="text-xs text-muted mt-0.5">
                    {{ $order->customer->full_name ?? $order->customer->email ?? '—' }}
                    &middot; {{ $order->vpsSpec->name ?? '—' }}
                    &middot; <span class="font-mono font-bold text-ink">Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                </p>
            </div>
            <span class="px-3 py-1 font-mono text-[11px] font-bold uppercase bg-ink text-cream">
                Status: {{ $order->status }}
            </span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="font-mono text-[10px] text-muted uppercase tracking-widest block">Channel</span>
                <p class="font-mono font-bold text-ink mt-1">{{ $order->channel }}</p>
            </div>
            <div>
                <span class="font-mono text-[10px] text-muted uppercase tracking-widest block">Paid at</span>
                <p class="font-mono font-bold text-ink mt-1">{{ $order->paid_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <span class="font-mono text-[10px] text-muted uppercase tracking-widest block">Prov. Attempts</span>
                <p class="font-mono font-bold text-ink mt-1">{{ $order->provisioning_attempts ?? 0 }}</p>
            </div>
            <div>
                <span class="font-mono text-[10px] text-muted uppercase tracking-widest block">Failure Reason</span>
                <p class="font-semibold text-ink truncate mt-1" title="{{ $order->failure_reason }}">
                    {{ $order->failure_reason ?? '—' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Timeline history --}}
    <div class="bg-white border border-hairline p-6">
        <h3 class="font-display text-sm font-bold text-ink uppercase tracking-wider mb-5 pb-3 border-b border-hairline">Timeline Transisi Status</h3>

        @if($histories->isEmpty())
            <p class="font-mono text-xs text-muted">Belum ada history untuk order ini.</p>
        @else
            <div class="relative pl-8 space-y-4">
                <div class="absolute left-3 top-2 bottom-2 w-px bg-hairline"></div>
                @foreach($histories as $h)
                    <div class="relative">
                        <div class="absolute -left-6 top-2 w-2.5 h-2.5
                            @if($h->to_status === 'active') bg-electric
                            @elseif(in_array($h->to_status, ['cancelled','failed','terminated','expired','suspended'])) bg-ink
                            @else bg-muted @endif
                        "></div>
                        <div class="bg-creamDark/40 p-3.5 border border-hairline">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-ink font-mono">
                                        {{ $h->from_status ?? '(created)' }} &rarr; <span class="font-bold text-electric">{{ $h->to_status }}</span>
                                    </p>
                                    @if($h->reason)
                                        <p class="text-xs text-ink mt-1">{{ $h->reason }}</p>
                                    @endif
                                    @if(!empty($h->metadata))
                                        <details class="mt-2">
                                            <summary class="font-mono text-[11px] text-muted cursor-pointer hover:text-ink">Metadata</summary>
                                            <pre class="mt-1 text-[10px] font-mono bg-white p-2 border border-hairline overflow-x-auto">{{ json_encode($h->metadata, JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-[11px] text-muted font-mono">{{ $h->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}</p>
                                    <p class="text-[10px] text-muted mt-0.5 font-mono">
                                        <span class="inline-block px-1.5 py-0.5 bg-creamDark border border-hairline text-ink font-bold uppercase">
                                            {{ $h->actor_type }}
                                        </span>
                                        @if($h->actor)
                                            &middot; {{ $h->actor->full_name ?? $h->actor->email }}
                                        @endif
                                    </p>
                                    @if($h->ip_address)
                                        <p class="text-[10px] text-muted mt-0.5 font-mono">{{ $h->ip_address }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection