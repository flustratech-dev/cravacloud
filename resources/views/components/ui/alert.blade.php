@props([
    'type' => 'info',
    'title' => null,
])

@php
    $styles = [
        'info' => 'bg-creamDark border-ink text-ink',
        'electric' => 'bg-electric border-electric text-white',
        'success' => 'bg-emerald-950 border-emerald-600 text-emerald-300',
        'warning' => 'bg-amber-50 border-amber-700 text-amber-950',
        'danger' => 'bg-rose-50 border-rose-700 text-rose-950',
    ];
    $cls = 'rounded-none border p-4 font-mono text-xs ' . ($styles[$type] ?? $styles['info']);
@endphp

<div {{ $attributes->merge(['class' => $cls]) }}>
    @if($title)
        <p class="font-bold uppercase tracking-wider mb-1">{{ $title }}</p>
    @endif
    <div class="leading-relaxed">{{ $slot }}</div>
</div>
