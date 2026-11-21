{{-- Navigasi tab bersama untuk Orders & Input Order Shopee (PRD 8.1). --}}
<div class="bg-white p-1.5 border border-hairline flex flex-wrap items-center gap-1">
    <a href="{{ route('admin.orders') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ request()->routeIs('admin.orders*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        Antrean Order
    </a>
    <a href="{{ route('admin.shopee') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ request()->routeIs('admin.shopee*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        Input Order Shopee
    </a>
</div>
