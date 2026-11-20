@extends('layouts.app')

@section('content')
@php
    $catalogSpecs = ($vpsSpecs ?? collect())->keyBy(fn ($s) => strtolower((string) $s->name));

    $starterDefaults = [
        [
            'code' => 'STARTER // 01',
            'tag' => 'KVM NVMe',
            'name' => 'Starter XS',
            'desc' => 'Entry-level hemat untuk aplikasi mikro, bot, web ringan, & dev environment.',
            'cpu' => 2, 'ram' => 2, 'disk' => 40, 'bandwidth' => 20, 'quota' => '0,5 TB / bulan',
            'price' => 115000, 'default_stack' => 'ubuntu2404', 'featured' => false,
        ],
        [
            'code' => 'STARTER // 02',
            'tag' => 'SWEET SPOT',
            'name' => 'Starter S',
            'desc' => 'Pilihan terpopuler untuk Coolify PaaS, website bisnis UKM, & workflow n8n.',
            'cpu' => 2, 'ram' => 4, 'disk' => 60, 'bandwidth' => 30, 'quota' => '1,5 TB / bulan',
            'price' => 165000, 'default_stack' => 'coolify', 'featured' => 'electric',
        ],
        [
            'code' => 'STARTER // 03',
            'tag' => '8GB MEMORY',
            'name' => 'Starter M',
            'desc' => 'Kapasitas RAM lega untuk multi-container Docker, aplikasi web, & cache.',
            'cpu' => 2, 'ram' => 8, 'disk' => 80, 'bandwidth' => 30, 'quota' => '2,5 TB / bulan',
            'price' => 269000, 'default_stack' => 'coolify', 'featured' => false,
        ],
        [
            'code' => 'STARTER // 04',
            'tag' => '100GB NVMe',
            'name' => 'Starter L',
            'desc' => 'Penyimpanan dan kuota trafik ekstra untuk produksi skala menengah & AI Agent.',
            'cpu' => 2, 'ram' => 8, 'disk' => 100, 'bandwidth' => 30, 'quota' => '3,0 TB / bulan',
            'price' => 339000, 'default_stack' => 'coolify', 'featured' => false,
        ],
    ];

    $enterpriseDefaults = [
        [
            'code' => 'ENTERPRISE // 01',
            'tag' => 'HIGH COMPUTE',
            'name' => 'Enterprise Pro',
            'desc' => 'Performa 4 Core untuk SaaS produksi, ERP, & beban database intensif.',
            'cpu' => 4, 'ram' => 8, 'disk' => 180, 'bandwidth' => 35, 'quota' => '5,0 TB / bulan',
            'price' => 975000, 'default_stack' => 'coolify', 'featured' => false,
        ],
        [
            'code' => 'ENTERPRISE // 02',
            'tag' => '16GB MEMORY',
            'name' => 'Enterprise Max',
            'desc' => 'Memori 16 GB untuk Dify AI Studio, database besar, & multi-aplikasi korporat.',
            'cpu' => 4, 'ram' => 16, 'disk' => 220, 'bandwidth' => 40, 'quota' => '6,0 TB / bulan',
            'price' => 1459000, 'default_stack' => 'dify_ollama', 'featured' => false,
        ],
        [
            'code' => 'ENTERPRISE // 03',
            'tag' => '8 vCPU CORES',
            'name' => 'Enterprise Ultra',
            'desc' => 'Tenaga komputasi 8 vCPU untuk trafik tinggi, microservices, & analisis data.',
            'cpu' => 8, 'ram' => 16, 'disk' => 300, 'bandwidth' => 45, 'quota' => '7,0 TB / bulan',
            'price' => 2100000, 'default_stack' => 'dokploy', 'featured' => false,
        ],
        [
            'code' => 'ENTERPRISE // 04',
            'tag' => 'FLAGSHIP SPEC',
            'name' => 'Enterprise Extreme',
            'desc' => 'Kapasitas puncak 32 GB RAM & 380 GB SSD untuk beban enterprise skala besar.',
            'cpu' => 8, 'ram' => 32, 'disk' => 380, 'bandwidth' => 50, 'quota' => '8,0 TB / bulan',
            'price' => 2850000, 'default_stack' => 'dokploy', 'featured' => 'electric',
        ],
    ];

    $hydratePlan = function (array $item) use ($catalogSpecs): array {
        $db = $catalogSpecs->get(strtolower($item['name']));
        if ($db) {
            $item['id'] = $db->id;
            $item['cpu'] = (int) $db->cpu;
            $item['ram'] = (int) $db->ram;
            $item['disk'] = (int) $db->disk;
            $item['bandwidth'] = (int) $db->bandwidth;
            $item['price'] = (float) $db->sell_price;
            $item['quota'] = method_exists($db, 'bandwidthQuotaLabel') ? $db->bandwidthQuotaLabel() : $item['quota'];
            $item['desc'] = $db->tagline ?: $item['desc'];
            $item['tag'] = $db->badge ?: $item['tag'];
            $item['is_active'] = (bool) $db->is_active;
            $item['checkout_url'] = route('checkout', $db->id);
        } else {
            $item['id'] = null;
            $item['is_active'] = true;
            $item['checkout_url'] = route('checkout');
        }
        $item['price_formatted'] = 'Rp ' . number_format((float) $item['price'], 0, ',', '.');
        return $item;
    };

    $starterCards = collect($starterDefaults)->map($hydratePlan);
    $enterpriseCards = collect($enterpriseDefaults)->map($hydratePlan);
    $starterS = $starterCards->firstWhere('name', 'Starter S') ?? $starterCards->first();
    $starterXs = $starterCards->firstWhere('name', 'Starter XS') ?? $starterCards->first();

    $templateNamesLower = $starterCards->merge($enterpriseCards)->pluck('name')->map(fn ($n) => strtolower($n))->all();
    $legacyRetiredNames = ['student basic', 'standard', 'premium', 'startup', 'business', 'mahasiswa basic'];
    $extraSpecs = ($vpsSpecs ?? collect())->filter(function ($s) use ($templateNamesLower, $legacyRetiredNames) {
        $lname = strtolower((string) $s->name);
        if (in_array($lname, $templateNamesLower, true)) {
            return false;
        }
        if (in_array($lname, $legacyRetiredNames, true) && !$s->is_active) {
            return false;
        }
        return true;
    })->values();
