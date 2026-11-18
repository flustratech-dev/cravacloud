{{-- Navigasi tab bersama untuk halaman Payment Gateway dan Webhook Log (Phase 4). --}}
<div class="bg-white p-1.5 border border-hairline flex flex-wrap items-center gap-1">
    <a href="{{ route('admin.gateways.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ request()->routeIs('admin.gateways*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        Payment Gateway
    </a>
    <a href="{{ route('admin.webhooks.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap inline-flex items-center gap-2 {{ request()->routeIs('admin.webhooks*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        <span>Webhook Log</span>
        @if(($failedWebhooks ?? 0) > 0)
            <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.webhooks*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $failedWebhooks }}</span>
        @endif
    </a>
</div>