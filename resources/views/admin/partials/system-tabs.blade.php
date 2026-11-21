{{-- Navigasi tab bersama untuk Pengaturan Sistem, WA Gateway Admin, Template Surel, Jadwal Maintenance, dan Audit Log (PRD 8.1). --}}
<div class="bg-white p-1.5 border border-hairline flex flex-wrap items-center gap-1">
    <a href="{{ route('admin.settings.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ request()->routeIs('admin.settings*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        Pengaturan Sistem
    </a>
    <a href="{{ route('admin.whatsapp.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ request()->routeIs('admin.whatsapp*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        WA Gateway Admin
    </a>
    <a href="{{ route('admin.templates.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ request()->routeIs('admin.templates*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        Template Surel
    </a>
    <a href="{{ route('admin.maintenance.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap inline-flex items-center gap-2 {{ request()->routeIs('admin.maintenance*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        <span>Jadwal Maintenance</span>
        @if(($adminRunningMaintenance ?? 0) > 0)
            <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.maintenance*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $adminRunningMaintenance }}</span>
        @endif
    </a>
    <a href="{{ route('admin.audit.index') }}"
       class="px-4 py-2 font-mono text-xs font-bold uppercase tracking-wider transition-colors whitespace-nowrap {{ request()->routeIs('admin.audit*') ? 'bg-electric text-white' : 'text-ink hover:bg-creamDark/60' }}">
        Audit Log
    </a>
</div>
