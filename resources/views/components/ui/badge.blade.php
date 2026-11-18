@props([
    'variant' => 'muted',
])

@php
    $variants = [
        'electric' => 'bg-electric text-white border border-electric',
        'electric-outline' => 'bg-transparent text-electric border border-electric',
        'white' => 'bg-white text-electric border border-white',
        'ink' => 'bg-ink text-white border border-ink',
        'cream' => 'bg-cream text-ink border border-hairline',
        'muted' => 'bg-creamDark text-muted border border-hairline',
        'emerald' => 'bg-emerald-950 text-emerald-300 border border-emerald-700',
        'amber' => 'bg-amber-100 text-amber-900 border border-amber-400',
        'rose' => 'bg-rose-100 text-rose-900 border border-rose-400',
    ];
    $cls = 'inline-flex items-center gap-1.5 rounded-none font-mono font-bold text-[10px] tracking-widest uppercase px-2.5 py-0.5 ' . ($variants[$variant] ?? $variants['muted']);
@endphp

<span {{ $attributes->merge(['class' => $cls]) }}>
    {{ $slot }}
</span>
