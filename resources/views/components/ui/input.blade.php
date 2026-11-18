@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'id' => null,
])

@php
    $inputId = $id ?? ($attributes->get('name') ? 'input-' . $attributes->get('name') : null);
@endphp

<div class="space-y-1.5">
    @if($label)
        <label @if($inputId) for="{{ $inputId }}" @endif class="block font-mono text-[11px] font-bold uppercase tracking-wider text-muted">
            {{ $label }}
        </label>
    @endif

    <input
        @if($inputId) id="{{ $inputId }}" @endif
        {{ $attributes->merge([
            'class' => 'w-full rounded-none bg-white border ' . ($error ? 'border-rose-600' : 'border-hairline') . ' px-3.5 py-2.5 text-xs sm:text-sm font-mono text-ink placeholder:text-muted/60 focus:outline-none focus:border-electric transition-colors',
        ]) }}
    >

    @if($hint && !$error)
        <p class="font-mono text-[10px] text-muted">{{ $hint }}</p>
    @endif

    @if($error)
        <p class="font-mono text-[11px] font-semibold text-rose-700">{{ $error }}</p>
    @endif
</div>
