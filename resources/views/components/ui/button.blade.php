@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'arrow' => false,
    'full' => false,
])

@php
    $variants = [
        'primary' => 'bg-electric hover:bg-electricHover text-white border border-electric hover:border-electricHover font-semibold',
        'secondary' => 'border border-ink bg-transparent hover:bg-ink hover:text-white text-ink font-semibold',
        'dark' => 'bg-ink hover:bg-electric text-white border border-ink hover:border-electric font-semibold',
        'white' => 'bg-white hover:bg-cream text-electric border border-white font-bold',
        'ghost' => 'bg-transparent hover:bg-creamDark text-ink border border-hairline font-medium',
        'danger' => 'bg-rose-700 hover:bg-rose-800 text-white border border-rose-700 font-semibold',
    ];

    $sizes = [
        'xs' => 'text-[11px] px-3 py-2',
        'sm' => 'text-xs px-4 py-2.5',
        'md' => 'text-xs sm:text-[13px] px-5 py-3.5',
        'lg' => 'text-xs sm:text-sm px-6 py-4',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-between gap-2.5 rounded-none font-mono uppercase tracking-wider transition-all cursor-pointer active:scale-[0.99] disabled:opacity-50 disabled:cursor-not-allowed',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        $full ? 'w-full' : '',
    ]);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span>{{ $slot }}</span>
        @if($arrow)
            <span aria-hidden="true" class="leading-none">↗</span>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span>{{ $slot }}</span>
        @if($arrow)
            <span aria-hidden="true" class="leading-none">↗</span>
        @endif
    </button>
@endif
