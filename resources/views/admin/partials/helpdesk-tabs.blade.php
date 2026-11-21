{{-- Navigasi tab bersama untuk Helpdesk, Kasus Pelanggaran, dan Broadcast Email (PRD 8.1). --}}
<div class="bg-white p-1.5 border border-hairline flex flex-wrap items-center gap-1">
    <a href="{{ route('admin.tickets') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap inline-flex items-center gap-2 {{ request()->routeIs('admin.tickets*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        <span>Tiket Bantuan</span>
        @if(($adminOpenTickets ?? 0) > 0)
            <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.tickets*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $adminOpenTickets }}</span>
        @endif
    </a>
    <a href="{{ route('admin.abuse.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap inline-flex items-center gap-2 {{ request()->routeIs('admin.abuse*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        <span>Kasus Pelanggaran</span>
        @if(($adminOpenAbuse ?? 0) > 0)
            <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.abuse*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $adminOpenAbuse }}</span>
        @endif
    </a>
    <a href="{{ route('admin.broadcast.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ request()->routeIs('admin.broadcast*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        Broadcast Email
    </a>
</div>
