<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $seoTitle = $title ?? 'CravaCloud VPS NVMe KVM Indonesia Mulai Rp 115rb/bln';
        $seoDescription = $description ?? 'Cloud VPS KVM NVMe di Jakarta (IDC DCI Indonesia) dengan akses root penuh, 1-Click Coolify PaaS, Database, dan AI Agent siap pakai dalam 60 detik.';
        $seoImage = asset('images/promo-banner.webp');
        $seoRobots = $robots ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
        $canonicalUrl = url()->current();
        $googleSiteVerification = config('services.google.site_verification');
    @endphp

    <title>{{ $seoTitle }}</title>
    <meta name="title" content="{{ $seoTitle }}">
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $seoRobots }}">
    <meta name="author" content="CravaCloud">
    <link rel="canonical" href="{{ $canonicalUrl }}">

    @if(!empty($googleSiteVerification))
    <meta name="google-site-verification" content="{{ $googleSiteVerification }}">
    @endif

    <!-- Geo Targeting -->
    <meta name="geo.region" content="ID-JK">
    <meta name="geo.placename" content="Jakarta, Indonesia">
    <meta name="geo.position" content="-6.2088;106.8456">
    <meta name="ICBM" content="-6.2088, 106.8456">

    <!-- Open Graph / WhatsApp / Social Preview -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="CravaCloud Indonesia">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:alt" content="CravaCloud Infrastructure">
    <meta property="og:image:width" content="800">
    <meta property="og:image:height" content="560">
    <meta property="og:locale" content="id_ID">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ $canonicalUrl }}">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">

    <!-- Schema.org JSON-LD Structured Data -->
    @php
        $waGatewayUrl = rtrim(config('whatsapp.url', 'https://wa.cravacloud.id'), '/');

        try {
            $hargaAktif = \App\Models\VpsSpec::where('is_active', true)->pluck('sell_price');
            $hargaTerendah = (int) ($hargaAktif->min() ?: 115000);
            $hargaTertinggi = (int) ($hargaAktif->max() ?: 2850000);
            $jumlahPaket = max(1, $hargaAktif->count());
        } catch (\Throwable $e) {
            [$hargaTerendah, $hargaTertinggi, $jumlahPaket] = [115000, 2850000, 8];
        }

        $schemaData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/') . '#organization',
                    'name' => 'CravaCloud',
                    'alternateName' => [
                        'CravaCloud',
                        'CravaCloud Indonesia',
                        'CravaCloud VPS',
                        'cravacloud',
                    ],
                    'url' => url('/'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => asset('images/favicon-512x512.png'),
                        'width' => 512,
                        'height' => 512,
                    ],
                    'contactPoint' => [
                        '@type' => 'ContactPoint',
                        'contactType' => 'customer service',
                        'availableLanguage' => ['Indonesian', 'English'],
                        'url' => \App\Support\NomorWhatsApp::tautan(),
                    ],
                    'subOrganization' => [
                        '@type' => 'Organization',
                        '@id' => $waGatewayUrl . '#organization',
                        'name' => 'CravaCloud WA Gateway',
                        'url' => $waGatewayUrl,
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/') . '#website',
                    'url' => url('/'),
                    'name' => 'CravaCloud',
                    'publisher' => [
                        '@id' => url('/') . '#organization',
                    ],
                    'inLanguage' => 'id-ID',
                ],
                [
                    '@type' => 'Product',
                    'name' => 'CravaCloud VPS KVM Indonesia',
                    'image' => asset('images/favicon-512x512.png'),
                    'description' => 'Server Virtual Private Server (VPS) berbasis KVM dengan penyimpanan 100% NVMe dan Datacenter Jakarta.',
                    'brand' => [
                        '@type' => 'Brand',
                        'name' => 'CravaCloud',
                    ],
                    'offers' => [
                        '@type' => 'AggregateOffer',
                        'priceCurrency' => 'IDR',
                        'lowPrice' => (string) $hargaTerendah,
                        'highPrice' => (string) $hargaTertinggi,
                        'offerCount' => (string) $jumlahPaket,
                        'priceValidUntil' => '2028-12-31',
                        'availability' => 'https://schema.org/InStock',
                        'url' => route('packages.vps'),
                    ],
                ],
            ],
        ];

        if (! empty($faq ?? [])) {
            $schemaData['@graph'][] = [
                '@type' => 'FAQPage',
                '@id' => $canonicalUrl . '#faq',
                'mainEntity' => collect($faq)->values()
                    ->map(fn (array $butir) => [
                        '@type' => 'Question',
                        'name' => $butir['tanya'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $butir['jawab'],
                        ],
                    ])->all(),
            ];
        }

        if (! empty($breadcrumbs ?? [])) {
            $schemaData['@graph'][] = [
                '@type' => 'BreadcrumbList',
                '@id' => $canonicalUrl . '#breadcrumb',
                'itemListElement' => collect($breadcrumbs)->values()
                    ->map(fn (array $remah, int $i) => array_filter([
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $remah['name'],
                        'item' => $remah['url'] ?? null,
                    ]))->all(),
            ];
        }
    @endphp
    <script type="application/ld+json">
    {!! json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>

    <!-- Favicons & Manifest -->
    @include('partials.favicons')

    <!-- Fonts: Space Grotesk + Inter + JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-cream text-ink antialiased paper-texture flex flex-col min-h-screen selection:bg-electric selection:text-white"
      x-data="{ mobileMenu: false }">
    @include('partials.impersonation-banner')

    @php
        $isHome = request()->routeIs('home');
        $headerNavItems = [
            ['label' => 'Beranda', 'href' => route('home'), 'active' => $isHome],
            ['label' => 'Katalog VPS', 'href' => route('packages.vps'), 'active' => request()->routeIs('packages.*')],
            ['label' => 'Dokumentasi', 'href' => route('docs'), 'active' => request()->routeIs('docs')],
            ['label' => 'Status Sistem', 'href' => route('status'), 'active' => request()->routeIs('status')],
            ['label' => 'Syarat & Ketentuan', 'href' => route('terms'), 'active' => request()->routeIs('terms')],
            ['label' => 'Privasi', 'href' => route('privacy'), 'active' => request()->routeIs('privacy')],
            ['label' => 'Kebijakan Refund', 'href' => route('refund'), 'active' => request()->routeIs('refund')],
        ];
    @endphp

    <!-- Minimalist Editorial Header -->
    <header x-data="{ scrolled: false }"
            x-init="scrolled = window.scrollY > 4"
            @scroll.window="scrolled = window.scrollY > 4"
            :data-scrolled="(scrolled || mobileMenu) ? 'true' : 'false'"
            class="w-full border-b border-transparent bg-cream sticky top-0 z-40 transition-colors duration-200 data-[scrolled=true]:border-hairline data-[scrolled=true]:bg-cream/95 data-[scrolled=true]:backdrop-blur-md">
        <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 h-20 flex items-center justify-between gap-4">
            <!-- Left: Redesigned Transparent Logo + Bold Wordmark -->
            <a href="{{ route('home') }}" class="flex items-center space-x-2.5 xl:space-x-3 group shrink-0">
                <img src="{{ asset('images/logo.svg') }}"
                     onerror="this.src='{{ asset('images/logo.png') }}'"
                     alt="CravaCloud Logo"
                     class="h-7 w-7 xl:h-9 xl:w-9 object-contain transition-transform duration-300 group-hover:scale-105">
                <span class="font-display font-extrabold text-lg xl:text-2xl tracking-tighter text-ink group-hover:text-electric transition-colors uppercase">
                    CRAVACLOUD
                </span>
            </a>

            <!-- Right: Flat Editorial Navigation aligned flush-right next to Masuk CTA + Mobile Toggle -->
            <div class="flex items-center gap-4 min-[960px]:gap-5 xl:gap-6 ml-auto shrink-0">
                <nav aria-label="Navigasi Utama" class="hidden min-[960px]:flex items-center gap-3 lg:gap-4 xl:gap-5 2xl:gap-6 text-[12px] xl:text-[13px] font-medium text-ink/80 whitespace-nowrap">
                    @foreach($headerNavItems as $item)
                        <a href="{{ $item['href'] }}"
                           class="relative inline-block py-1 {{ $item['active'] ? 'text-electric font-semibold' : 'hover:text-electric' }} after:content-[''] after:absolute after:left-0 after:bottom-0 after:h-[1.5px] after:w-full after:bg-electric after:scale-x-0 hover:after:scale-x-100 after:origin-left after:transition-transform after:duration-150 transition-colors">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="flex items-center space-x-3 shrink-0">
                    @auth
                        <a href="{{ auth()->user()->is_admin ? route('admin.index') : route('dashboard.index') }}"
                           class="hidden sm:inline-flex items-center border border-ink bg-white hover:bg-ink hover:text-white font-mono text-xs uppercase tracking-wider font-semibold text-ink px-4 py-2.5 rounded-none transition-colors">
                            {{ auth()->user()->is_admin ? 'Admin Panel' : 'Console' }}
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="hidden sm:inline-flex items-center border border-ink bg-white hover:bg-ink hover:text-white font-mono text-xs uppercase tracking-wider font-semibold text-ink px-4 py-2.5 rounded-none transition-colors">
                            Masuk
                        </a>
                    @endauth

                    <button type="button"
                            @click="mobileMenu = !mobileMenu"
                            aria-label="Toggle Menu"
                            class="min-[960px]:hidden border border-hairline bg-white hover:border-electric p-2.5 text-ink transition-colors rounded-none cursor-pointer">
                        <svg x-show="!mobileMenu" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="square" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg x-show="mobileMenu" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="square" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Drawer -->
        <div x-show="mobileMenu"
             x-cloak
             @click.outside="mobileMenu = false"
             class="min-[960px]:hidden border-t border-hairline bg-cream px-5 py-6 space-y-4">
            <nav aria-label="Navigasi Mobile" class="flex flex-col divide-y divide-hairline font-mono text-xs uppercase tracking-wider">
                @foreach($headerNavItems as $item)
                    <a href="{{ $item['href'] }}" @click="mobileMenu = false"
                       class="py-3 {{ $item['active'] ? 'text-electric font-bold' : 'text-ink hover:text-electric' }} flex justify-between">
                        <span>{{ $item['label'] }}</span><span>→</span>
                    </a>
                @endforeach
                @auth
                    <a href="{{ auth()->user()->is_admin ? route('admin.index') : route('dashboard.index') }}" class="py-3 font-bold text-electric flex justify-between">
                        <span>{{ auth()->user()->is_admin ? 'Admin Panel' : 'Client Console' }}</span><span>↗</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="py-3 font-bold text-electric flex justify-between">
                        <span>Masuk Client Area</span><span>↗</span>
                    </a>
                @endauth
            </nav>
        </div>
    </header>

    <!-- Flash Messages via SweetAlert -->
    @if(session('success'))
        <script>
            (function() {
                function showSuccessAlert() {
                    if (window.showSuccess) {
                        window.showSuccess(@json(session('success')));
                    } else if (window.Swal) {
                        window.Swal.fire({
                            background: '#F8F6F0',
                            color: '#121212',
                            icon: 'success',
                            title: 'Berhasil',
                            text: @json(session('success')),
                            confirmButtonColor: '#001AFF',
                            confirmButtonText: 'Mengerti',
                            timer: 3500,
                            timerProgressBar: true,
                            customClass: {
                                popup: 'rounded-none border-2 border-[#121212] font-mono shadow-xl',
                                confirmButton: 'rounded-none px-5 py-2.5 font-mono text-xs font-bold uppercase tracking-wider',
                            }
                        });
                    } else {
                        setTimeout(showSuccessAlert, 50);
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showSuccessAlert);
                } else {
                    showSuccessAlert();
                }
            })();
        </script>
    @endif

    @if(session('error'))
        <script>
            (function() {
                function showErrorAlert() {
                    if (window.showError) {
                        window.showError(@json(session('error')));
                    } else if (window.Swal) {
                        window.Swal.fire({
                            background: '#F8F6F0',
                            color: '#121212',
                            icon: 'error',
                            title: 'Terjadi Kesalahan',
                            text: @json(session('error')),
                            confirmButtonColor: '#001AFF',
                            confirmButtonText: 'Mengerti',
                            customClass: {
                                popup: 'rounded-none border-2 border-[#121212] font-mono shadow-xl',
                                confirmButton: 'rounded-none px-5 py-2.5 font-mono text-xs font-bold uppercase tracking-wider',
                            }
                        });
                    } else {
                        setTimeout(showErrorAlert, 50);
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showErrorAlert);
                } else {
                    showErrorAlert();
                }
            })();
        </script>
    @endif

    @if($errors->any())
        <script>
            (function() {
                function showValidationErrors() {
                    if (window.Swal) {
                        window.Swal.fire({
                            background: '#F8F6F0',
                            color: '#121212',
                            icon: 'error',
                            title: 'Perhatian',
                            html: `<ul class="text-left text-xs font-mono text-[#686763] list-disc list-inside space-y-1 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>`,
                            confirmButtonColor: '#001AFF',
                            confirmButtonText: 'Mengerti',
                            customClass: {
                                popup: 'rounded-none border-2 border-[#121212] font-mono shadow-xl',
                                confirmButton: 'rounded-none px-5 py-2.5 font-mono text-xs font-bold uppercase tracking-wider',
                            }
                        });
                    } else {
                        setTimeout(showValidationErrors, 50);
                    }
                }
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', showValidationErrors);
                } else {
                    showValidationErrors();
                }
            })();
        </script>
    @endif

    <!-- Main Content -->
    <div class="flex-grow">
        @yield('content')
    </div>

    <!-- Enterprise Editorial Footer -->
    @unless(request()->routeIs('checkout'))
    @php
        try {
            $footerSettings = app(\App\Services\SettingsService::class);
            $footerLegalCompany = $footerSettings->get('company_legal_name', 'PT Flustra Finances Artha');
            $footerCompanyCity = $footerSettings->get('company_city', 'Jakarta');
            $footerSupportEmail = \App\Support\MailIdentity::contactEmail();
            $footerIg = $footerSettings->get('social_instagram');
            $footerTwitter = $footerSettings->get('social_twitter');
            $footerLinkedin = $footerSettings->get('social_linkedin');

            $footerComponents = \Illuminate\Support\Facades\Schema::hasTable('system_components')
                ? \App\Models\SystemComponent::visible()->get()
                : collect();
            $footerAllOperational = $footerComponents->isEmpty()
                || $footerComponents->every(fn ($c) => $c->status === 'operational');
            $footerMaintenanceActive = app(\App\Services\MaintenanceService::class)->isGlobalActive();
        } catch (\Throwable $e) {
            $footerLegalCompany = 'PT Flustra Finances Artha';
            $footerCompanyCity = 'Jakarta';
            $footerSupportEmail = 'support@cravacloud.id';
            $footerIg = null;
            $footerTwitter = null;
            $footerLinkedin = null;
            $footerAllOperational = true;
            $footerMaintenanceActive = false;
        }

        $footerWaTautan = \App\Support\NomorWhatsApp::tautan('Halo Tim CravaCloud, saya ingin konsultasi kebutuhan infrastruktur Cloud VPS.');
    @endphp

    <footer class="border-t border-hairline bg-cream mt-16 sm:mt-20">
        <!-- 1. Pre-Footer Conversion Band -->
        <div class="border-b border-hairline bg-creamDark/60">
            <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 py-8 sm:py-10">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                        </div>
                        <h2 class="font-display font-extrabold text-xl sm:text-2xl lg:text-3xl tracking-tight text-ink uppercase">
                            Siap Aktifkan Server KVM NVMe dalam 60 Detik?
                        </h2>
                        <p class="font-mono text-xs text-muted max-w-2xl leading-relaxed">
                            Penyimpanan CBS NVMe SSD murni di IDC DCI Indonesia Jakarta (8–15 ms), 1x Dedicated IPv4, dan 1-Click Application Stacks langsung aktif otomatis setelah pembayaran QRIS/VA.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <a href="{{ route('packages.vps') }}"
                           class="inline-flex items-center gap-2 px-5 py-3.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                            <span>Lihat Katalog VPS</span>
                            <span>↗</span>
                        </a>
                        <a href="{{ $footerWaTautan }}"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center gap-2 px-5 py-3.5 border border-ink bg-white hover:bg-ink hover:text-white text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                            <span>Konsultasi Teknis</span>
                            <span>↗</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Main Directory Grid (5 Kolom Enterprise) -->
        <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 py-12 sm:py-16">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-10 lg:gap-8">
                <!-- Kolom Brand & Status (4 Kolom) -->
                <div class="lg:col-span-4 space-y-5">
                    <a href="{{ route('home') }}" class="inline-flex items-center space-x-2.5 group">
                        <img src="{{ asset('images/logo.svg') }}"
                             onerror="this.src='{{ asset('images/logo.png') }}'"
                             alt="CravaCloud Logo"
                             class="h-8 w-8 object-contain transition-transform duration-300 group-hover:scale-105">
                        <span class="font-display font-extrabold text-2xl tracking-tighter text-ink group-hover:text-electric transition-colors uppercase">
                            CRAVACLOUD
                        </span>
                    </a>

                    <p class="text-xs text-muted font-mono leading-relaxed">
                        Penyedia infrastruktur Cloud VPS KVM NVMe generasi baru dengan otomatisasi Zero-Touch Engine, akses root penuh, dan ekosistem 1-Click App Stacks siap pakai di Indonesia.
                    </p>

                    <!-- Live Telemetry Status Pill -->
                    <div class="pt-1">
                        <a href="{{ route('status') }}"
                           class="inline-flex items-center gap-2.5 px-3.5 py-2 border border-hairline bg-white hover:border-electric transition-colors group">
                            <span class="relative flex h-2 w-2 shrink-0">
                                @if(!$footerAllOperational || $footerMaintenanceActive)
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                @else
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                @endif
                            </span>
                            <span class="font-mono text-[11px] font-semibold text-ink uppercase tracking-wider group-hover:text-electric">
                                @if($footerMaintenanceActive)
                                    Status: Pemeliharaan Sistem
                                @elseif(!$footerAllOperational)
                                    Status: Sebagian Layanan Terganggu
                                @else
                                    Status: Semua Sistem Normal
                                @endif
                            </span>
                            <span class="font-mono text-[11px] text-muted group-hover:text-electric">→</span>
                        </a>
                    </div>
                </div>

                <!-- Kolom Direktori 1: Produk & Komputasi (2 Kolom) -->
                <div class="lg:col-span-2 space-y-4">
                    <h3 class="font-mono text-xs font-bold uppercase tracking-widest text-ink pb-2 border-b border-hairline">
                        Produk &amp; Infra
                    </h3>
                    <ul class="space-y-2.5 font-mono text-xs text-ink/80">
                        <li>
                            <a href="{{ route('packages.vps') }}" class="hover:text-electric transition-colors block">
                                Katalog VPS NVMe
                            </a>
                        </li>
                        <li>
                            <a href="{{ $isHome ? '#pricing-starter' : route('home') . '#pricing-starter' }}" class="hover:text-electric transition-colors block">
                                Starter Series
                            </a>
                        </li>
                        <li>
                            <a href="{{ $isHome ? '#pricing-enterprise' : route('home') . '#pricing-enterprise' }}" class="hover:text-electric transition-colors block">
                                Enterprise Series
                            </a>
                        </li>
                        <li>
                            <a href="{{ $isHome ? '#stacks' : route('home') . '#stacks' }}" class="hover:text-electric transition-colors block">
                                1-Click App Stacks
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('docs.kelompok', 'infrastruktur') }}" class="hover:text-electric transition-colors block">
                                Spesifikasi Datacenter
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Kolom Direktori 2: Dokumentasi & Panduan (2 Kolom) -->
                <div class="lg:col-span-2 space-y-4">
                    <h3 class="font-mono text-xs font-bold uppercase tracking-widest text-ink pb-2 border-b border-hairline">
                        Dokumentasi
                    </h3>
                    <ul class="space-y-2.5 font-mono text-xs text-ink/80">
                        <li>
                            <a href="{{ route('docs') }}" class="hover:text-electric transition-colors block font-semibold text-ink">
                                Pusat Panduan (Docs)
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('docs.kelompok', 'mulai-cepat') }}" class="hover:text-electric transition-colors block">
                                Mulai Cepat &amp; SSH
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('docs.kelompok', 'control-panel') }}" class="hover:text-electric transition-colors block">
                                Coolify &amp; App Panel
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('docs.kelompok', 'keamanan') }}" class="hover:text-electric transition-colors block">
                                Firewall &amp; Keamanan
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('docs.kelompok', 'operasional') }}" class="hover:text-electric transition-colors block">
                                Siklus Hidup &amp; Billing
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('docs.kelompok', 'referensi-api') }}" class="hover:text-electric transition-colors block">
                                Referensi API CravaCloud
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Kolom Direktori 3: Portal & Layanan (2 Kolom) -->
                <div class="lg:col-span-2 space-y-4">
                    <h3 class="font-mono text-xs font-bold uppercase tracking-widest text-ink pb-2 border-b border-hairline">
                        Client Area
                    </h3>
                    <ul class="space-y-2.5 font-mono text-xs text-ink/80">
                        @auth
                            <li>
                                <a href="{{ auth()->user()->is_admin ? route('admin.index') : route('dashboard.index') }}" class="hover:text-electric transition-colors block font-semibold text-electric">
                                    {{ auth()->user()->is_admin ? 'Admin Panel' : 'Client Console' }} ↗
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('dashboard.billing') }}" class="hover:text-electric transition-colors block">
                                    Faktur &amp; Tagihan
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('organizations.index') }}" class="hover:text-electric transition-colors block">
                                    Kelola Tim &amp; Org
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('security.settings') }}" class="hover:text-electric transition-colors block">
                                    Keamanan &amp; 2FA
                                </a>
                            </li>
                        @else
                            <li>
                                <a href="{{ route('login') }}" class="hover:text-electric transition-colors block font-semibold text-ink">
                                    Masuk Client Console
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('register') }}" class="hover:text-electric transition-colors block">
                                    Daftar Akun Baru
                                </a>
                            </li>
                        @endauth
                        <li>
                            <a href="{{ route('status') }}" class="hover:text-electric transition-colors block">
                                Status Sistem Layanan
                            </a>
                        </li>
                        <li>
                            <a href="{{ auth()->check() ? route('dashboard.support') : $footerWaTautan }}"
                               @unless(auth()->check()) target="_blank" rel="noopener" @endunless
                               class="hover:text-electric transition-colors block">
                                Tiket Dukungan Teknis
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Kolom Direktori 4: Legal & Kebijakan (2 Kolom) -->
                <div class="lg:col-span-2 space-y-4">
                    <h3 class="font-mono text-xs font-bold uppercase tracking-widest text-ink pb-2 border-b border-hairline">
                        Legal &amp; Tata Kelola
                    </h3>
                    <ul class="space-y-2.5 font-mono text-xs text-ink/80">
                        <li>
                            <a href="{{ route('terms') }}" class="hover:text-electric transition-colors block">
                                Syarat &amp; Ketentuan
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('privacy') }}" class="hover:text-electric transition-colors block">
                                Kebijakan Privasi
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('refund') }}" class="hover:text-electric transition-colors block">
                                Kebijakan Refund
                            </a>
                        </li>
                        <li>
                            <a href="mailto:{{ $footerSupportEmail }}?subject={{ rawurlencode('Pelaporan Abuse / Pelanggaran CravaCloud') }}" class="hover:text-electric transition-colors block">
                                Pelaporan Abuse
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('sitemap') }}" class="hover:text-electric transition-colors block">
                                XML Sitemap
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 3. Payment & Security Capabilities Strip -->
        <div class="border-t border-b border-hairline bg-creamDark/40">
            <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 py-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 font-mono text-xs text-ink">
                    <!-- Sisi Kiri: Metode Pembayaran (Logo SVG Resmi) -->
                    <div class="space-y-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-muted block">
                            METODE PEMBAYARAN TERSEDIA
                        </span>
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- QRIS -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="QRIS Instan">
                                <img src="{{ asset('images/payments/qris.svg') }}" alt="QRIS" class="h-4 w-auto object-contain">
                            </div>
                            <!-- BCA -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="BCA Virtual Account">
                                <img src="{{ asset('images/payments/bca.svg') }}" alt="BCA" class="h-3.5 w-auto object-contain">
                            </div>
                            <!-- Mandiri -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="Mandiri Virtual Account">
                                <img src="{{ asset('images/payments/mandiri.svg') }}" alt="Mandiri" class="h-3.5 w-auto object-contain">
                            </div>
                            <!-- BRI -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="BRI Virtual Account">
                                <img src="{{ asset('images/payments/bri.svg') }}" alt="BRI" class="h-4 w-auto object-contain">
                            </div>
                            <!-- BNI -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="BNI Virtual Account">
                                <img src="{{ asset('images/payments/bni.svg') }}" alt="BNI" class="h-3.5 w-auto object-contain">
                            </div>
                            <!-- BSI -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="BSI Virtual Account">
                                <img src="{{ asset('images/payments/bsi.svg') }}" alt="BSI" class="h-3.5 w-auto object-contain">
                            </div>
                            <!-- GoPay -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="GoPay">
                                <img src="{{ asset('images/payments/gopay.svg') }}" alt="GoPay" class="h-3.5 w-auto object-contain">
                            </div>
                            <!-- ShopeePay -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="ShopeePay">
                                <img src="{{ asset('images/payments/shopeepay.svg') }}" alt="ShopeePay" class="h-4 w-auto object-contain">
                            </div>
                            <!-- Dana -->
                            <div class="h-8 px-2.5 bg-white border border-hairline flex items-center justify-center" title="DANA">
                                <img src="{{ asset('images/payments/dana.svg') }}" alt="DANA" class="h-3.5 w-auto object-contain">
                            </div>
                            <!-- Visa -->
                            <div class="h-8 px-2 bg-white border border-hairline flex items-center justify-center" title="Visa">
                                <img src="{{ asset('images/payments/visa.svg') }}" alt="Visa" class="h-3.5 w-auto object-contain">
                            </div>
                            <!-- Mastercard -->
                            <div class="h-8 px-2 bg-white border border-hairline flex items-center justify-center" title="Mastercard">
                                <img src="{{ asset('images/payments/mastercard.svg') }}" alt="Mastercard" class="h-4 w-auto object-contain">
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Kapabilitas Arsitektur & Keamanan Platform -->
                    <div class="space-y-2 lg:text-right">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-muted block">
                            STANDAR KEAMANAN &amp; ARSITEKTUR PLATFORM
                        </span>
                        <div class="flex flex-wrap items-center lg:justify-end gap-2">
                            <span class="px-2.5 py-1 bg-white border border-hairline font-semibold text-[11px] text-ink">2FA TOTP</span>
                            <span class="px-2.5 py-1 bg-white border border-hairline font-semibold text-[11px] text-ink">Multi-User RBAC</span>
                            <span class="px-2.5 py-1 bg-white border border-hairline font-semibold text-[11px] text-ink">SSH Vault</span>
                            <span class="px-2.5 py-1 bg-white border border-hairline font-semibold text-[11px] text-ink">Dedicated IPv4</span>
                            <span class="px-2.5 py-1 bg-white border border-hairline font-semibold text-[11px] text-ink">Zero-Touch Engine</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Bottom Utility & Copyright Bar -->
        <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 py-8">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 text-xs font-mono text-muted">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span>© {{ date('Y') }} CravaCloud Indonesia.</span>
                    <span class="text-hairline">•</span>
                    <span>Product By</span>
                    <a href="https://flustra.id"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-1 border border-hairline bg-white hover:border-electric hover:text-electric text-ink font-semibold px-2.5 py-1 transition-colors rounded-none">
                        <span>flustra.id</span>
                        <span aria-hidden="true">↗</span>
                    </a>
                </div>

                <div class="flex flex-wrap items-center gap-4 text-xs">
                    <a href="{{ $footerWaTautan }}" target="_blank" rel="noopener" class="text-ink hover:text-electric transition-colors font-semibold">
                        CS: {{ \App\Support\NomorWhatsApp::tampil() }} ↗
                    </a>
                    @if(!empty($footerIg))
                        <a href="{{ $footerIg }}" target="_blank" rel="noopener noreferrer" class="text-ink hover:text-electric transition-colors">Instagram ↗</a>
                    @endif
                    @if(!empty($footerTwitter))
                        <a href="{{ $footerTwitter }}" target="_blank" rel="noopener noreferrer" class="text-ink hover:text-electric transition-colors">X (Twitter) ↗</a>
                    @endif
                    @if(!empty($footerLinkedin))
                        <a href="{{ $footerLinkedin }}" target="_blank" rel="noopener noreferrer" class="text-ink hover:text-electric transition-colors">LinkedIn ↗</a>
                    @endif
                </div>
            </div>
        </div>
    </footer>
    @endunless

    @include('partials.invoice-modal')

    @stack('scripts')
    <script>
        window.addEventListener('load', function() {
            if (window.location.hash) {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    setTimeout(function() {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }, 100);
                }
            }
        });
    </script>
</body>
</html>
