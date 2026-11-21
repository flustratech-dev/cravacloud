{{-- Navigasi tab bersama untuk Billing Center & Biaya Upstream / Supplier (PRD 8.1). --}}
@php
    $billingTabs = [
        'invoices' => 'Faktur',
        'subscriptions' => 'Langganan',
        'refunds' => 'Pengembalian Dana',
        'coupons' => 'Kupon',
        'taxes' => 'Pajak',
        'credits' => 'Saldo Pelanggan',
        'margin' => 'Margin Riil',
    ];
    $activeBillingTab = request()->routeIs('admin.supplier*') ? 'supplier' : ($tab ?? 'invoices');
@endphp
<div class="bg-white p-1.5 border border-hairline flex flex-wrap items-center gap-1">
    @foreach($billingTabs as $key => $label)
        <a href="{{ route('admin.billing.index', ['tab' => $key]) }}"
           class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ $activeBillingTab === $key ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
            {{ $label }}
        </a>
    @endforeach
    <a href="{{ route('admin.supplier.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap inline-flex items-center gap-2 {{ $activeBillingTab === 'supplier' ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        <span>Biaya Upstream</span>
        @if(($adminSupplierUrgent ?? 0) > 0)
            <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ $activeBillingTab === 'supplier' ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $adminSupplierUrgent }}</span>
        @endif
    </a>
</div>
