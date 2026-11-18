<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>CravaCloud {{ $title ?? 'Admin' }}</title>
    @include('partials.favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased bg-cream text-ink" x-data="{ sidebarOpen: false, userDropdown: false }">
    <div class="h-screen w-full flex flex-col md:flex-row overflow-hidden bg-cream">

        <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-ink/50 md:hidden" @click="sidebarOpen = false" style="display:none;"></div>

        <!-- Sidebar (Editorial Brutalist) -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 w-full md:w-64 bg-white border-r border-hairline flex flex-col transition-transform duration-200 md:static md:inset-auto md:h-full md:shrink-0 md:translate-x-0">

            <!-- Brand Header -->
            <div class="flex h-16 shrink-0 items-center gap-3 px-5 border-b border-hairline">
                <a href="{{ route('admin.index') }}" class="flex min-w-0 items-center gap-3 group">
                    <img src="{{ asset('images/logo.svg') }}" alt="CravaCloud" class="h-8 w-auto shrink-0 object-contain">
                    <div class="flex min-w-0 flex-col">
                        <span class="truncate font-display text-sm font-bold tracking-tight text-ink uppercase leading-tight group-hover:text-electric transition-colors">CravaCloud</span>
                        <span class="truncate font-mono text-[10px] font-semibold uppercase tracking-widest text-muted leading-tight">Admin Console</span>
                    </div>
                </a>
                <button @click="sidebarOpen = false" class="ml-auto md:hidden p-1.5 text-muted hover:text-ink border border-hairline hover:border-ink transition-colors" aria-label="Tutup Menu">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <a href="{{ route('admin.index') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.index') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span>Ringkasan</span>
                </a>

                <a href="{{ route('admin.billing.index') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.billing*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                        <span>Billing Center</span>
                    </div>
                    @php $pendingRefundCount = $adminPendingRefunds ?? 0; @endphp
                    @if($pendingRefundCount > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.billing*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $pendingRefundCount }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.gateways.index') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.gateways*') || request()->routeIs('admin.webhooks*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <span>Payment Gateway</span>
                    </div>
                    @php $failedWebhookCount = $adminFailedWebhooks ?? 0; @endphp
                    @if($failedWebhookCount > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.gateways*') || request()->routeIs('admin.webhooks*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $failedWebhookCount }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.orders') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.orders*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Antrean Order</span>
                    </div>
                    @php $pendingCount = $adminPendingOrders ?? \App\Models\Order::where('status', 'pending')->count(); @endphp
                    @if($pendingCount > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.orders*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $pendingCount }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.fulfillment.index') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.fulfillment*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                        <span>Papan Fulfillment</span>
                    </div>
                    @php $workingOrders = $adminFulfillmentWorking ?? 0; @endphp
                    @if($workingOrders > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.fulfillment*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $workingOrders }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.supplier.index') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.supplier*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Pembelian Supplier</span>
                    </div>
                    @php $supplierUrgent = $adminSupplierUrgent ?? 0; @endphp
                    @if($supplierUrgent > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.supplier*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $supplierUrgent }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.shopee') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.shopee') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span>Proses Shopee</span>
                </a>

                <a href="{{ route('admin.instances') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.instances') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                    <span>Cloud Instances</span>
                </a>

                <a href="{{ route('admin.packages.index') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.packages*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span>Paket Produk</span>
                </a>

                <a href="{{ route('admin.monitoring') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.monitoring') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <span class="w-4 h-4 border border-current flex items-center justify-center shrink-0"><span class="w-1.5 h-1.5 bg-current"></span></span>
                    <span>Monitoring</span>
                </a>

                <a href="{{ route('admin.reports') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.reports*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <span class="w-4 h-4 flex items-end gap-0.5 shrink-0"><span class="w-1 h-2 bg-current"></span><span class="w-1 h-3 bg-current"></span><span class="w-1 h-4 bg-current"></span></span>
                    <span>Reports</span>
                </a>

                <a href="{{ route('admin.customers') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.customers') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <span>Pelanggan</span>
                </a>

                <a href="{{ route('admin.tickets') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.tickets*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                        <span>Tiket Bantuan</span>
                    </div>
                    @php $openTicketCount = $adminOpenTickets ?? \App\Models\SupportTicket::whereIn('status', ['open', 'in_progress'])->count(); @endphp
                    @if($openTicketCount > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.tickets*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $openTicketCount }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.abuse.index') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.abuse*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 004.99 19z"/></svg>
                        <span>Kasus Pelanggaran</span>
                    </div>
                    @php $openAbuseCount = $adminOpenAbuse ?? 0; @endphp
                    @if($openAbuseCount > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.abuse*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $openAbuseCount }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.broadcast.index') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.broadcast*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                    <span>Broadcast</span>
                </a>

                <a href="{{ route('admin.templates.index') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.templates*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>Template Surel</span>
                </a>

                <a href="{{ route('admin.whatsapp.index') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.whatsapp*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <span>WhatsApp Gateway</span>
                </a>

                <a href="{{ route('admin.maintenance.index') }}"
                   class="flex items-center justify-between px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.maintenance*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>Jadwal Maintenance</span>
                    </div>
                    @php $runningWindows = $adminRunningMaintenance ?? 0; @endphp
                    @if($runningWindows > 0)
                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 leading-none {{ request()->routeIs('admin.maintenance*') ? 'bg-white text-electric' : 'bg-ink text-cream' }}">{{ $runningWindows }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.audit.index') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.audit*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Audit Log</span>
                </a>

                <a href="{{ route('admin.settings.index') }}"
                   class="flex items-center gap-3 px-3 py-2 text-xs transition-colors {{ request()->routeIs('admin.settings*') ? 'bg-electric text-white font-bold' : 'text-ink hover:bg-creamDark/70 font-medium' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                    <span>Pengaturan Sistem</span>
                </a>
            </nav>

            <!-- Sidebar Footer -->
            <div class="shrink-0 border-t border-hairline p-3 bg-white">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2 text-xs font-mono uppercase tracking-wider font-semibold text-ink hover:bg-ink hover:text-white transition-colors cursor-pointer">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>Keluar Akun</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0 h-full overflow-y-auto bg-cream">
            <!-- Top Header Bar -->
            <header class="h-16 shrink-0 bg-white/95 backdrop-blur-sm border-b border-hairline flex items-center justify-between px-5 sm:px-8 sticky top-0 z-30">
                <div class="flex items-center gap-3 min-w-0">
                    <button @click="sidebarOpen = true" class="md:hidden p-1.5 border border-hairline hover:border-ink text-ink -ml-1" aria-label="Buka Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    @if(isset($backUrl))
                        <a href="{{ $backUrl }}" class="p-1.5 border border-hairline bg-white hover:border-ink hover:text-electric text-muted transition-colors flex items-center justify-center shrink-0" title="{{ $backLabel ?? 'Kembali' }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </a>
                    @endif
                    <h1 class="font-display text-base sm:text-lg font-bold text-ink tracking-tight truncate">{{ $headerTitle ?? 'Admin Console' }}</h1>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <a href="{{ route('admin.orders') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase tracking-wider transition-colors">
                        <span>Antrean Order</span>
                    </a>

                    <div class="relative" @click.away="userDropdown = false">
                        <button @click="userDropdown = !userDropdown" class="flex items-center gap-2 px-2.5 py-1.5 border border-hairline bg-white hover:border-ink transition-colors cursor-pointer">
                            <span class="w-6 h-6 bg-ink text-cream font-mono text-[11px] font-bold flex items-center justify-center shrink-0">
                                {{ strtoupper(substr(auth()->user()->full_name ?? auth()->user()->username ?? 'A', 0, 1)) }}
                            </span>
                            <span class="hidden sm:inline font-mono text-xs font-medium text-ink max-w-[130px] truncate">{{ auth()->user()->full_name ?? auth()->user()->username }}</span>
                        </button>

                        <div x-show="userDropdown" x-cloak class="absolute right-0 mt-1.5 w-60 bg-white border border-ink shadow-xl py-1 z-50 text-xs" style="display:none;">
                            <div class="px-4 py-3 border-b border-hairline bg-creamDark/40">
                                <p class="font-display font-bold text-ink truncate">{{ auth()->user()->full_name ?? auth()->user()->username }} (Admin)</p>
                                <p class="font-mono text-[11px] text-muted truncate mt-0.5">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('dashboard.index') }}" class="block px-4 py-2.5 text-ink hover:bg-creamDark/60 font-medium">Client Portal</a>
                            <a href="{{ route('home') }}" class="block px-4 py-2.5 text-ink hover:bg-creamDark/60 font-medium">Halaman Utama</a>
                            <div class="border-t border-hairline my-1"></div>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2.5 font-mono uppercase tracking-wider font-semibold text-ink hover:bg-ink hover:text-white transition-colors cursor-pointer">Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Alerts (Monochrome Editorial Brutalist) -->
            <div class="px-5 sm:px-8 pt-4">
                @if(session('success'))
                    <div x-data="{ show: true }"
                         x-show="show"
                         x-init="setTimeout(() => show = false, 4000)"
                         x-transition:leave="transition ease-in duration-200"
                         class="border border-electric bg-white p-3.5 text-xs text-ink mb-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2.5">
                            <span class="px-1.5 py-0.5 bg-electric text-white font-mono text-[10px] font-bold uppercase">OK</span>
                            <span class="font-medium">{{ session('success') }}</span>
                        </div>
                        <button @click="show = false" class="text-muted hover:text-ink px-1.5 font-mono text-sm leading-none cursor-pointer">&times;</button>
                    </div>
                @endif
                @if(session('error'))
                    <div x-data="{ show: true }"
                         x-show="show"
                         x-init="setTimeout(() => show = false, 5000)"
                         class="border-2 border-ink bg-creamDark p-3.5 text-xs text-ink mb-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2.5">
                            <span class="px-1.5 py-0.5 bg-ink text-cream font-mono text-[10px] font-bold uppercase">ERR</span>
                            <span class="font-semibold">{{ session('error') }}</span>
                        </div>
                        <button @click="show = false" class="text-muted hover:text-ink px-1.5 font-mono text-sm leading-none cursor-pointer">&times;</button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="border-2 border-ink bg-white p-4 text-xs text-ink mb-4">
                        <div class="font-mono text-[10px] font-bold uppercase tracking-widest mb-1.5">Terjadi Kesalahan Input</div>
                        <ul class="list-disc pl-5 space-y-1 text-ink">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <!-- Main Page Body -->
            <main class="flex-1 px-5 sm:px-8 pb-12 pt-2">
                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="py-4 px-5 sm:px-8 border-t border-hairline bg-white/60 text-[11px] font-mono text-muted shrink-0">
                &copy; 2026 CRAVACLOUD — ADMIN CONSOLE
            </footer>
        </div>
    </div>
    @include('partials.invoice-modal')
    @stack('scripts')
</body>
</html>