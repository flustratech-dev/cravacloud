@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'id' => null,
])

@php
    $selectId = $id ?? ($attributes->get('name') ? 'select-' . $attributes->get('name') : null);
@endphp

<div class="space-y-1.5">
    @if($label)
        <label @if($selectId) for="{{ $selectId }}" @endif class="block font-mono text-[11px] font-bold uppercase tracking-wider text-muted">
            {{ $label }}
        </label>
    @endif

    <select
        @if($selectId) id="{{ $selectId }}" @endif
        {{ $attributes->merge([
            'class' => 'w-full rounded-none bg-white border ' . ($error ? 'border-rose-600' : 'border-hairline') . ' px-3.5 py-2.5 text-xs sm:text-sm font-mono text-ink focus:outline-none focus:border-electric transition-colors',
        ]) }}
    >
        {{ $slot }}
    </select>

    @if($hint && !$error)
        <p class="font-mono text-[10px] text-muted">{{ $hint }}</p>
    @endif

    @if($error)
        <p class="font-mono text-[11px] font-semibold text-rose-700">{{ $error }}</p>
    @endif
</div>
