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

    <!-- Minimalist Editorial Footer -->
    @unless(request()->routeIs('checkout'))
    <footer class="border-t border-hairline bg-cream mt-12">
        <div class="max-w-[1380px] mx-auto px-5 sm:px-10 lg:px-14 py-12">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div>
                    <a href="{{ route('home') }}" class="inline-flex items-center space-x-2.5 group">
                        <img src="{{ asset('images/logo.svg') }}"
                             onerror="this.src='{{ asset('images/logo.png') }}'"
                             alt="CravaCloud"
                             class="h-7 w-7 object-contain">
                        <span class="font-display font-extrabold text-xl tracking-tighter text-ink uppercase">
                            CRAVACLOUD
                        </span>
                    </a>
                    <p class="text-xs text-muted mt-2 font-mono">
                        Cravacloud (cravacloud.id) • Next-Gen Automated Cloud VPS, 1-Click PaaS, Database &amp; AI Agent Infrastructure.
                    </p>
                </div>

                <div class="flex flex-wrap gap-6 text-xs font-mono text-ink">
                    <a href="{{ $isHome ? '#pricing-starter' : route('home') . '#pricing-starter' }}" class="hover:text-electric transition-colors">Starter Series</a>
                    <a href="{{ $isHome ? '#pricing-enterprise' : route('home') . '#pricing-enterprise' }}" class="hover:text-electric transition-colors">Enterprise Series</a>
                    <a href="{{ $isHome ? '#stacks' : route('home') . '#stacks' }}" class="hover:text-electric transition-colors">1-Click App Stacks</a>
                    <a href="{{ \App\Support\NomorWhatsApp::tautan() }}?text={{ rawurlencode('Halo Tim CravaCloud, saya ingin konsultasi infrastruktur Cloud VPS.') }}"
                       target="_blank"
                       rel="noopener"
                       class="text-electric font-bold hover:underline">
                        Hubungi Tim CravaCloud ↗
                    </a>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-2 text-[11px] font-mono text-muted">
                <span>© {{ date('Y') }} CravaCloud Indonesia. Product By</span>
                <a href="https://flustra.id"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center gap-1 border border-hairline bg-white hover:border-electric hover:text-electric text-ink font-semibold px-2.5 py-1 transition-colors rounded-none">
                    <span>flustra.id</span>
                    <span aria-hidden="true">↗</span>
                </a>
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
