@extends('layouts.dashboard', ['title' => 'Detail VPS ' . ($vps->hostname ?? $vps->id), 'headerTitle' => 'Kelola Instance VPS'])

@section('content')
<div class="space-y-6" x-data="{
    activeTab: '{{ $vps->isAiPackage() ? 'webapp' : ($vps->isDatabasePackage() ? 'database' : 'overview') }}',
    renewalModal: false,
    reinstallModal: {{ $errors->hasAny(['os', 'control_panel', 'confirm_hostname', 'notes']) ? 'true' : 'false' }},
    rebootGuideModal: false,
    unreachableModal: {{ $errors->has('description') ? 'true' : 'false' }},
    passwordLoading: false,
    passwordError: '',
    revealedPassword: '',
    showPassword: false,
    revealedAt: '{{ $vps->root_password_revealed_at ? $vps->root_password_revealed_at->timezone('Asia/Jakarta')->format('d M Y H:i:s') : '' }}',
    showDbPassword: false,
    copyText(text) {
        if (!text) return;
        navigator.clipboard.writeText(text);
        showAlert('Teks disalin ke clipboard: ' + text, { icon: 'success', title: 'Berhasil disalin' });
    },
    copySecret(text) {
        if (!text) return;
        navigator.clipboard.writeText(text);
        showAlert('Password disalin ke clipboard.', { icon: 'success', title: 'Berhasil disalin' });
    },
    async togglePassword() {
        if (this.showPassword) {
            this.showPassword = false;
            return;
        }
        if (this.revealedPassword) {
            this.showPassword = true;
            return;
        }
        if (this.passwordLoading) return;
        this.passwordLoading = true;
        this.passwordError = '';
        try {
            const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content');
            const res = await fetch('{{ route('dashboard.vps.reveal-password', $vps->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({})
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success && data.password) {
                this.revealedPassword = data.password;
                this.revealedAt = data.revealed_at || this.revealedAt;
                this.showPassword = true;
            } else {
                this.passwordError = data.message || 'Password belum tersedia. Silakan hubungi support.';
            }
        } catch (err) {
            this.passwordError = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        } finally {
            this.passwordLoading = false;
        }
    },
    async revealFromGuide() {
        this.activeTab = 'access';
        if (!this.showPassword) await this.togglePassword();
    }
}">
    <!-- Back Link -->
    <div>
        <a href="{{ route('dashboard.index') }}" class="font-mono text-xs uppercase tracking-wider text-muted hover:text-electric transition-colors inline-flex items-center gap-1.5">
            <span>←</span>
            <span>Kembali ke VPS Instances</span>
        </a>
    </div>

    <!-- Lifecycle & Status Warning Alerts -->
    @if($vps->status === 'suspended')
        <div class="p-4 bg-white border-2 border-amber-500 text-ink flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs">
                <strong class="font-display font-bold text-sm block text-amber-950">Instance VPS Ditangguhkan (Suspended)</strong>
                Layanan VPS ditangguhkan karena melewati jatuh tempo pembayaran. Perpanjang sekarang untuk mengaktifkan kembali server.
            </div>
            @if($vps->canBeRenewed())
                <button type="button" @click="renewalModal = true" class="rounded-none px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase whitespace-nowrap self-start sm:self-auto cursor-pointer">
                    Perpanjang &amp; Aktifkan Kembali
                </button>
            @endif
        </div>
    @elseif($vps->isInGracePeriod())
        <div class="p-4 bg-white border-2 border-amber-500 text-ink flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs">
                <strong class="font-display font-bold text-sm block text-amber-950">Masa Tenggang Berlangganan (Grace Period)</strong>
                Masa aktif VPS telah jatuh tempo. Anda memiliki masa tenggang hingga <strong>{{ $vps->grace_period_ends_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</strong> sebelum layanan dihentikan otomatis.
            </div>
            @if($vps->canBeRenewed())
                <button type="button" @click="renewalModal = true" class="rounded-none px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase whitespace-nowrap self-start sm:self-auto cursor-pointer">
                    Perpanjang Layanan Sekarang
                </button>
            @endif
        </div>
    @elseif($vps->isExpired())
        <div class="p-4 bg-white border-2 border-red-600 text-ink flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs">
                <strong class="font-display font-bold text-sm block text-red-900">Masa Aktif Berakhir (Expired)</strong>
                Masa aktif server telah kedaluwarsa pada {{ $vps->expires_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}.
            </div>
            @if($vps->canBeRenewed())
                <button type="button" @click="renewalModal = true" class="rounded-none px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase whitespace-nowrap self-start sm:self-auto cursor-pointer">
                    Perpanjang Layanan
                </button>
            @endif
        </div>
    @endif

    @if($vps->isEolWithReplacement())
        <div class="p-4 bg-white border border-hairline border-l-4 border-l-ink text-xs leading-relaxed">
            <strong class="font-display font-bold text-ink text-sm block mb-0.5">Penyesuaian Skema Paket (End-of-Life)</strong>
            Paket lama tidak lagi diproduksi oleh penyedia infrastruktur (End-of-Life). Perpanjangan masa aktif otomatis disesuaikan ke paket <strong>{{ $vps->eol_replacement_spec?->name }}</strong> (Rp {{ number_format($vps->renewal_price, 0, ',', '.') }}/bulan) tanpa mengubah data di dalam server Anda.
        </div>
    @elseif($vps->isEolWithoutReplacement())
        <div class="p-4 bg-white border-2 border-ink flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="text-xs leading-relaxed">
                <strong class="font-display font-bold text-ink text-sm block mb-0.5">Paket Dihentikan Permanen (End-of-Life)</strong>
                Paket lama telah dihentikan permanen oleh penyedia infrastruktur. Hubungi tim dukungan untuk bantuan migrasi server ke paket aktif.
            </div>
            <a href="{{ route('dashboard.support') }}" class="rounded-none px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase whitespace-nowrap self-start sm:self-auto">
                Hubungi Tim Dukungan
            </a>
        </div>
    @endif

    <!-- Open Service Request Banners -->
    @foreach($openRequests as $openRequest)
        <div class="p-4 bg-white border-2 border-electric flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-electric shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div class="text-xs">
                    <strong class="font-display font-bold text-sm text-ink block">
                        {{ $openRequest->type_label }} sedang {{ $openRequest->status === 'in_progress' ? 'dikerjakan' : 'menunggu diproses' }} tim
                    </strong>
                    Diajukan {{ $openRequest->created_at->locale('id')->diffForHumans() }}. Estimasi selesai maksimal {{ $slaHours }} jam kerja ({{ $supportHours }}). Anda akan menerima email saat selesai.
                </div>
            </div>
            <a href="{{ route('dashboard.support.show', $openRequest->id) }}" class="rounded-none px-4 py-2 bg-creamDark border border-ink hover:bg-electric hover:text-white hover:border-electric text-ink font-mono text-xs font-bold uppercase whitespace-nowrap self-start sm:self-auto transition-colors">
                Lihat Tiket #TK-{{ str_pad($openRequest->id, 4, '0', STR_PAD_LEFT) }}
            </a>
        </div>
    @endforeach

    <!-- Server Top Header Card -->
    <div class="bg-white border border-ink p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-creamDark border border-hairline text-ink flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="font-display text-xl font-bold text-ink">{{ $vps->hostname ?? 'VPS-' . $vps->id }}</h1>
                    @if($vps->isDatabasePackage())
                        <span class="px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-emerald-100 text-emerald-900 border border-emerald-400">Managed DB</span>
                    @elseif($vps->isAiPackage())
                        <span class="px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-electric text-white">AI Combo</span>
                    @endif
                    @php $customerStatus = $vps->customer_status; @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 font-mono text-[10px] font-bold uppercase border {{ $customerStatus['class'] }}" title="Status layanan">
                        <span class="w-1.5 h-1.5 rounded-full {{ $customerStatus['dot'] }}"></span>
                        {{ $customerStatus['label'] }}
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-y-1 gap-x-3 text-xs text-muted mt-2">
                    <span>IP: <strong class="text-ink font-mono">{{ $vps->public_ip ?? 'Belum dialokasikan' }}</strong></span>
                    <span>·</span>
                    <span>OS: <strong class="text-ink">{{ $vps->os_label }}</strong></span>
                    <span>·</span>
                    <span>Provider: <strong class="text-ink">{{ $vps->provider_label }}</strong></span>
                    <span>·</span>
                    <span>Stack: <strong class="text-ink">{{ $vps->control_panel_label }}</strong></span>
                    <span>·</span>
                    <span>Siklus: <strong class="text-ink font-mono uppercase">{{ str_replace('_', ' ', $vps->billing_cycle ?? 'monthly') }}</strong></span>
                </div>
            </div>
        </div>

        <!-- Action Toolbar (Honest Actions Only) -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @if($vps->canBeRenewed())
                <button type="button" @click="renewalModal = true" class="rounded-none px-4 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-1.5 cursor-pointer">
                    <span>Perpanjang Layanan</span>
                </button>
            @elseif($vps->isEolWithoutReplacement())
                <a href="{{ route('dashboard.support') }}" class="rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark font-mono text-xs font-bold uppercase text-ink transition-colors">
                    Hubungi Tim Migrasi
                </a>
            @endif

            @if($vps->public_ip && $vps->status === 'running')
                @if($vps->isAiPackage() && $vps->app_url)
                    <a href="{{ $vps->app_url }}" target="_blank" rel="noopener" class="rounded-none px-4 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5">
                        <span>Buka Web App</span>
                        <span>↗</span>
                    </a>
                @elseif($vps->isDatabasePackage() && $vps->web_manager_url_resolved)
                    <a href="{{ $vps->web_manager_url_resolved }}" target="_blank" rel="noopener" class="rounded-none px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5">
                        <span>Buka Web Manager</span>
                        <span>↗</span>
                    </a>
                @elseif(!$vps->isDatabasePackage() && $vps->panel_url)
                    <a href="{{ $vps->panel_url }}" target="_blank" rel="noopener" class="rounded-none px-4 py-2.5 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors inline-flex items-center gap-1.5">
                        <span>Panel Web</span>
                        <span>↗</span>
                    </a>
                @endif
            @endif

            @if(in_array($vps->status, ['running', 'error'], true))
                <button type="button" @click="rebootGuideModal = true" class="rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark font-mono text-xs font-bold uppercase text-ink transition-colors cursor-pointer">
                    Cara Reboot
                </button>
            @endif

            @if(isset($openRequests['reinstall']))
                <a href="{{ route('dashboard.support.show', $openRequests['reinstall']->id) }}" class="rounded-none px-4 py-2.5 border border-electric bg-creamDark font-mono text-xs font-bold uppercase text-electric transition-colors">
                    Reinstall Sedang Diproses
                </a>
            @elseif($vps->acceptsServiceRequests())
                <button type="button" @click="reinstallModal = true" class="rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark font-mono text-xs font-bold uppercase text-ink transition-colors cursor-pointer">
                    Ajukan Reinstall OS
                </button>
            @endif
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex flex-wrap gap-px bg-hairline border border-hairline font-mono text-xs font-bold uppercase">
        @if($vps->isDatabasePackage())
            <button type="button" @click="activeTab = 'database'"
                    :class="activeTab === 'database' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors flex items-center gap-2 cursor-pointer">
                <span>Koneksi Database (.env)</span>
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            </button>
            <button type="button" @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors cursor-pointer">
                Ringkasan Server
            </button>
            <button type="button" @click="activeTab = 'access'"
                    :class="activeTab === 'access' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors cursor-pointer">
                Akses SSH Server
            </button>
        @elseif($vps->isAiPackage())
            <button type="button" @click="activeTab = 'webapp'"
                    :class="activeTab === 'webapp' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors flex items-center gap-2 cursor-pointer">
                <span>Akses Web App</span>
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            </button>
            <button type="button" @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors cursor-pointer">
                Ringkasan Server
            </button>
            <button type="button" @click="activeTab = 'access'"
                    :class="activeTab === 'access' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors cursor-pointer">
                Akses SSH (Lanjutan)
            </button>
        @else
            <button type="button" @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors cursor-pointer">
                Ringkasan Server
            </button>
            <button type="button" @click="activeTab = 'access'"
                    :class="activeTab === 'access' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors cursor-pointer">
                Detail Akses &amp; SSH
            </button>
        @endif

        <button type="button" @click="activeTab = 'subscription'"
                :class="activeTab === 'subscription' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                class="py-3 px-5 transition-colors cursor-pointer">
            Berlangganan &amp; Siklus
        </button>

        @if(!$vps->isAiPackage() && !$vps->isDatabasePackage())
            <button type="button" @click="activeTab = 'panel'"
                    :class="activeTab === 'panel' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                    class="py-3 px-5 transition-colors cursor-pointer">
                Control Panel
            </button>
        @endif

        <button type="button" @click="activeTab = 'requests'"
                :class="activeTab === 'requests' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                class="py-3 px-5 transition-colors flex items-center gap-2 cursor-pointer">
            <span>Permintaan &amp; Bantuan</span>
            @if($openRequests->isNotEmpty())
                <span class="px-1.5 py-0.5 text-[10px] leading-none" :class="activeTab === 'requests' ? 'bg-white text-electric' : 'bg-electric text-white'">
                    {{ $openRequests->count() }}
                </span>
            @endif
        </button>

        <button type="button" @click="activeTab = 'logs'"
                :class="activeTab === 'logs' ? 'bg-electric text-white' : 'bg-white text-ink hover:bg-creamDark/60'"
                class="py-3 px-5 transition-colors flex items-center gap-2 cursor-pointer">
            <span>Audit Trail &amp; Riwayat</span>
            <span class="px-1.5 py-0.5 text-[10px] leading-none" :class="activeTab === 'logs' ? 'bg-white text-electric' : 'bg-creamDark text-ink border border-hairline'">
                {{ $vps->activityLogs->count() }}
            </span>
        </button>
    </div>

    @if($vps->isDatabasePackage())
    @php
        $dbHost = $vps->public_ip ?? '103.xxx.xxx.xxx';
        $dbPort = $vps->db_port_resolved;
        $dbConn = $vps->db_connection_driver;
        $dbName = $vps->db_name ?? 'cravadb_production';
        $dbUser = $vps->db_user ?? 'admin_crava';
        $dbPass = $vps->db_password ?: ($vps->initial_root_password ?: 'cravapass123');
        $dbUrl = $vps->database_url;
        $webMgrUrl = $vps->web_manager_url_resolved;
        $dbEngineLabel = match($vps->db_engine) {
            'mysql' => 'MySQL 8.0 / MariaDB 11',
            'redis' => 'Redis 7 In-Memory Cache',
            'mongodb' => 'MongoDB 7 Community',
            'vector' => 'Qdrant / pgvector (AI & RAG)',
            default => 'PostgreSQL 16',
        };
        $fullEnvConfig = "DB_CONNECTION={$dbConn}\n"
                       . "DB_HOST={$dbHost}\n"
                       . "DB_PORT={$dbPort}\n"
                       . "DB_DATABASE={$dbName}\n"
                       . "DB_USERNAME={$dbUser}\n"
                       . "DB_PASSWORD={$dbPass}\n"
                       . "DATABASE_URL={$dbUrl}";
        if ($webMgrUrl) {
            $fullEnvConfig .= "\nWEB_MANAGER_URL={$webMgrUrl} (CloudBeaver)";
        }
    @endphp
    <!-- Tab Database -->
    <div x-show="activeTab === 'database'" class="space-y-6">
        <div class="bg-white border border-hairline p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-hairline">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-display text-base font-bold text-ink">Format Connection String Database</h3>
                        <span class="px-2 py-0.5 font-mono text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-400">
                            {{ $dbEngineLabel }}
                        </span>
                    </div>
                    <p class="text-xs text-muted mt-1">Salin konfigurasi siap pakai ke file <code class="bg-creamDark px-1.5 py-0.5 font-mono text-ink">.env</code> aplikasi backend Anda.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($webMgrUrl && $vps->status === 'running')
                        <a href="{{ $webMgrUrl }}" target="_blank" rel="noopener" class="rounded-none px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white font-mono text-xs font-bold uppercase transition-colors">
                            Buka Web GUI ↗
                        </a>
                    @endif
                    <button type="button" @click="copyText(@js($fullEnvConfig))" class="rounded-none px-4 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase transition-colors cursor-pointer">
                        Salin Format .env
                    </button>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-xs font-bold text-ink uppercase">Konfigurasi .env Aplikasi</span>
                    <button type="button" @click="showDbPassword = !showDbPassword" class="font-mono text-xs font-bold text-electric hover:underline cursor-pointer">
                        <span x-text="showDbPassword ? 'Sembunyikan Password' : 'Lihat Password'"></span>
                    </button>
                </div>

                <div class="relative">
                    <pre class="bg-ink text-cream p-5 font-mono text-xs sm:text-sm overflow-x-auto leading-relaxed border border-ink select-all"><code>DB_CONNECTION={{ $dbConn }}
DB_HOST={{ $dbHost }}
DB_PORT={{ $dbPort }}
DB_DATABASE={{ $dbName }}
DB_USERNAME={{ $dbUser }}
DB_PASSWORD=<span x-text="showDbPassword ? '{{ $dbPass }}' : '****************'">****************</span>
DATABASE_URL={{ $dbUrl }}
@if($webMgrUrl)
WEB_MANAGER_URL={{ $webMgrUrl }} (CloudBeaver)
@endif</code></pre>
                    <button type="button" @click="copyText(@js($fullEnvConfig))" class="absolute top-3 right-3 px-3 py-1.5 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase transition-colors cursor-pointer">
                        Salin .env
                    </button>
                </div>
            </div>

            <!-- Parameters Grid -->
            <div class="space-y-3 pt-2">
                <h4 class="font-mono text-[11px] font-bold uppercase tracking-wider text-muted">Rincian Parameter Koneksi</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-px bg-hairline border border-hairline">
                    @foreach([
                        ['DB_CONNECTION', $dbConn],
                        ['DB_HOST', $dbHost],
                        ['DB_PORT', $dbPort],
                        ['DB_DATABASE', $dbName],
                        ['DB_USERNAME', $dbUser],
                    ] as [$k, $v])
                    <div class="p-3.5 bg-white flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="font-mono text-[10px] text-muted font-bold uppercase block">{{ $k }}</span>
                            <span class="font-mono font-bold text-ink text-xs truncate block">{{ $v }}</span>
                        </div>
                        <button type="button" @click="copyText('{{ $v }}')" class="p-1.5 border border-hairline hover:border-ink text-muted hover:text-ink cursor-pointer" title="Salin">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>
                    @endforeach

                    <div class="p-3.5 bg-white flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="font-mono text-[10px] text-muted font-bold uppercase block">DB_PASSWORD</span>
                            <span class="font-mono font-bold text-ink text-xs" x-text="showDbPassword ? '{{ $dbPass }}' : '••••••••••••••••'"></span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="showDbPassword = !showDbPassword" class="p-1.5 border border-hairline hover:border-ink text-muted hover:text-ink cursor-pointer" title="Lihat/Sembunyikan">
                                <svg x-show="!showDbPassword" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showDbPassword" x-cloak class="w-3.5 h-3.5 text-electric" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                            </button>
                            <button type="button" @click="copyText('{{ $dbPass }}')" class="p-1.5 border border-hairline hover:border-ink text-muted hover:text-ink cursor-pointer" title="Salin Password">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="sm:col-span-2 p-3.5 bg-white flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="font-mono text-[10px] text-muted font-bold uppercase block">DATABASE_URL</span>
                            <span class="font-mono font-bold text-ink text-xs truncate block">{{ $dbUrl }}</span>
                        </div>
                        <button type="button" @click="copyText('{{ $dbUrl }}')" class="p-1.5 border border-hairline hover:border-ink text-muted hover:text-ink shrink-0 cursor-pointer" title="Salin URL">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>

                    <div class="p-3.5 bg-white flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="font-mono text-[10px] text-muted font-bold uppercase block">WEB_MANAGER_URL</span>
                            @if($webMgrUrl)
                                <span class="font-mono font-bold text-emerald-700 text-xs truncate block">{{ $webMgrUrl }}</span>
                            @else
                                <span class="text-muted text-xs font-mono">CLI Only (Disabled)</span>
                            @endif
                        </div>
                        @if($webMgrUrl)
                            <button type="button" @click="copyText('{{ $webMgrUrl }}')" class="p-1.5 border border-hairline hover:border-ink text-muted hover:text-ink shrink-0 cursor-pointer" title="Salin">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Remote DB Guide -->
            <div class="p-4 bg-creamDark/60 border border-hairline space-y-3">
                <h4 class="font-mono font-bold text-ink text-xs uppercase tracking-wider">Panduan Remote Database Client</h4>
                <p class="text-xs text-muted leading-relaxed">
                    Hubungkan aplikasi backend (Vercel, Railway, VPS eksternal) atau remote client (DBeaver, TablePlus, DataGrip, psql) via Dedicated IP Publik server:
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs font-mono">
                    <div class="bg-white p-2.5 border border-hairline"><span class="text-muted block text-[10px]">PostgreSQL</span><span class="font-bold text-ink">Port 5432</span></div>
                    <div class="bg-white p-2.5 border border-hairline"><span class="text-muted block text-[10px]">MySQL / MariaDB</span><span class="font-bold text-ink">Port 3306</span></div>
                    <div class="bg-white p-2.5 border border-hairline"><span class="text-muted block text-[10px]">Redis Cache</span><span class="font-bold text-ink">Port 6379</span></div>
                    <div class="bg-white p-2.5 border border-hairline"><span class="text-muted block text-[10px]">Vector Qdrant</span><span class="font-bold text-ink">Port 6333</span></div>
                </div>
                <p class="text-[11px] text-muted">
                    Disarankan SSH Tunneling (<code class="bg-white px-1.5 py-0.5 border border-hairline text-ink font-mono">ssh -L {{ $dbPort }}:localhost:{{ $dbPort }} {{ $vps->ssh_user }}&#64;{{ $dbHost }}</code>) untuk keamanan optimal.
                </p>
            </div>
        </div>
    </div>
    @endif

    @if($vps->isAiPackage())
    <!-- Tab Web App (AI Combo) -->
    <div x-show="activeTab === 'webapp'" class="space-y-6">
        <div class="bg-white border border-hairline p-6 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-hairline">
                <div>
                    <h3 class="font-display text-base font-bold text-ink">Akses Web App {{ $vps->app_name ?? $vps->control_panel_label }}</h3>
                    <p class="text-xs text-muted mt-0.5">Tautan langsung menuju antarmuka runtime AI yang telah aktif di server Anda.</p>
                </div>
                @if($vps->app_url && $vps->status === 'running')
                    <a href="{{ $vps->app_url }}" target="_blank" rel="noopener" class="rounded-none px-4 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase transition-colors inline-flex items-center gap-2 self-start sm:self-auto">
                        <span>Buka Web App di Tab Baru</span>
                        <span>↗</span>
                    </a>
                @endif
            </div>

            <div class="p-4 bg-creamDark/40 border border-hairline space-y-2.5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <span class="font-mono text-xs font-bold uppercase text-muted">URL Akses Web</span>
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-ink text-xs bg-white px-3 py-1.5 border border-hairline select-all">
                            {{ $vps->app_url ?? 'Sedang mengalokasikan...' }}
                        </span>
                        @if($vps->app_url)
                            <button type="button" @click="copyText('{{ $vps->app_url }}')" class="px-3 py-1.5 border border-ink bg-white hover:bg-creamDark font-mono text-xs font-bold uppercase cursor-pointer">
                                Salin
                            </button>
                        @endif
                    </div>
                </div>
                <p class="text-[11px] text-muted">Akses URL di atas melalui browser modern (Chrome, Safari, Firefox, Edge).</p>
            </div>

            <div class="space-y-3 pt-2">
                <h4 class="font-mono text-[11px] font-bold uppercase tracking-wider text-muted">Panduan Penggunaan Cepat</h4>

                @if($vps->control_panel === 'claude_opencode')
                    <div class="p-4 border border-hairline bg-white space-y-2 text-xs">
                        <p class="font-display font-bold text-ink">Combo Terminal Coding Agent</p>
                        <ol class="list-decimal list-inside space-y-1.5 text-muted">
                            <li>Buka Web Terminal melalui URL di atas atau sambungkan terminal lokal via SSH.</li>
                            <li>Ketik <code class="bg-creamDark px-1.5 py-0.5 text-ink font-mono">claude</code> untuk menjalankan Claude Code CLI resmi Anthropic.</li>
                            <li>Ketik <code class="bg-creamDark px-1.5 py-0.5 text-ink font-mono">opencode</code> untuk multi-provider AI model open-source.</li>
                            <li>Masukkan API Key saat pertama diminta. Environment Docker, Git, Node.js LTS, dan Python 3.12 siap dipakai.</li>
                        </ol>
                    </div>
                @elseif($vps->control_panel === 'vscode_server')
                    <div class="p-4 border border-hairline bg-white space-y-2 text-xs">
                        <p class="font-display font-bold text-ink">Combo Cloud AI Workstation</p>
                        <ol class="list-decimal list-inside space-y-1.5 text-muted">
                            <li>Buka URL Web App di browser perangkat Anda (laptop/iPad).</li>
                            <li>Masukkan password VPS jika diminta untuk membuka sesi Web VS Code Server.</li>
                            <li>Buka terminal VS Code (<code class="bg-creamDark px-1.5 py-0.5 text-ink font-mono">Ctrl + `</code>) dan jalankan <code class="bg-creamDark px-1.5 py-0.5 text-ink font-mono">claude</code>.</li>
                        </ol>
                    </div>
                @elseif(in_array($vps->control_panel, ['hermes_omniroute', 'hermes_agent', 'omniroute', '9router']))
                    <div class="p-4 border border-hairline bg-white space-y-2 text-xs">
                        <p class="font-display font-bold text-ink">Combo Hermes Autonomous Hub</p>
                        <ol class="list-decimal list-inside space-y-1.5 text-muted">
                            <li>Buka antarmuka OmniRoute di URL di atas untuk konfigurasi routing AI providers &amp; API keys.</li>
                            <li>Hermes Agent runtime telah berjalan di background sebagai service 24/7.</li>
                            <li>Semua task dan automasi diproses melalui proxy routing terpadu.</li>
                        </ol>
                    </div>
                @else
                    <div class="p-4 border border-hairline bg-white space-y-2 text-xs">
                        <p class="font-display font-bold text-ink">Combo Enterprise Private AI &amp; RAG</p>
                        <ol class="list-decimal list-inside space-y-1.5 text-muted">
                            <li>Buka URL Web App di browser untuk mengakses dashboard studio AI.</li>
                            <li>Buat Knowledge Base baru dan unggah dokumen PDF / data privat organisasi.</li>
                            <li>Pilih local model via Ollama yang telah terintegrasi di server privat Anda.</li>
                        </ol>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Tab Overview -->
    <div x-show="activeTab === 'overview'" class="space-y-6">
        @if(!$vps->isAiPackage() && !$vps->isDatabasePackage() && $vps->status === 'running')
            <div class="bg-white border border-ink p-6">
                <h3 class="font-display text-base font-bold text-ink">Mulai di Sini</h3>
                <p class="text-xs text-muted mt-0.5 mb-5">Tiga langkah pertama setelah server Anda aktif.</p>

                <ol class="grid gap-4 md:grid-cols-3 text-xs">
                    <li class="p-4 bg-creamDark/40 border border-hairline space-y-2.5">
                        <span class="inline-flex items-center justify-center w-6 h-6 bg-ink text-cream font-mono font-bold">01</span>
                        <p class="font-display font-bold text-ink">Ambil password SSH</p>
                        <p class="text-muted">Username: <span class="font-mono font-bold text-ink">{{ $vps->ssh_user }}</span>. Klik untuk melihat password di tab Akses.</p>
                        <button type="button" @click="revealFromGuide()" class="rounded-none px-3.5 py-2 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase cursor-pointer">
                            Tampilkan Password
                        </button>
                    </li>
                    <li class="p-4 bg-creamDark/40 border border-hairline space-y-2.5">
                        <span class="inline-flex items-center justify-center w-6 h-6 bg-ink text-cream font-mono font-bold">02</span>
                        <p class="font-display font-bold text-ink">Login ke server lewat SSH</p>
                        <p class="text-muted">Jalankan dari Terminal, PowerShell, atau PuTTY.</p>
                        <div class="flex items-center justify-between gap-2 p-2.5 bg-ink text-cream font-mono text-[11px]">
                            <span class="truncate">{{ $vps->ssh_command }}</span>
                            <button type="button" @click="copyText(@js($vps->ssh_command))" class="px-2 py-0.5 bg-electric text-white text-[10px] uppercase font-bold shrink-0 cursor-pointer">Salin</button>
                        </div>
                    </li>
                    <li class="p-4 bg-creamDark/40 border border-hairline space-y-2.5">
                        <span class="inline-flex items-center justify-center w-6 h-6 bg-ink text-cream font-mono font-bold">03</span>
                        <p class="font-display font-bold text-ink">Ganti password</p>
                        <p class="text-muted">Setelah diganti, simpan password baru Anda sendiri.</p>
                        <div class="flex items-center justify-between gap-2 p-2.5 bg-ink text-cream font-mono text-[11px]">
                            <span>passwd</span>
                            <button type="button" @click="copyText('passwd')" class="px-2 py-0.5 bg-electric text-white text-[10px] uppercase font-bold shrink-0 cursor-pointer">Salin</button>
                        </div>
                    </li>
                </ol>

                @if($vps->control_panel && $vps->control_panel !== 'none')
                    <p class="text-xs text-muted mt-4">
                        Memakai {{ $vps->control_panel_label }}? Buka tab
                        <button type="button" @click="activeTab = 'panel'" class="font-bold text-electric underline cursor-pointer">Control Panel</button>
                        untuk alamat panelnya.
                    </p>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
            <div class="bg-white p-5">
                <span class="font-mono text-[10px] uppercase text-muted font-bold block mb-1">Prosesor</span>
                <p class="font-mono text-lg font-bold text-ink">{{ $vps->cpu ?? 1 }} vCPU</p>
            </div>
            <div class="bg-white p-5">
                <span class="font-mono text-[10px] uppercase text-muted font-bold block mb-1">Memori</span>
                <p class="font-mono text-lg font-bold text-ink">{{ $vps->ram ?? 1 }} GB RAM</p>
            </div>
            <div class="bg-white p-5">
                <span class="font-mono text-[10px] uppercase text-muted font-bold block mb-1">Penyimpanan</span>
                <p class="font-mono text-lg font-bold text-ink">{{ $vps->disk ?? 20 }} GB NVMe</p>
            </div>
            <div class="bg-white p-5">
                <span class="font-mono text-[10px] uppercase text-muted font-bold block mb-1">Status Layanan</span>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 font-mono text-[10px] font-bold uppercase border {{ $customerStatus['class'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $customerStatus['dot'] }}"></span>
                    {{ $customerStatus['label'] }}
                </span>
                <p class="font-mono text-[11px] text-muted mt-1.5">
                    @if($vps->expires_at)
                        {{ $vps->isExpired() ? 'Berakhir' : 'Aktif s/d' }} {{ $vps->expires_at->timezone('Asia/Jakarta')->format('d M Y') }}
                    @else
                        Masa aktif belum tercatat
                    @endif
                </p>
            </div>
        </div>

        <div class="bg-white border border-hairline p-6">
            <h3 class="font-display text-base font-bold text-ink mb-4 border-b border-hairline pb-3">Detail Server</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">
                <div>
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Sistem Operasi</span>
                    <p class="font-bold text-ink text-sm">{{ $vps->os_label ?: '—' }}</p>
                </div>
                <div>
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Stack / Control Panel</span>
                    <p class="font-bold text-ink text-sm">{{ $vps->control_panel_label ?: '—' }}</p>
                </div>
                <div>
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Lokasi Datacenter</span>
                    <p class="font-mono font-bold text-ink text-sm uppercase">{{ $vps->datacenter_location ?: '—' }}</p>
                </div>
                <div>
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Alamat IP Publik</span>
                    <p class="font-mono font-bold text-ink text-sm">{{ $vps->public_ip ?? 'Belum dialokasikan' }}</p>
                </div>
                <div>
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Infrastruktur</span>
                    <p class="font-bold text-ink text-sm">{{ $vps->provider_label }}</p>
                </div>
                <div>
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Aktif Sejak</span>
                    <p class="font-mono font-bold text-ink text-sm">{{ $vps->starts_at ? $vps->starts_at->timezone('Asia/Jakarta')->format('d M Y') : '—' }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Access & SSH -->
    <div x-show="activeTab === 'access'" class="space-y-6" style="display: none;">
        <div class="bg-white border border-hairline p-6 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-hairline pb-4">
                <div>
                    <h3 class="font-display text-base font-bold text-ink">Kredensial Akses SSH</h3>
                    <p class="text-xs text-muted mt-0.5">Password disimpan terenkripsi (AES-256) dan hanya dibuka saat Anda menekan ikon mata.</p>
                </div>
                <span class="px-3 py-1 font-mono text-xs font-bold uppercase bg-creamDark text-ink border border-hairline self-start sm:self-auto">
                    Port {{ $vps->ssh_port ?: 22 }} (SSH)
                </span>
            </div>

            <div class="space-y-3 text-xs divide-y divide-hairline">
                <div class="flex justify-between items-center py-2.5">
                    <span class="font-mono uppercase text-muted">Dedicated Public IPv4</span>
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-ink text-sm">{{ $vps->public_ip ?? 'Belum dialokasikan' }}</span>
                        @if($vps->public_ip)
                            <button type="button" @click="copyText('{{ $vps->public_ip }}')" class="p-1.5 border border-hairline hover:border-ink text-muted hover:text-ink cursor-pointer" title="Salin IP">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="flex justify-between items-center py-2.5">
                    <span class="font-mono uppercase text-muted">Private Subnet IP</span>
                    <span class="font-mono text-ink">{{ $vps->private_ip ?? '—' }}</span>
                </div>

                <div class="flex justify-between items-center py-2.5">
                    <span class="font-mono uppercase text-muted">Username SSH</span>
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-ink font-bold text-sm">{{ $vps->ssh_user }}</span>
                        <button type="button" @click="copyText(@js($vps->ssh_user))" class="p-1.5 border border-hairline hover:border-ink text-muted hover:text-ink cursor-pointer" title="Salin username">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-3.5 gap-3">
                    <div>
                        <span class="font-mono uppercase text-muted font-bold block">Password SSH Awal</span>
                        <span class="text-[11px] text-muted block mt-0.5">Klik ikon mata untuk menampilkan atau menyembunyikan.</span>
                        <span class="text-[11px] text-muted block">Jika sudah diubah lewat <code class="font-mono text-ink">passwd</code>, password awal ini tidak berlaku lagi.</span>
                    </div>

                    <div class="flex flex-col items-start sm:items-end gap-1.5">
                        <div class="flex items-center gap-2">
                            <span x-show="!showPassword" class="font-mono text-muted font-bold tracking-widest text-sm bg-creamDark px-3 py-1.5 border border-hairline">
                                ••••••••••••••••
                            </span>
                            <span x-show="showPassword" style="display: none;" class="font-mono font-bold text-sm bg-emerald-50 text-emerald-950 px-3 py-1.5 border border-emerald-500 select-all break-all" x-text="revealedPassword"></span>

                            <button type="button" @click="togglePassword()" :disabled="passwordLoading"
                                    :title="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    class="p-2 border border-ink bg-white hover:bg-creamDark text-ink disabled:opacity-50 transition-colors cursor-pointer">
                                <svg x-show="passwordLoading" style="display: none;" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                <svg x-show="!passwordLoading && !showPassword" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="!passwordLoading && showPassword" style="display: none;" class="w-4 h-4 text-electric" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            </button>

                            <button type="button" x-show="showPassword" style="display: none;" @click="copySecret(revealedPassword)" class="px-3 py-1.5 border border-ink bg-white hover:bg-creamDark font-mono text-xs font-bold uppercase text-ink cursor-pointer">
                                Salin
                            </button>
                        </div>
                        <span x-show="passwordError" style="display: none;" class="font-mono text-[11px] text-red-700 font-bold" x-text="passwordError"></span>
                        <span x-show="showPassword && revealedAt" style="display: none;" class="font-mono text-[10px] text-muted" x-text="'Terakhir dibuka: ' + revealedAt"></span>
                    </div>
                </div>

                <div class="pt-4">
                    <span class="font-mono uppercase text-xs font-bold text-ink block mb-2">Perintah Terminal Cepat</span>
                    <div class="flex items-center justify-between gap-2 p-4 bg-ink text-cream font-mono text-xs">
                        <span class="select-all">{{ $vps->ssh_command }}</span>
                        <button type="button" @click="copyText('{{ $vps->ssh_command }}')" class="px-3 py-1.5 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase shrink-0 cursor-pointer">
                            Salin Perintah
                        </button>
                    </div>
                </div>

                @if($vps->isDatabasePackage())
                    <div class="mt-4 p-4 bg-creamDark/60 border border-hairline space-y-3">
                        <h4 class="font-mono font-bold text-ink text-xs uppercase tracking-wider">Panduan Remote Database Client</h4>
                        <p class="text-xs text-muted leading-relaxed">
                            Hubungkan backend atau remote client via IP Publik Dedicated server:
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs font-mono">
                            <div class="bg-white p-2.5 border border-hairline"><span class="text-muted block text-[10px]">PostgreSQL</span><span class="font-bold text-ink">Port 5432</span></div>
                            <div class="bg-white p-2.5 border border-hairline"><span class="text-muted block text-[10px]">MySQL / MariaDB</span><span class="font-bold text-ink">Port 3306</span></div>
                            <div class="bg-white p-2.5 border border-hairline"><span class="text-muted block text-[10px]">Redis Cache</span><span class="font-bold text-ink">Port 6379</span></div>
                            <div class="bg-white p-2.5 border border-hairline"><span class="text-muted block text-[10px]">Vector Qdrant</span><span class="font-bold text-ink">Port 6333</span></div>
                        </div>
                        <div class="bg-ink text-cream p-3 font-mono text-[11px] overflow-x-auto">
                            DATABASE_URL="postgresql://username:password&#64;{{ $vps->public_ip }}:5432/database_name"
                        </div>
                        <p class="text-[11px] text-muted">
                            Disarankan SSH Tunneling (<code class="bg-white px-1 border border-hairline text-ink font-mono">ssh -L 5432:localhost:5432 {{ $vps->ssh_user }}&#64;{{ $vps->public_ip }}</code>) di production.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Tab Subscription Lifecycle -->
    <div x-show="activeTab === 'subscription'" class="space-y-6" style="display: none;">
        <div class="bg-white border border-hairline p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">
                <div>
                    <h3 class="font-display text-base font-bold text-ink">Siklus Tagihan &amp; Masa Aktif</h3>
                    <p class="text-xs text-muted mt-0.5">Informasi masa berlangganan, grace period, dan perpanjangan otomatis.</p>
                </div>
                <a href="{{ route('dashboard.billing') }}" class="rounded-none px-4 py-2 border border-ink hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase transition-colors self-start sm:self-auto">
                    Lihat Riwayat Invoice →
                </a>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-px bg-hairline border border-hairline">
                <div class="p-4 bg-white">
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Siklus Berlangganan</span>
                    <p class="font-mono font-bold text-ink text-sm uppercase">{{ str_replace('_', ' ', $vps->billing_cycle ?? 'monthly') }}</p>
                    <p class="text-[11px] text-muted mt-1">
                        @if(($vps->billing_cycle ?? 'monthly') === 'annual') Diskon 15% diterapkan
                        @elseif(($vps->billing_cycle ?? 'monthly') === 'semi_annual') Diskon 10% diterapkan
                        @elseif(($vps->billing_cycle ?? 'monthly') === 'quarterly') Diskon 5% diterapkan
                        @else Tagihan bulanan standar @endif
                    </p>
                </div>

                <div class="p-4 bg-white">
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Mulai Layanan</span>
                    <p class="font-mono font-bold text-ink text-sm">{{ $vps->starts_at ? $vps->starts_at->timezone('Asia/Jakarta')->format('d M Y') : '-' }}</p>
                    <p class="font-mono text-[11px] text-muted mt-1">{{ $vps->starts_at ? $vps->starts_at->timezone('Asia/Jakarta')->format('H:i T') : '' }}</p>
                </div>

                <div class="p-4 bg-white">
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Jatuh Tempo (Expiry)</span>
                    <p class="font-mono font-bold text-sm {{ $vps->isExpired() ? 'text-red-700' : 'text-ink' }}">
                        {{ $vps->expires_at ? $vps->expires_at->timezone('Asia/Jakarta')->format('d M Y') : '—' }}
                    </p>
                    <p class="font-mono text-[11px] text-muted mt-1">
                        @if($vps->isExpired())
                            <span class="text-red-700 font-bold">Telah Kadaluarsa</span>
                        @elseif($vps->expires_at)
                            Sisa {{ $vps->days_until_expiry }} hari lagi
                        @endif
                    </p>
                </div>

                <div class="p-4 bg-white">
                    <span class="font-mono text-[10px] uppercase text-muted block mb-1">Batas Masa Tenggang</span>
                    <p class="font-mono font-bold text-ink text-sm">
                        {{ $vps->grace_period_ends_at ? $vps->grace_period_ends_at->timezone('Asia/Jakarta')->format('d M Y') : '—' }}
                    </p>
                    <p class="text-[11px] text-muted mt-1">Masa tenggang: {{ $graceDays }} hari setelah jatuh tempo</p>
                </div>
            </div>

            <div class="p-4 border border-hairline bg-creamDark/40 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
                <div>
                    <strong class="font-display text-ink block font-bold">Perpanjangan Otomatis (Auto-Renewal)</strong>
                    <span class="text-muted">Status saat ini: {{ $vps->auto_renew ? 'Aktif (Otomatis ditagih saat jatuh tempo)' : 'Non-aktif (Pemberitahuan manual)' }}</span>
                </div>
                <span class="px-3 py-1 font-mono text-[11px] font-bold uppercase {{ $vps->auto_renew ? 'bg-emerald-100 text-emerald-900 border border-emerald-500' : 'bg-white text-muted border border-hairline' }}">
                    {{ $vps->auto_renew ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Tab Control Panel -->
    <div x-show="activeTab === 'panel'" class="space-y-6" style="display: none;">
        <div class="bg-white border border-hairline p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-ink mb-1">Akses Stack Server</h3>
            <p class="text-xs text-muted">
                Stack terpasang: <strong class="font-mono text-ink">{{ $vps->control_panel_label }}</strong>
            </p>

            @if($vps->panel_url)
                <div class="p-4 bg-creamDark/40 border border-hairline text-xs space-y-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <span class="font-mono uppercase text-muted font-bold">URL Dashboard Panel</span>
                        <div class="flex items-center gap-2 min-w-0">
                            <a href="{{ $vps->panel_url }}" target="_blank" rel="noopener" class="font-mono font-bold text-electric underline truncate">
                                {{ $vps->panel_url }}
                            </a>
                            <button type="button" @click="copyText(@js($vps->panel_url))" class="px-2.5 py-1 border border-ink bg-white hover:bg-creamDark font-mono text-[10px] font-bold uppercase text-ink shrink-0 cursor-pointer">Salin</button>
                        </div>
                    </div>
                    <p class="text-xs text-muted">Buka URL di atas untuk masuk atau membuat akun administrator panel.</p>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <a href="{{ $vps->panel_url }}" target="_blank" rel="noopener" class="rounded-none px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase transition-colors">
                        Buka Control Panel ↗
                    </a>
                    <a href="{{ route('dashboard.support') }}" class="rounded-none px-5 py-2.5 border border-ink hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase transition-colors">
                        Bantuan Setup
                    </a>
                </div>
            @else
                <div class="p-4 bg-amber-50 border border-amber-500 text-xs text-amber-950 space-y-1">
                    <p class="font-mono font-bold uppercase">Alamat panel belum tersedia</p>
                    <p>Tim kami belum mencantumkan alamat panel untuk server ini. Hubungi support dan kami akan mengirimkannya.</p>
                </div>

                <div class="pt-2">
                    <a href="{{ route('dashboard.support') }}" class="rounded-none inline-block px-5 py-2.5 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase transition-colors">
                        Minta Alamat Panel
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Tab Permintaan & Bantuan -->
    <div x-show="activeTab === 'requests'" class="space-y-6" style="display: none;">
        <div class="bg-white border border-hairline p-6">
            <h3 class="font-display text-base font-bold text-ink">Butuh Apa?</h3>
            <p class="text-xs text-muted mt-0.5 mb-4">
                Beberapa hal bisa Anda lakukan sendiri dalam hitungan detik. Sisanya dikerjakan tim kami maksimal {{ $slaHours }} jam kerja ({{ $supportHours }}).
            </p>

            <div class="divide-y divide-hairline text-xs">
                @if(in_array($vps->status, ['running', 'error'], true))
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3.5">
                        <div>
                            <p class="font-display font-bold text-ink">Restart server</p>
                            <p class="text-muted">Lakukan sendiri lewat SSH, selesai dalam 1-2 menit.</p>
                        </div>
                        <button type="button" @click="rebootGuideModal = true" class="rounded-none px-3.5 py-2 border border-ink hover:bg-creamDark font-mono text-[11px] font-bold uppercase text-ink self-start sm:self-auto cursor-pointer">Lihat Caranya</button>
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3.5">
                    <div>
                        <p class="font-display font-bold text-ink">Ganti password SSH</p>
                        <p class="text-muted">Login SSH lalu jalankan perintah <code class="font-mono text-ink bg-creamDark px-1">passwd</code>.</p>
                    </div>
                    <button type="button" @click="copyText('passwd')" class="rounded-none px-3.5 py-2 border border-ink hover:bg-creamDark font-mono text-[11px] font-bold uppercase text-ink self-start sm:self-auto cursor-pointer">Salin Perintah</button>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3.5">
                    <div>
                        <p class="font-display font-bold text-ink">Server tidak bisa diakses sama sekali</p>
                        <p class="text-muted">SSH tidak merespons atau server hang. Tim akan me-restart dari sisi infrastruktur.</p>
                    </div>
                    @if(isset($openRequests['unreachable']))
                        <a href="{{ route('dashboard.support.show', $openRequests['unreachable']->id) }}" class="rounded-none px-3.5 py-2 border border-electric bg-creamDark text-electric font-mono text-[11px] font-bold uppercase self-start sm:self-auto">Sedang Diproses</a>
                    @elseif($vps->acceptsServiceRequests())
                        <button type="button" @click="unreachableModal = true" class="rounded-none px-3.5 py-2 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase self-start sm:self-auto cursor-pointer">Laporkan ke Tim</button>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3.5">
                    <div>
                        <p class="font-display font-bold text-ink">Install ulang OS (reinstall)</p>
                        <p class="text-muted">Seluruh data terhapus. Tim memasang OS baru dan password SSH baru muncul di dashboard.</p>
                    </div>
                    @if(isset($openRequests['reinstall']))
                        <a href="{{ route('dashboard.support.show', $openRequests['reinstall']->id) }}" class="rounded-none px-3.5 py-2 border border-electric bg-creamDark text-electric font-mono text-[11px] font-bold uppercase self-start sm:self-auto">Sedang Diproses</a>
                    @elseif($vps->acceptsServiceRequests())
                        <button type="button" @click="reinstallModal = true" class="rounded-none px-3.5 py-2 border border-ink hover:bg-creamDark font-mono text-[11px] font-bold uppercase text-ink self-start sm:self-auto cursor-pointer">Ajukan Reinstall</button>
                    @endif
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3.5">
                    <div>
                        <p class="font-display font-bold text-ink">Pertanyaan atau kendala lain</p>
                        <p class="text-muted">Konfigurasi, domain, panel, atau tagihan.</p>
                    </div>
                    <a href="{{ route('dashboard.support') }}" class="rounded-none px-3.5 py-2 border border-ink hover:bg-creamDark font-mono text-[11px] font-bold uppercase text-ink self-start sm:self-auto">Buat Tiket Bantuan</a>
                </div>
            </div>
        </div>

        <!-- Server Ticket History -->
        <div class="bg-white border border-hairline overflow-hidden">
            <div class="p-6 border-b border-hairline">
                <h3 class="font-display text-base font-bold text-ink">Riwayat Permintaan Server Ini</h3>
                <p class="text-xs text-muted mt-0.5">10 tiket terakhir yang terkait dengan server ini.</p>
            </div>

            @php
                $ticketStatusLabels = [
                    'open' => ['Menunggu', 'bg-amber-100 text-amber-950 border-amber-400'],
                    'in_progress' => ['Dikerjakan', 'bg-electric text-white border-electric'],
                    'resolved' => ['Selesai', 'bg-emerald-100 text-emerald-900 border-emerald-400'],
                    'closed' => ['Ditutup', 'bg-creamDark text-muted border-hairline'],
                ];
            @endphp

            <div class="divide-y divide-hairline text-xs">
                @forelse($serverTickets as $serverTicket)
                    @php [$ticketStatusText, $ticketStatusClass] = $ticketStatusLabels[$serverTicket->status] ?? [ucfirst($serverTicket->status), 'bg-creamDark text-ink border-hairline']; @endphp
                    <a href="{{ route('dashboard.support.show', $serverTicket->id) }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-6 py-3.5 hover:bg-creamDark/30 transition-colors">
                        <div>
                            <p class="font-display font-bold text-ink">{{ $serverTicket->subject }}</p>
                            <p class="font-mono text-[11px] text-muted mt-0.5">
                                #TK-{{ str_pad($serverTicket->id, 4, '0', STR_PAD_LEFT) }} · {{ $serverTicket->type_label }} · {{ $serverTicket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                            </p>
                        </div>
                        <span class="px-2.5 py-0.5 border font-mono text-[10px] font-bold uppercase self-start sm:self-auto {{ $ticketStatusClass }}">{{ $ticketStatusText }}</span>
                    </a>
                @empty
                    <p class="px-6 py-8 text-center font-mono text-xs text-muted">Belum ada permintaan untuk server ini.</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tab Audit Trail & Activity Log -->
    <div x-show="activeTab === 'logs'" class="space-y-6" style="display: none;">
        <div class="bg-white border border-hairline overflow-hidden">
            <div class="p-6 border-b border-hairline flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="font-display text-base font-bold text-ink">Audit Trail &amp; Riwayat Aktivitas Server</h3>
                    <p class="text-xs text-muted mt-0.5">25 aktivitas terakhir: permintaan layanan, akses kredensial, dan perubahan status.</p>
                </div>
                <span class="font-mono text-[10px] font-bold uppercase px-3 py-1 bg-creamDark text-ink border border-hairline self-start sm:self-auto">
                    Immutable Audit Log
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-creamDark border-b border-hairline font-mono text-ink font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-3 px-4">Waktu</th>
                            <th class="py-3 px-4">Aksi</th>
                            <th class="py-3 px-4">Deskripsi</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        @forelse($vps->activityLogs as $log)
                            <tr class="hover:bg-creamDark/30 transition-colors">
                                <td class="py-3 px-4 whitespace-nowrap text-muted font-mono">
                                    {{ $log->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i:s') }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-creamDark text-ink">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-ink">{{ $log->description }}</td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @php
                                        $logStatusLabel = [
                                            'completed' => 'Selesai',
                                            'submitted' => 'Diajukan',
                                            'pending' => 'Menunggu',
                                            'failed' => 'Gagal',
                                        ][$log->status] ?? $log->status;
                                    @endphp
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase {{ in_array($log->status, ['completed', 'submitted'], true) ? 'bg-creamDark text-ink border border-hairline' : 'bg-amber-100 text-amber-950 border border-amber-400' }}">
                                        {{ $logStatusLabel }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-muted font-mono">
                                    {{ $log->ip_address ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center font-mono text-xs text-muted">
                                    Belum ada catatan aktivitas untuk instance ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @php $serverName = $vps->hostname ?? ('VPS-' . $vps->id); @endphp

    <!-- Modal: Cara Restart Server -->
    <div x-show="rebootGuideModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/60" style="display: none;">
        <div class="bg-white border-2 border-ink p-6 max-w-md w-full max-h-[90vh] overflow-y-auto" @click.away="rebootGuideModal = false">
            <h3 class="font-display text-lg font-bold text-ink mb-1">Cara Restart Server</h3>
            <p class="text-xs text-muted mb-5">Anda memegang akses penuh ke server, jadi restart dapat dijalankan langsung via SSH.</p>

            <ol class="space-y-4 text-xs">
                <li>
                    <p class="font-display font-bold text-ink mb-1.5">1. Login ke server</p>
                    <div class="flex items-center justify-between gap-2 p-3 bg-ink text-cream font-mono">
                        <span class="truncate">{{ $vps->ssh_command }}</span>
                        <button type="button" @click="copyText(@js($vps->ssh_command))" class="px-2.5 py-1 bg-electric text-white text-[10px] uppercase font-bold shrink-0 cursor-pointer">Salin</button>
                    </div>
                </li>
                <li>
                    <p class="font-display font-bold text-ink mb-1.5">2. Jalankan perintah restart</p>
                    <div class="flex items-center justify-between gap-2 p-3 bg-ink text-cream font-mono">
                        <span>sudo reboot</span>
                        <button type="button" @click="copyText('sudo reboot')" class="px-2.5 py-1 bg-electric text-white text-[10px] uppercase font-bold shrink-0 cursor-pointer">Salin</button>
                    </div>
                </li>
                <li>
                    <p class="font-display font-bold text-ink mb-1">3. Tunggu 1-2 menit, lalu login kembali</p>
                    <p class="text-muted">Koneksi SSH otomatis terputus saat server restart.</p>
                </li>
            </ol>

            <div class="mt-5 p-4 bg-creamDark/60 border border-hairline text-xs">
                <p class="font-display font-bold text-ink">Tidak bisa login SSH sama sekali?</p>
                <p class="text-muted mt-0.5">Laporkan ke tim. Kami akan me-restart server dari sisi infrastruktur.</p>
                @if(isset($openRequests['unreachable']))
                    <a href="{{ route('dashboard.support.show', $openRequests['unreachable']->id) }}" class="inline-block mt-2.5 px-3.5 py-2 border border-electric bg-white text-electric font-mono text-[11px] font-bold uppercase">
                        Laporan Anda sedang diproses →
                    </a>
                @elseif($vps->acceptsServiceRequests())
                    <button type="button" @click="rebootGuideModal = false; unreachableModal = true" class="mt-2.5 rounded-none px-3.5 py-2 bg-ink hover:bg-electric text-white font-mono text-[11px] font-bold uppercase cursor-pointer">
                        Laporkan Server Tidak Bisa Diakses
                    </button>
                @endif
            </div>

            <div class="pt-4 flex justify-end">
                <button type="button" @click="rebootGuideModal = false" class="rounded-none px-4 py-2 border border-ink hover:bg-creamDark font-mono text-xs font-bold uppercase text-ink cursor-pointer">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    @if($vps->acceptsServiceRequests() && !isset($openRequests['unreachable']))
        <!-- Modal: Unreachable Server -->
        <div x-show="unreachableModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/60" style="display: none;">
            <div class="bg-white border-2 border-ink p-6 max-w-md w-full" @click.away="unreachableModal = false">
                <h3 class="font-display text-lg font-bold text-ink mb-1">Laporkan Server Tidak Bisa Diakses</h3>
                <p class="text-xs text-muted mb-4">
                    Tim akan mengecek dan me-restart <strong class="font-mono text-ink">{{ $serverName }}</strong> dari sisi infrastruktur, maksimal {{ $slaHours }} jam kerja ({{ $supportHours }}).
                </p>

                <form action="{{ route('dashboard.vps.report-unreachable', $vps->id) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Keterangan (opsional)</label>
                        <textarea name="description" rows="3" maxlength="1000" placeholder="Contoh: SSH timeout sejak jam 10 pagi, website tidak bisa dibuka."
                                  class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 font-mono text-red-700 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button type="button" @click="unreachableModal = false" class="rounded-none px-4 py-2.5 border border-ink text-ink hover:bg-creamDark font-mono text-xs font-bold uppercase cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="rounded-none px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase cursor-pointer">
                            Kirim Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($vps->acceptsServiceRequests() && !isset($openRequests['reinstall']))
        @php
            $reinstallOsOptions = $vps->reinstallOsOptions();
            $reinstallStackOptions = $vps->reinstallStackOptions();
            $selectedOs = old('os', array_key_exists((string) $vps->os, $reinstallOsOptions) ? $vps->os : array_key_first($reinstallOsOptions));
            $selectedStack = old('control_panel', $vps->control_panel);
        @endphp
        <!-- Modal: Request Reinstall OS -->
        <div x-show="reinstallModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/60" style="display: none;">
            <div class="bg-white border-2 border-ink p-6 max-w-md w-full max-h-[90vh] overflow-y-auto" @click.away="reinstallModal = false">
                <h3 class="font-display text-lg font-bold text-ink mb-1">Ajukan Reinstall OS</h3>
                <p class="text-xs text-muted mb-3">
                    Reinstall dikerjakan tim kami, maksimal {{ $slaHours }} jam kerja ({{ $supportHours }}). Server tetap berjalan sampai tim mulai mengerjakan.
                </p>
                <p class="text-xs text-red-900 bg-red-50 p-3 border border-red-400 mb-4 font-bold">
                    Seluruh data di server akan terhapus permanen. Backup data Anda sebelum mengajukan.
                </p>

                <form action="{{ route('dashboard.vps.request-reinstall', $vps->id) }}" method="POST" class="space-y-4 text-xs">
                    @csrf
                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Sistem Operasi Baru</label>
                        <select name="os" required class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink font-mono focus:outline-none focus:border-electric">
                            @foreach($reinstallOsOptions as $osKey => $osName)
                                <option value="{{ $osKey }}" @selected((string) $selectedOs === (string) $osKey)>{{ $osName }}</option>
                            @endforeach
                        </select>
                        @error('os')
                            <p class="mt-1 font-mono text-red-700 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Stack / Control Panel</label>
                        @if($vps->hasFixedStack())
                            <p class="px-3.5 py-2.5 border border-hairline bg-creamDark/50 text-ink font-mono">
                                {{ $vps->control_panel_label }} (bawaan paket, dipasang ulang)
                            </p>
                        @else
                            <select name="control_panel" required class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink font-mono focus:outline-none focus:border-electric">
                                @foreach($reinstallStackOptions as $stackKey => $stackName)
                                    <option value="{{ $stackKey }}" @selected((string) $selectedStack === (string) $stackKey)>{{ $stackName }}</option>
                                @endforeach
                            </select>
                            @error('control_panel')
                                <p class="mt-1 font-mono text-red-700 font-bold">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">Catatan untuk tim (opsional)</label>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Contoh: mohon aktifkan swap 2 GB setelah install."
                                  class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink focus:outline-none focus:border-electric">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1 font-mono text-red-700 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="font-mono text-[11px] font-bold uppercase text-ink block mb-1.5">
                            Ketik <span class="text-electric">{{ $serverName }}</span> untuk konfirmasi
                        </label>
                        <input type="text" name="confirm_hostname" required autocomplete="off" value="{{ old('confirm_hostname') }}"
                               class="w-full rounded-none px-3.5 py-2.5 text-base sm:text-sm border border-hairline bg-white text-ink font-mono focus:outline-none focus:border-electric">
                        @error('confirm_hostname')
                            <p class="mt-1 font-mono text-red-700 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    <p class="text-[11px] text-muted">
                        Setelah selesai, password SSH baru tampil di tab Akses dan Anda menerima email pemberitahuan.
                    </p>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button type="button" @click="reinstallModal = false" class="rounded-none px-4 py-2.5 border border-ink text-ink hover:bg-creamDark font-mono text-xs font-bold uppercase cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="rounded-none px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase cursor-pointer">
                            Kirim Permintaan Reinstall
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Modal: Renewal --}}
    @if($vps->canBeRenewed())
        <div x-show="renewalModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-ink/60 p-4 overflow-y-auto"
             @keydown.escape.window="renewalModal = false">
            <div class="bg-white border-2 border-ink max-w-lg w-full p-6 space-y-5 my-8 text-ink" @click.away="renewalModal = false">
                <div class="flex items-start justify-between border-b border-hairline pb-4">
                    <div>
                        <span class="font-mono text-[10px] uppercase tracking-widest text-electric font-bold block">RENEWAL INVOICE</span>
                        <h3 class="font-display text-lg font-bold text-ink">Perpanjang Layanan VPS</h3>
                        <p class="font-mono text-xs text-muted mt-0.5">{{ $vps->hostname }} ({{ $vps->public_ip ?? 'IP Pending' }})</p>
                    </div>
                    <button type="button" @click="renewalModal = false" class="p-1.5 border border-hairline hover:border-ink text-ink cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                @if($vps->isEolWithReplacement())
                    <div class="p-3.5 bg-amber-50 border border-amber-500 text-xs text-amber-950 leading-relaxed">
                        <div class="font-mono font-bold uppercase mb-1">Penyesuaian Skema Paket (End-of-Life)</div>
                        Paket lama tidak lagi diproduksi. Perpanjangan dialihkan ke paket pengganti <strong>{{ $vps->eol_replacement_spec->name }}</strong> tanpa mengubah IP, data, atau konfigurasi di server Anda.
                    </div>
                @endif

                <div class="bg-creamDark/40 border border-hairline p-4 space-y-2.5 text-xs">
                    <div class="flex justify-between items-center text-muted">
                        <span>Paket Layanan</span>
                        <span class="font-display font-bold text-ink">{{ $vps->renewal_spec->name ?? $vps->package_name ?? 'Cloud VPS' }}</span>
                    </div>
                    <div class="flex justify-between items-center text-muted">
                        <span>Masa Aktif Saat Ini</span>
                        <span class="font-mono font-bold text-ink">
                            {{ $vps->expires_at ? $vps->expires_at->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB' : 'Belum ditentukan' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-muted">
                        <span>Perpanjangan Ditambahkan</span>
                        <span class="font-mono font-bold text-emerald-900 bg-emerald-100 px-2 py-0.5 border border-emerald-400">
                            +1 Siklus ({{ ucfirst(str_replace('_', ' ', $vps->billing_cycle ?? 'monthly')) }})
                        </span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-hairline">
                        <span class="font-mono uppercase text-muted">Masa Aktif Baru Menjadi</span>
                        <span class="font-mono font-bold text-ink">
                            {{ ($vps->expires_at && $vps->expires_at->isFuture() ? $vps->expires_at->copy()->addMonth() : now()->addMonth())->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB' }}
                        </span>
                    </div>
                    @if($vps->expires_at && $vps->expires_at->isFuture())
                        <p class="text-[11px] text-muted pt-0.5">* Sisa masa aktif diakumulasikan dari tanggal kedaluwarsa saat ini.</p>
                    @endif
                </div>

                <div class="p-4 bg-white border border-ink space-y-2">
                    <div class="flex justify-between items-baseline">
                        <span class="font-mono text-xs font-bold uppercase text-ink">Total Biaya Perpanjangan</span>
                        <span class="font-mono text-xl font-bold text-electric">Rp {{ number_format($vps->renewal_price, 0, ',', '.') }}</span>
                    </div>
                    @if($vps->custom_renewal_price)
                        <div class="inline-block px-2 py-0.5 bg-creamDark font-mono text-[10px] font-bold uppercase text-electric border border-electric">
                            Harga Khusus Kontrak / Pelanggan Loyal
                        </div>
                    @elseif($vps->order && $vps->order->amount != $vps->renewal_price)
                        <div class="inline-block px-2 py-0.5 bg-creamDark font-mono text-[10px] font-bold uppercase text-muted border border-hairline">
                            Penyesuaian tarif infrastruktur katalog terkini
                        </div>
                    @endif
                </div>

                <form action="{{ route('dashboard.vps.renew', $vps->id) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block font-mono text-[11px] font-bold text-ink uppercase tracking-wider mb-2">Metode Pembayaran</label>
                        <div class="space-y-2">
                            <label class="flex items-center justify-between p-3.5 border border-ink cursor-pointer bg-cream has-checked:border-electric">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="payment_method" value="midtrans_snap" checked class="text-electric focus:ring-electric">
                                    <div>
                                        <div class="font-display text-xs font-bold text-ink">Pembayaran Online Otomatis</div>
                                        <div class="text-[11px] text-muted">QRIS, Virtual Account Bank, E-Wallet</div>
                                    </div>
                                </div>
                                <span class="font-mono text-[10px] font-bold uppercase text-ink bg-white border border-hairline px-2 py-0.5">Instan</span>
                            </label>

                            @php
                                $userBalance = auth()->user()->credit_balance ?? 0;
                                $canPayWithBalance = $userBalance >= $vps->renewal_price;
                            @endphp
                            <label class="flex items-center justify-between p-3.5 border transition-colors {{ $canPayWithBalance ? 'border-hairline cursor-pointer hover:border-ink' : 'border-hairline bg-creamDark/40 opacity-60 cursor-not-allowed' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="payment_method" value="credit" {{ $canPayWithBalance ? '' : 'disabled' }} class="text-electric focus:ring-electric">
                                    <div>
                                        <div class="font-display text-xs font-bold text-ink">Potong Saldo Akun</div>
                                        <div class="font-mono text-[11px] text-muted">Saldo saat ini: Rp {{ number_format($userBalance, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                                @if(!$canPayWithBalance)
                                    <span class="font-mono text-[10px] font-bold uppercase text-red-700">Saldo Tidak Cukup</span>
                                @else
                                    <span class="font-mono text-[10px] font-bold uppercase text-emerald-900 bg-emerald-100 border border-emerald-400 px-2 py-0.5">Tersedia</span>
                                @endif
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="renewalModal = false" class="rounded-none px-4 py-2.5 border border-ink font-mono text-xs font-bold uppercase text-ink hover:bg-creamDark transition-colors cursor-pointer">
                            Batal
                        </button>
                        <button type="submit" class="rounded-none px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase transition-colors flex items-center gap-2 cursor-pointer">
                            <span>Lanjutkan Pembayaran</span>
                            <span>→</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
