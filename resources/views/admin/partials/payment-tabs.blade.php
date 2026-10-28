{{-- Navigasi tab bersama untuk halaman Payment Gateway dan Webhook Log (Phase 4). --}}
<div class="bg-white p-2 rounded-lg border border-slate-200 flex items-center gap-1 overflow-x-auto">
    <a href="{{ route('admin.gateways.index') }}"
       class="px-4 py-2 rounded-lg text-xs font-bold transition-colors whitespace-nowrap {{ request()->routeIs('admin.gateways*') ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50' }}">
        Payment Gateway
    </a>
    <a href="{{ route('admin.webhooks.index') }}"
       class="px-4 py-2 rounded-lg text-xs font-bold transition-colors whitespace-nowrap inline-flex items-center gap-2 {{ request()->routeIs('admin.webhooks*') ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50' }}">
        Webhook Log
        @if(($failedWebhooks ?? 0) > 0)
            <span class="text-[10px] font-bold bg-black text-white px-1.5 py-0.5 rounded">{{ $failedWebhooks }}</span>
        @endif
    </a>
</div>
