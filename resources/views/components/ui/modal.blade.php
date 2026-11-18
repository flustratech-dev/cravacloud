@props([
    'id' => 'modal-dialog',
    'eyebrow' => null,
    'title' => null,
    'maxWidth' => 'max-w-md',
])

<div id="{{ $id }}" {{ $attributes->merge(['class' => 'fixed inset-0 z-50 hidden bg-ink/75 backdrop-blur-xs flex items-center justify-center p-4']) }}>
    <div class="bg-cream border-2 border-ink {{ $maxWidth }} w-full p-7 relative shadow-2xl rounded-none">
        @if(isset($close))
            {{ $close }}
        @else
            <button type="button" onclick="document.getElementById('{{ $id }}').classList.add('hidden')"
                class="absolute top-5 right-5 text-xs font-mono border border-hairline px-2.5 py-1 hover:bg-ink hover:text-white transition-colors rounded-none cursor-pointer">
                ESC / ✕
            </button>
        @endif

        @if($eyebrow)
            <div class="flex items-center space-x-2 text-electric font-mono text-[11px] font-bold uppercase tracking-widest">
                <span>●</span>
                <span>{{ $eyebrow }}</span>
            </div>
        @endif

        @if($title)
            <h3 class="mt-2 font-display font-bold text-2xl text-ink uppercase">
                {{ $title }}
            </h3>
        @endif

        {{ $slot }}
    </div>
</div>
