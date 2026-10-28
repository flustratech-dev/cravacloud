@extends('layouts.admin', ['title' => 'Riwayat Status Order #' . $order->id, 'headerTitle' => 'Riwayat Status Order #' . $order->id, 'backUrl' => route('admin.orders'), 'backLabel' => 'Kembali ke Daftar Order'])

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Order summary card --}}
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Order #{{ $order->id }}</h2>
                <p class="text-sm text-slate-500">
                    {{ $order->customer->full_name ?? $order->customer->email ?? '—' }}
                    · {{ $order->vpsSpec->name ?? '—' }}
                    · Rp {{ number_format($order->amount, 0, ',', '.') }}
                </p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                Status: {{ $order->status }}
            </span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div>
                <span class="text-slate-400 uppercase">Channel</span>
                <p class="font-semibold text-slate-800">{{ $order->channel }}</p>
            </div>
            <div>
                <span class="text-slate-400 uppercase">Paid at</span>
                <p class="font-semibold text-slate-800">{{ $order->paid_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <span class="text-slate-400 uppercase">Prov. Attempts</span>
                <p class="font-semibold text-slate-800">{{ $order->provisioning_attempts ?? 0 }}</p>
            </div>
            <div>
                <span class="text-slate-400 uppercase">Failure Reason</span>
                <p class="font-semibold text-slate-800 truncate" title="{{ $order->failure_reason }}">
                    {{ $order->failure_reason ?? '—' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Timeline history --}}
    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-6">
        <h3 class="text-md font-bold text-slate-900 mb-4">Timeline Transisi Status</h3>

        @if($histories->isEmpty())
            <p class="text-sm text-slate-500">Belum ada history untuk order ini.</p>
        @else
            <div class="relative pl-8 space-y-4">
                <div class="absolute left-3 top-2 bottom-2 w-px bg-slate-200"></div>
                @foreach($histories as $h)
                    <div class="relative">
                        <div class="absolute -left-6 top-1.5 w-3 h-3 rounded-full
                            @if($h->to_status === 'active') bg-emerald-500
                            @elseif($h->to_status === 'paid') bg-blue-500
                            @elseif(in_array($h->to_status, ['cancelled','failed','terminated','expired'])) bg-rose-500
                            @elseif($h->to_status === 'provisioning') bg-cyan-500
                            @elseif($h->to_status === 'grace_period') bg-amber-500
                            @elseif($h->to_status === 'suspended') bg-red-500
                            @else bg-slate-400 @endif
                        "></div>
                        <div class="bg-slate-50 rounded-lg p-3 border border-slate-200">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-800">
                                        {{ $h->from_status ?? '(created)' }} → <span class="font-mono-code">{{ $h->to_status }}</span>
                                    </p>
                                    @if($h->reason)
                                        <p class="text-xs text-slate-600 mt-1">{{ $h->reason }}</p>
                                    @endif
                                    @if(!empty($h->metadata))
                                        <details class="mt-2">
                                            <summary class="text-xs text-slate-500 cursor-pointer hover:text-slate-700">Metadata</summary>
                                            <pre class="mt-1 text-[10px] bg-white p-2 rounded border border-slate-200 overflow-x-auto">{{ json_encode($h->metadata, JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endif
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-[11px] text-slate-500 font-mono-code">{{ $h->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i:s') }}</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-slate-200 text-slate-700 font-semibold">
                                            {{ $h->actor_type }}
                                        </span>
                                        @if($h->actor)
                                            · {{ $h->actor->full_name ?? $h->actor->email }}
                                        @endif
                                    </p>
                                    @if($h->ip_address)
                                        <p class="text-[10px] text-slate-400 mt-0.5 font-mono-code">{{ $h->ip_address }}</p>
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
