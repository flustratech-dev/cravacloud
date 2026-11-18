@props([
    'tabs' => [],
    'active' => null,
])

@php
    $firstKey = $active ?? (is_array($tabs) && count($tabs) ? array_key_first($tabs) : 'tab-0');
@endphp

<div x-data="{ activeTab: @js($firstKey) }" {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <div class="flex flex-wrap border-b border-hairline gap-0" role="tablist">
        @foreach($tabs as $key => $label)
            <button
                type="button"
                role="tab"
                @click="activeTab = @js((string) $key)"
                :class="activeTab === @js((string) $key)
                    ? 'bg-electric text-white border-electric'
                    : 'bg-cream text-ink border-hairline hover:bg-creamDark'"
                class="px-4 py-2.5 font-mono text-xs font-bold uppercase tracking-wider border border-b-0 -mb-px transition-colors rounded-none cursor-pointer"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div>
        {{ $slot }}
    </div>
</div>
