@props([
    'tone' => 'cream',
    'padding' => 'p-7',
    'hoverable' => true,
])

@php
    $tones = [
        'cream' => 'bg-cream border-hairline text-ink' . ($hoverable ? ' hover:bg-white' : ''),
        'white' => 'bg-white border-hairline text-ink',
        'creamDark' => 'bg-creamDark border-hairline text-ink',
        'electric' => 'bg-electric border-electric text-white shadow-xl',
        'ink' => 'bg-ink border-hairline text-cream',
    ];
    $cls = 'rounded-none border transition-colors ' . ($tones[$tone] ?? $tones['cream']) . ' ' . $padding;
@endphp

<div {{ $attributes->merge(['class' => $cls]) }}>
    {{ $slot }}
</div>