@endphp

<main class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 relative">

    <!-- 2. HERO SECTION -->
    <section class="relative pt-8 sm:pt-12 pb-8 lg:pb-12">

        <!-- Top Right Decorative Sparkle + Vertical Right Hairline Line -->
        <div class="hidden lg:flex flex-col items-center absolute right-2 top-12 bottom-10 z-20">
            <svg class="w-6 h-6 text-electric mb-4 animate-spin" style="animation-duration: 18s;" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 0L13.2 9.4L20.5 3.5L14.6 10.8L24 12L14.6 13.2L20.5 20.5L13.2 14.6L12 24L10.8 14.6L3.5 20.5L9.4 13.2L0 12L9.4 10.8L3.5 3.5L10.8 9.4L12 0Z"/>
            </svg>
            <div class="w-[1px] flex-1 bg-hairline"></div>

            <!-- Vertical "SCROLL TO DISCOVER" Pill Card -->
            <a href="#process-bar" class="mt-4 bg-white border border-hairline p-1.5 flex flex-col items-center shadow-sm hover:border-electric transition-colors group">
                <div class="w-6 h-12 overflow-hidden bg-ink mb-2">
                    <img src="{{ asset('images/editorial/server-rack.jpg') }}"
                         alt="CravaCloud Node"
                         class="w-full h-full object-cover mono-editorial group-hover:scale-110 transition-transform">
                </div>
                <span class="writing-vertical text-[9px] font-mono tracking-[0.22em] uppercase text-ink font-semibold py-2">
                    SCROLL TO DISCOVER
                </span>
            </a>
        </div>

        <!-- GIANT "CRAVACLOUD" DISPLAY TYPOGRAPHY -->
        <div class="relative z-30 pr-0 lg:pr-10">
            <h1 class="hero-giant-title">
                CRAVACLOUD
            </h1>
        </div>

        <!-- HERO CONTENT GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start relative z-20 mt-4 lg:-mt-6">

            <!-- Left Column (Cols 1 to 6) -->
            <div class="lg:col-span-6 lg:pt-12 flex flex-col justify-between space-y-8">

                <!-- Headline & Intro Paragraph -->
                <div class="max-w-lg">
                    <h2 class="font-display font-bold text-2xl sm:text-3xl lg:text-[32px] tracking-tight text-ink uppercase leading-tight">
                        DEPLOY CLOUD VPS BERKECEPATAN TINGGI DALAM 60 DETIK
                    </h2>
                    <p class="mt-3.5 text-sm sm:text-[15px] text-muted leading-relaxed">
                        Dapatkan performa compute murni, storage NVMe SSD berkecepatan tinggi di IDC DCI Indonesia Jakarta &amp; edge Asia Pasifik, serta ekosistem 1-Click Application Stacks (Coolify, Database, atau AI Agent) yang langsung aktif otomatis setelah pembayaran QRIS terverifikasi.
                    </p>
                    <div class="mt-5 flex flex-wrap items-center gap-6">
                        <a href="#pricing" class="inline-flex items-center space-x-3 text-xs sm:text-[13px] font-semibold text-ink border-b-[1.5px] border-ink pb-1 hover:text-electric hover:border-electric transition-colors">
                            <span>Mulai Deploy Sekarang Mulai {{ str_replace(' ', '', $starterXs['price_formatted']) }}/bln</span>
                            <span>↗</span>
                        </a>
                    </div>
                </div>

                <!-- ELECTRIC BLUE SQUARE CARD -->
                <div class="w-full max-w-[340px] bg-electric text-white p-6 sm:p-7 rounded-none shadow-lg relative overflow-hidden group">
                    <div class="flex items-center justify-between pb-4 border-b border-white/20">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-7 h-7 rounded-full overflow-hidden border border-white/30 bg-ink flex items-center justify-center">
                                <img src="{{ asset('images/editorial/earth-globe.jpg') }}"
                                     alt="Jakarta Cloud Region"
                                     class="w-full h-full object-cover mono-editorial">
                            </div>
                            <span class="text-[10px] font-mono uppercase tracking-widest text-white/80">ZERO-TOUCH ENGINE</span>
                        </div>
                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 0L13.2 9.4L20.5 3.5L14.6 10.8L24 12L14.6 13.2L20.5 20.5L13.2 14.6L12 24L10.8 14.6L3.5 20.5L9.4 13.2L0 12L9.4 10.8L3.5 3.5L10.8 9.4L12 0Z"/>
                        </svg>
                    </div>

                    <div class="mt-5">
                        <p class="text-[10px] font-mono tracking-[0.18em] uppercase text-white/75">
                            SWEET SPOT BEST DEAL
                        </p>
                        <div class="flex items-center justify-between mt-1 gap-2">
                            <h3 class="font-display font-bold text-xl sm:text-[22px] tracking-tight uppercase whitespace-nowrap">
                                CRAVA STARTER S
                            </h3>
                            <span class="text-[10px] font-mono bg-white/15 px-2 py-0.5 shrink-0">{{ $starterS['cpu'] }} vCPU / {{ $starterS['ram'] }}GB</span>
                        </div>
                    </div>

                    <!-- Countdown Timer -->
                    <div class="mt-5 grid grid-cols-3 gap-2 pt-1">
                        <div>
                            <div class="flex items-baseline justify-between">
                                <span id="cd-days" class="font-display text-3xl sm:text-[34px] font-light tracking-tight">12</span>
                                <span class="text-white/40 font-mono text-lg">:</span>
                            </div>
                            <span class="text-[9px] font-mono tracking-widest uppercase text-white/70 block mt-1">DAYS</span>
                        </div>
                        <div>
                            <div class="flex items-baseline justify-between">
                                <span id="cd-hours" class="font-display text-3xl sm:text-[34px] font-light tracking-tight">08</span>
                                <span class="text-white/40 font-mono text-lg">:</span>
                            </div>
                            <span class="text-[9px] font-mono tracking-widest uppercase text-white/70 block mt-1">HRS</span>
                        </div>
                        <div>
                            <div class="flex items-baseline">
                                <span id="cd-mins" class="font-display text-3xl sm:text-[34px] font-light tracking-tight">42</span>
                                <span id="cd-secs" class="font-mono text-xs text-white/75 ml-1.5">.19s</span>
                            </div>
                            <span class="text-[9px] font-mono tracking-widest uppercase text-white/70 block mt-1">MINS</span>
                        </div>
                    </div>

                    <!-- Interactive Quick Claim inside Blue Box -->
                    <a href="{{ $starterS['checkout_url'] }}"
                       class="mt-5 w-full bg-white text-electric hover:bg-cream text-[11px] font-mono font-bold uppercase tracking-wider py-2.5 px-4 flex items-center justify-between transition-colors">
                        <span>Pilih Paket Starter S — {{ $starterS['price_formatted'] }}</span>
                        <span>↗</span>
                    </a>
                </div>

                <!-- Bottom Left Label -->
                <div class="pt-2 flex items-center space-x-2.5 text-ink">
                    <svg class="w-4 h-4 text-ink shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    <span class="text-xs font-mono tracking-[0.16em] uppercase font-medium">
                        NEXT-GEN AUTOMATED CLOUD INFRASTRUCTURE
                    </span>
                </div>

            </div>

            <!-- Right Column (Cols 7 to 12) - Overlapping Monochrome Visual -->
            <div class="lg:col-span-6 lg:pt-8 relative flex justify-center lg:justify-end items-end">
                <div class="relative w-full max-w-[500px] lg:mr-10">
                    <!-- Floating Live Telemetry Tag -->
                    <div class="absolute top-12 -left-2 sm:left-0 z-30 bg-cream/95 border border-hairline px-3.5 py-2 shadow-sm">
                        <div class="flex items-center space-x-2">
                            <span class="w-2 h-2 bg-electric rounded-full animate-ping"></span>
                            <span class="font-mono text-[10px] font-bold tracking-wider uppercase text-ink">1-CLICK APP STACKS READY</span>
                        </div>
                        <p class="font-mono text-[9px] text-muted mt-0.5">Coolify • PostgreSQL • AI Agent • Docker</p>
                    </div>

                    <!-- High-Contrast Monochrome Astronaut Image -->
                    <div class="relative z-20 overflow-hidden">
                        <img src="{{ asset('images/editorial/astronaut-hero.jpg') }}"
                             alt="CravaCloud Engineer"
                             class="w-full h-[460px] sm:h-[580px] object-cover object-top mono-editorial contrast-125"
                             style="mask-image: linear-gradient(to bottom, black 88%, transparent 100%); -webkit-mask-image: linear-gradient(to bottom, black 88%, transparent 100%);">
                    </div>

                    <!-- Bottom Right Technical Spec Stamp -->
                    <div class="absolute bottom-4 right-4 z-30 bg-ink text-white px-3.5 py-2 font-mono text-[10px] tracking-widest uppercase">
                        CBS NVMe • DEDICATED RAM
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- 3. 3-STEP HORIZONTAL PROCESS BAR -->
    <section id="process-bar" class="border-y border-hairline my-4 relative">
        <div class="grid grid-cols-1 md:grid-cols-12 items-center">

            <!-- Step 01 -->
            <a href="#pricing" class="md:col-span-3 py-7 px-4 sm:px-6 flex items-center space-x-4 border-b md:border-b-0 md:border-r border-hairline hover:bg-creamDark/60 transition-colors group">
                <span class="font-display font-bold text-2xl sm:text-3xl text-electric">01</span>
                <div class="text-xs font-mono tracking-wider uppercase leading-snug text-ink group-hover:translate-x-1 transition-transform">
                    <span class="block font-semibold">PILIH PAKET VPS</span>
                    <span class="block text-muted">STARTER / ENTERPRISE</span>
                </div>
            </a>

            <!-- Step 02 -->
            <a href="#stacks" class="md:col-span-4 py-7 px-4 sm:px-8 flex items-center space-x-4 border-b md:border-b-0 md:border-r border-hairline hover:bg-creamDark/60 transition-colors group">
                <span class="font-display font-bold text-2xl sm:text-3xl text-electric">02</span>
                <div class="text-xs font-mono tracking-wider uppercase leading-snug text-ink group-hover:translate-x-1 transition-transform">
                    <span class="block font-semibold">PILIH 1-CLICK STACK</span>
                    <span class="block text-muted">COOLIFY, DATABASE, ATAU AI</span>
                </div>
            </a>

            <!-- Step 03 -->
            <a href="#pricing" class="md:col-span-3 py-7 px-4 sm:px-8 flex items-center space-x-4 border-b md:border-b-0 border-hairline hover:bg-creamDark/60 transition-colors group">
                <span class="font-display font-bold text-2xl sm:text-3xl text-electric">03</span>
                <div class="text-xs font-mono tracking-wider uppercase leading-snug text-ink group-hover:translate-x-1 transition-transform">
                    <span class="block font-semibold">ZERO-TOUCH DEPLOY</span>
                    <span class="block text-muted">AKTIF &lt; 60 DETIK VIA QRIS</span>
                </div>
            </a>

            <!-- Far Right Square Monochrome Thumbnail -->
            <div class="md:col-span-2 flex justify-end">
                <div class="w-full md:w-36 h-28 md:h-32 bg-ink overflow-hidden relative group border-l border-hairline">
                    <img src="{{ asset('images/editorial/earth-horizon.jpg') }}"
                         alt="CravaCloud Node Horizon"
                         class="w-full h-full object-cover mono-editorial group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent flex items-end p-2.5">
                        <span class="font-mono text-[9px] text-white tracking-widest uppercase">100% ROOT SSH</span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- 4. LOWER EDITORIAL & FEATURES SECTION -->
    <section id="infrastructure" class="py-16 lg:py-24">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-stretch">

            <!-- LEFT COLUMN (4 Cols): Mission text + Framed Coordinate Polaroid Card -->
            <div class="lg:col-span-4 flex flex-col justify-between space-y-10">
                <div>
                    <span class="text-[11px] font-mono font-bold tracking-[0.2em] text-electric uppercase block">
                        CRAVACLOUD INFRASTRUCTURE
                    </span>
                    <h2 class="mt-3 font-display font-bold text-2xl sm:text-[28px] text-ink uppercase leading-[1.2] tracking-tight">
                        KOMPUTASI CLOUD OTOMATIS TANPA SETUP SERVER RIBET.
                    </h2>
                    <p class="mt-4 text-sm text-muted leading-relaxed">
                        Cravacloud bukan sekadar penyedia VPS virtual mesin kosongan. Kami menggabungkan kluster KVM berkecepatan tinggi, alokasi RAM murni tanpa <em class="not-italic font-medium text-ink">overselling</em>, otomasi <em class="not-italic font-medium text-ink">Zero-Touch</em> kurang dari 60 detik, serta ekosistem 1-Click Application Stacks siap pakai.
                    </p>
                    <div class="mt-6">
                        <a href="#pricing" class="inline-flex items-center space-x-2.5 text-xs font-semibold text-ink border-b-[1.5px] border-ink pb-1 hover:text-electric hover:border-electric transition-colors">
                            <span>Lihat Katalog Paket Cravacloud</span>
                            <span>↗</span>
                        </a>
                    </div>
                </div>

                <!-- Framed Monochrome Technical Card with Jakarta Coordinates -->
                <div class="bg-white border border-hairline p-3.5 max-w-[290px] shadow-sm">
                    <div class="w-full h-44 bg-black overflow-hidden relative">
                        <img src="{{ asset('images/editorial/dci-cosmos.jpg') }}"
                             alt="Data Center IDC DCI Indonesia"
                             class="w-full h-full object-cover mono-editorial">
                        <span class="absolute top-2.5 left-2.5 bg-electric text-white font-mono text-[9px] px-2 py-0.5 uppercase tracking-wider">
                            IDC DCI INDONESIA
                        </span>
                    </div>
                    <div class="mt-3 flex items-center justify-between pt-1">
                        <span class="font-mono text-[11px] font-semibold text-ink tracking-tight">
                            6.2088° S 106.8456° E (JKT)
                        </span>
                        <span class="bg-ink text-white px-2.5 py-1 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <!-- CENTER COLUMN (4 Cols): Tall Electric Blue Poster -->
            <div class="lg:col-span-4">
                <div class="w-full h-full min-h-[480px] lg:min-h-[560px] cobalt-duotone flex flex-col justify-between p-6 relative group border border-electric">

                    <!-- Top Telemetry Overlay -->
                    <div class="relative z-20 flex items-center justify-between text-white/90 font-mono text-[10px]">
                        <span class="uppercase tracking-widest bg-black/30 backdrop-blur-xs px-2.5 py-1 border border-white/20">
                            JAKARTA DATA CENTER
                        </span>
                        <span>DEDICATED IPv4</span>
                    </div>

                    <!-- Monochrome Earth Sphere Floating on Electric Blue Background -->
                    <div class="my-auto relative z-10 flex justify-end -mr-10">
                        <div class="w-64 h-64 sm:w-72 sm:h-72 rounded-full overflow-hidden shadow-2xl border border-white/20 bg-black">
                            <img src="{{ asset('images/editorial/earth-globe.jpg') }}"
                                 alt="Monochrome Earth"
                                 class="w-full h-full object-cover earth-img group-hover:scale-105 transition-transform duration-700">
                        </div>
                    </div>

                    <!-- Bottom Ping & Performance Stats -->
                    <div class="relative z-20 bg-ink/90 backdrop-blur-xs text-white p-4 border border-white/15">
                        <div class="flex items-center justify-between text-[11px] font-mono mb-2">
                            <span class="text-white/70">LOCAL ISP PEERING</span>
                            <span class="text-emerald-400 font-bold">● ULTRA-LOW LATENCY</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center font-mono border-t border-white/15 pt-2.5">
                            <div>
                                <span class="text-[10px] text-white/60 block">DCI JKT</span>
                                <span class="text-xs font-bold">8-15 ms</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-white/60 block">STORAGE</span>
                                <span class="text-xs font-bold">CBS NVMe</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-white/60 block">PROVISION</span>
                                <span class="text-xs font-bold">45-75 dtk</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN (4 Cols): 3 Stacked Feature Blocks -->
            <div class="lg:col-span-4 flex flex-col justify-between divide-y divide-hairline">

                <!-- Feature 1: Performa Nyata -->
                <div class="pb-8 pt-2 first:pt-0">
                    <div class="w-11 h-11 border border-hairline bg-white flex items-center justify-center text-electric mb-5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09zM12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-display font-bold text-base sm:text-lg text-ink uppercase tracking-tight">
                        PERFORMA NYATA &amp; STORAGE CBS NVME
                    </h3>
                    <p class="mt-2 text-xs sm:text-sm text-muted leading-relaxed">
                        vCPU dedikasi seimbang, alokasi RAM murni tanpa <em class="not-italic font-medium text-ink">overselling</em>, disk CBS NVMe SSD ultra-cepat, dan gratis 1 IP Publik IPv4 statis khusus di setiap instance.
                    </p>
                </div>

                <!-- Feature 2: 1-Click Application Stacks -->
                <div class="py-8" id="stacks">
                    <div class="w-11 h-11 border border-hairline bg-white flex items-center justify-center text-electric mb-5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v12M18 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM6 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM18 9a9 9 0 0 1-9 9"/>
                        </svg>
                    </div>
                    <h3 class="font-display font-bold text-base sm:text-lg text-ink uppercase tracking-tight">
                        BEBAS PUSING DEVOPS (1-CLICK STACKS)
                    </h3>
                    <p class="mt-2 text-xs sm:text-sm text-muted leading-relaxed">
                        Otomatis terinstal tanpa biaya tambahan: Coolify, Dokploy, CloudPanel, aaPanel, MySQL 8.0, PostgreSQL 16 (pgvector), Redis, n8n, Dify AI, Ollama, hingga Hermes &amp; OpenClaw Agent.
                    </p>
                </div>

                <!-- Feature 3: Self-Service Client Portal -->
                <div class="pt-8">
                    <div class="w-11 h-11 border border-hairline bg-white flex items-center justify-center text-electric mb-5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <h3 class="font-display font-bold text-base sm:text-lg text-ink uppercase tracking-tight">
                        SELF-SERVICE PORTAL &amp; ZERO-TOUCH
                    </h3>
                    <p class="mt-2 text-xs sm:text-sm text-muted leading-relaxed">
                        Akses penuh root SSH, panduan reboot mandiri, permintaan Reinstall OS terkelola, serta pengelolaan faktur dan perpanjangan masa aktif langsung dari client console web.
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- 5. CRAVACLOUD VPS PLANS & PRODUCT CATALOG SECTION -->
    <section id="pricing" class="py-16 lg:py-20 border-t border-hairline">

        <!-- Section Header -->
        <div class="mb-10">
            <h2 class="font-display font-extrabold text-3xl sm:text-4xl lg:text-[46px] text-ink uppercase tracking-tight leading-tight">
                PILIH PAKET CLOUD VPS CRAVACLOUD.
            </h2>
        </div>

        <!-- ================================================================= -->
        <!-- LINI 1 & 2: CRAVACLOUD STARTER & ENTERPRISE SERIES                -->
        <!-- ================================================================= -->
        @php
            $pricingSeries = [
                [
                    'id' => 'pricing-starter',
                    'title' => 'CRAVACLOUD STARTER SERIES',
                    'cards' => $starterCards,
                    'wrapperClass' => 'mb-14',
                ],
                [
                    'id' => 'pricing-enterprise',
                    'title' => 'CRAVACLOUD ENTERPRISE SERIES',
                    'cards' => $enterpriseCards,
                    'wrapperClass' => '',
                ],
            ];
        @endphp

        @foreach($pricingSeries as $series)
            <div id="{{ $series['id'] }}"@if($series['wrapperClass'] !== '') class="{{ $series['wrapperClass'] }}"@endif>
                <div class="pb-4 mb-6 border-b border-hairline text-center">
                    <h3 class="font-display font-bold text-xl sm:text-2xl text-electric uppercase tracking-tight">
                        {{ $series['title'] }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 border-t border-l border-hairline">
                    @foreach($series['cards'] as $plan)
                        @php
                            $isElectric = $plan['featured'] === 'electric';
                            $isInactive = !$plan['is_active'];
                        @endphp
                        <div class="{{ $isElectric ? 'bg-electric text-white border-r border-b border-electric relative shadow-xl' : 'bg-cream hover:bg-white border-r border-b border-hairline transition-colors' }} p-7 flex flex-col">
                            <div>
                                @if($isInactive)
                                    <span class="inline-block mb-2 bg-amber-200 text-amber-950 font-mono font-bold px-2 py-0.5 text-[10px]">MAINTENANCE</span>
                                @endif

                                <h4 class="font-display font-bold text-xl {{ $isElectric ? 'text-white' : 'text-ink' }} uppercase">
                                    {{ $plan['name'] }}
                                </h4>

                                <div class="mt-4 flex items-baseline flex-wrap gap-x-1.5">
                                    <span class="font-display font-extrabold text-2xl xl:text-[28px] tracking-tight {{ $isElectric ? 'text-white' : 'text-ink' }} whitespace-nowrap">{{ $plan['price_formatted'] }}</span>
                                    <span class="text-xs font-mono {{ $isElectric ? 'text-white/75' : 'text-muted' }}">/bulan</span>
                                </div>

                                <a href="{{ $plan['checkout_url'] }}"
                                   class="mt-5 w-full {{ $isInactive ? 'border border-hairline bg-creamDark text-muted hover:border-ink hover:text-ink' : ($isElectric ? 'bg-white hover:bg-cream text-electric font-bold' : 'border border-ink bg-transparent hover:bg-ink hover:text-white text-ink font-semibold') }} text-xs font-mono uppercase py-3.5 px-4 flex items-center justify-between transition-colors">
                                    <span>{{ $isInactive ? 'Sedang Pemeliharaan' : 'Pilih Paket' }}</span>
                                    <span>↗</span>
                                </a>
                            </div>

                            <ul class="mt-6 pt-6 border-t {{ $isElectric ? 'border-white/20 text-white/95' : 'border-hairline text-ink/90' }} space-y-3 text-xs font-mono">
                                <li class="flex justify-between">
                                    <span class="{{ $isElectric ? 'text-white/70' : 'text-muted' }}">vCPU</span>
                                    <span class="font-bold">{{ $plan['cpu'] }} vCPU</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="{{ $isElectric ? 'text-white/70' : 'text-muted' }}">RAM</span>
                                    <span class="font-bold">{{ $plan['ram'] }} GB</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="{{ $isElectric ? 'text-white/70' : 'text-muted' }}">Penyimpanan</span>
                                    <span class="font-bold">{{ $plan['disk'] }} GB NVMe SSD</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="{{ $isElectric ? 'text-white/70' : 'text-muted' }}">Network Speed</span>
                                    <span class="font-bold">{{ $plan['bandwidth'] }} Mbps</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="{{ $isElectric ? 'text-white/70' : 'text-muted' }}">Kuota Transfer</span>
                                    <span class="font-bold">{{ $plan['quota'] }}</span>
                                </li>
                                <li class="flex justify-between">
                                    <span class="{{ $isElectric ? 'text-white/70' : 'text-muted' }}">IP Publik</span>
                                    <span class="font-bold">1x Dedicated IPv4</span>
                                </li>
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if($extraSpecs->isNotEmpty())
            <div id="pricing-custom" class="mt-14">
                <div class="pb-4 mb-6 border-b border-hairline text-center">
                    <h3 class="font-display font-bold text-xl sm:text-2xl text-electric uppercase tracking-tight">
                        PAKET VPS TAMBAHAN
                    </h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 border-t border-l border-hairline">
                    @foreach($extraSpecs as $idx => $extra)
                        @php
                            $extraPrice = 'Rp ' . number_format((float) $extra->sell_price, 0, ',', '.');
                        @endphp
                        <div class="bg-cream hover:bg-white border-r border-b border-hairline p-7 flex flex-col transition-colors">
                            <div>
                                @if(!$extra->is_active)
                                    <span class="inline-block mb-2 bg-amber-200 text-amber-950 font-mono font-bold px-2 py-0.5 text-[10px]">MAINTENANCE</span>
                                @endif
                                <h4 class="font-display font-bold text-xl text-ink uppercase">{{ $extra->name }}</h4>
                                <div class="mt-4 flex items-baseline flex-wrap gap-x-1.5">
                                    <span class="font-display font-extrabold text-2xl xl:text-[28px] tracking-tight text-ink whitespace-nowrap">{{ $extraPrice }}</span>
                                    <span class="text-xs font-mono text-muted">/bulan</span>
                                </div>
                                <a href="{{ route('checkout', $extra->id) }}"
                                   class="mt-5 w-full border border-ink bg-transparent hover:bg-ink hover:text-white text-ink text-xs font-mono font-semibold uppercase py-3.5 px-4 flex items-center justify-between transition-colors">
                                    <span>{{ $extra->is_active ? 'Pilih Paket' : 'Sedang Pemeliharaan' }}</span>
                                    <span>↗</span>
                                </a>
                            </div>
                            <ul class="mt-6 pt-6 border-t border-hairline space-y-3 text-xs font-mono text-ink/90">
                                <li class="flex justify-between"><span class="text-muted">vCPU</span><span class="font-bold">{{ $extra->cpu }} vCPU</span></li>
                                <li class="flex justify-between"><span class="text-muted">RAM</span><span class="font-bold">{{ $extra->ram }} GB</span></li>
                                <li class="flex justify-between"><span class="text-muted">Penyimpanan</span><span class="font-bold">{{ $extra->disk }} GB NVMe SSD</span></li>
                                <li class="flex justify-between"><span class="text-muted">Network Speed</span><span class="font-bold">{{ $extra->bandwidth }} Mbps</span></li>
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </section>

</main>
@endsection

@push('scripts')
<script>
    (function() {
        let totalSeconds = (12 * 86400) + (8 * 3600) + (42 * 60) + 19;

        function updateCountdown() {
            const dEl = document.getElementById('cd-days');
            const hEl = document.getElementById('cd-hours');
            const mEl = document.getElementById('cd-mins');
            const sEl = document.getElementById('cd-secs');
            if (!dEl || !hEl || !mEl || !sEl) return;

            if (totalSeconds <= 0) totalSeconds = 86400;
            totalSeconds--;

            const days = Math.floor(totalSeconds / 86400);
            const hours = Math.floor((totalSeconds % 86400) / 3600);
            const mins = Math.floor((totalSeconds % 3600) / 60);
            const secs = totalSeconds % 60;

            dEl.textContent = String(days).padStart(2, '0');
            hEl.textContent = String(hours).padStart(2, '0');
            mEl.textContent = String(mins).padStart(2, '0');
            sEl.textContent = '.' + String(secs).padStart(2, '0') + 's';
        }
        setInterval(updateCountdown, 1000);
    })();
</script>
@endpush
