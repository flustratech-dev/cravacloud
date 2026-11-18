@props([
    'heading' => 'KELOLA INFRASTRUKTUR CLOUD DALAM SATU PORTAL',
])

<!-- Left Column: Full-Bleed Astronaut Editorial Visual -->
<div class="hidden lg:flex lg:col-span-5 h-full min-h-0 relative overflow-hidden bg-ink border-r border-hairline select-none p-8 xl:p-10 flex-col justify-between">
    <img src="{{ asset('images/editorial/astronaut-hero.jpg') }}"
         alt="CravaCloud Infrastructure"
         class="absolute inset-0 w-full h-full object-cover object-center mono-editorial">
    <div class="absolute inset-0 bg-gradient-to-b from-black/50 via-transparent to-black/85 pointer-events-none"></div>

    <!-- Top: Transparent Logo + Wordmark -->
    <div class="relative z-10">
        <a href="{{ route('home') }}" class="inline-flex items-center space-x-3 group w-fit">
            <img src="{{ asset('images/logo.svg') }}"
                 onerror="this.src='{{ asset('images/logo.png') }}'"
                 alt="CravaCloud"
                 class="h-8 w-8 object-contain transition-transform duration-300 group-hover:scale-105">
            <span class="font-display font-extrabold text-xl tracking-tighter text-white uppercase">
                CRAVACLOUD
            </span>
        </a>
    </div>

    <!-- Bottom: Clean Editorial Heading -->
    <div class="relative z-10">
        <h2 class="font-display font-black text-2xl xl:text-4xl tracking-tight uppercase leading-[1.08] text-white max-w-md">
            {{ $heading }}
        </h2>
    </div>
</div>
