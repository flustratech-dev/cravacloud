@extends('layouts.admin', ['title' => 'Proses Order Shopee', 'headerTitle' => 'Proses Order Marketplace Shopee', 'backUrl' => route('admin.index'), 'backLabel' => 'Kembali ke Dashboard Admin'])

@section('content')
<div class="space-y-6" x-data="{
    smartPasteOpen: false,
    smartPasteText: '',
    shopeeOrderId: '{{ old('shopee_order_id', '') }}',
    customerName: '{{ old('customer_name', '') }}',
    customerEmail: '{{ old('customer_email', '') }}',
    customerPhone: '{{ old('customer_phone', '') }}',
    vpsSpecId: '{{ old('vps_spec_id', $specs->first()?->id ?? '') }}',
    datacenterLocation: '{{ old('datacenter_location', 'indonesia') }}',
    os: '{{ old('os', 'ubuntu2404') }}',
    controlPanel: '{{ old('control_panel', 'none') }}',
    hostname: '{{ old('hostname', '') }}',
    shopeeMode: '{{ old('shopee_mode', 'auto') }}',
    autoProvision: false,
    publicIp: '{{ old('public_ip', '') }}',
    sshPort: '{{ old('ssh_port', '22') }}',
    sshUsername: '{{ old('ssh_username', 'ubuntu') }}',
    rootPassword: '{{ old('root_password', '') }}',
    activeMsgTab: 'ready',
    specs: @js($specs),
    operatingSystems: @js($operatingSystems),
    stackLabels: @js($stackLabels),

    // Modals
    copyModalOpen: false,
    copyModalData: null,
    copyModalTab: 'ready',
    provisionModalOpen: false,
    provisionModalData: null,
    provPublicIp: '',
    provHostname: '',
    provOs: 'ubuntu2404',
    provSshPort: 22,
    provSshUsername: 'ubuntu',
    provRootPassword: '',
    provAppUrl: '',

    defaultSshUserFor(osKey) {
        return String(osKey || 'ubuntu2404').toLowerCase().startsWith('ubuntu') ? 'ubuntu' : 'root';
    },

    init() {
        this.syncSpecSettings();
        @if(!old('ssh_username'))
            this.sshUsername = this.defaultSshUserFor(this.os);
        @endif
        this.$watch('os', (val) => {
            if (!this.sshUsername || this.sshUsername === 'ubuntu' || this.sshUsername === 'root') {
                this.sshUsername = this.defaultSshUserFor(val);
            }
        });
        this.$watch('provOs', (val) => {
            if (!this.provSshUsername || this.provSshUsername === 'ubuntu' || this.provSshUsername === 'root') {
                this.provSshUsername = this.defaultSshUserFor(val);
            }
        });
    },

    syncSpecSettings() {
        const spec = this.specs.find(s => String(s.id) === String(this.vpsSpecId));
        if (!spec) return;

        if (!this.operatingSystems[this.os]) {
            this.os = Object.keys(this.operatingSystems)[0] || 'ubuntu2404';
        }

        if (spec.upstream_bundle_id) {
            this.shopeeMode = 'auto';
            this.autoProvision = false;
        }
    },

    parseSmartText() {
        const text = this.smartPasteText;
        if (!text || !text.trim()) {
            if (typeof showAlert === 'function') showAlert('Silakan tempel teks pesanan atau chat Shopee terlebih dahulu.', { icon: 'warning', title: 'Teks Masih Kosong' });
            return;
        }

        let extractedCount = 0;

        // Shopee Order ID
        const orderIdMatch = text.match(/(?:no\.?\s*pesanan|nomor\s*pesanan|order\s*id|pesanan|id)[:#\s]*([0-9a-zA-Z_-]{10,32})/i)
            || text.match(/\b(2[0-9]{13,16}[a-zA-Z0-9]*)\b/)
            || text.match(/\b([0-9]{14,18})\b/);
        if (orderIdMatch) {
            this.shopeeOrderId = orderIdMatch[1].trim();
            extractedCount++;
        }

        // Email
        const emailMatch = text.match(/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/);
        if (emailMatch) {
            this.customerEmail = emailMatch[1].trim().toLowerCase();
            extractedCount++;
        }

        // Phone / WhatsApp
        const phoneMatch = text.match(/(?:\+62|62|0)8[1-9][0-9]{7,11}\b/);
        if (phoneMatch) {
            let p = phoneMatch[0].trim();
            if (p.startsWith('0')) p = '62' + p.substring(1);
            if (p.startsWith('+')) p = p.substring(1);
            this.customerPhone = p;
            extractedCount++;
        }

        // Customer Name
        const nameMatch = text.match(/(?:nama|nama\s*pembeli|nama\s*akun|atas\s*nama|user)[:#\s]*([a-zA-Z\s]{3,35})(?:\r?\n|$|,)/i);
        if (nameMatch) {
            const rawName = nameMatch[1].trim();
            if (!rawName.toLowerCase().includes('shopee') && !rawName.toLowerCase().includes('pesanan')) {
                this.customerName = rawName;
                extractedCount++;
            }
        }

        // OS
        const lowerText = text.toLowerCase();
        if (lowerText.includes('windows 2022') || lowerText.includes('win 2022') || lowerText.includes('windows server 2022')) {
            this.os = 'windows2022';
            extractedCount++;
        } else if (lowerText.includes('windows 2019') || lowerText.includes('win 2019')) {
            this.os = 'windows2019';
            extractedCount++;
        } else if (lowerText.includes('windows 2016') || lowerText.includes('win 2016')) {
            this.os = 'windows2016';
            extractedCount++;
        } else if (lowerText.includes('windows') || lowerText.includes('rdp')) {
            this.os = 'windows2022';
            extractedCount++;
        } else if (lowerText.includes('ubuntu 24') || lowerText.includes('ubuntu 24.04')) {
            this.os = 'ubuntu2404';
            extractedCount++;
        } else if (lowerText.includes('ubuntu 22') || lowerText.includes('ubuntu 22.04')) {
            this.os = 'ubuntu2204';
            extractedCount++;
        } else if (lowerText.includes('debian 12')) {
            this.os = 'debian12';
            extractedCount++;
        } else if (lowerText.includes('debian 11')) {
            this.os = 'debian11';
            extractedCount++;
        } else if (lowerText.includes('debian')) {
            this.os = 'debian12';
            extractedCount++;
        } else if (lowerText.includes('rocky')) {
            this.os = 'rocky94';
            extractedCount++;
        } else if (lowerText.includes('centos 7')) {
            this.os = 'centos76';
            extractedCount++;
        } else if (lowerText.includes('centos')) {
            this.os = 'centos_stream9';
            extractedCount++;
        }

        // Control Panel / Stack (dynamic dari StackRegistry / Order::stackLabels())
        const stackAliases = { wp: 'wordpress', dify: 'dify_ollama' };
        let matchedStack = null;
        for (const [alias, targetKey] of Object.entries(stackAliases)) {
            if (this.stackLabels && this.stackLabels[targetKey] && new RegExp('\\b' + alias + '\\b', 'i').test(lowerText)) {
                matchedStack = targetKey;
                break;
            }
        }
        if (!matchedStack && this.stackLabels) {
            const entries = Object.entries(this.stackLabels)
                .filter(([k]) => k !== 'none')
                .sort((a, b) => Math.max(b[0].length, (b[1] || '').length) - Math.max(a[0].length, (a[1] || '').length));
            for (const [key, label] of entries) {
                const keyNorm = key.replace(/_/g, ' ');
                if (lowerText.includes(key.toLowerCase()) || lowerText.includes(keyNorm) || (label && lowerText.includes(label.toLowerCase()))) {
                    matchedStack = key;
                    break;
                }
            }
        }
        if (matchedStack) {
            this.controlPanel = matchedStack;
        }

        // Spec Match
        for (const s of this.specs) {
            const sName = s.name.toLowerCase();
            if (lowerText.includes(sName)) {
                this.vpsSpecId = s.id;
                this.syncSpecSettings();
                extractedCount++;
                break;
            }
        }

        if (extractedCount > 0) {
            if (typeof showAlert === 'function') showAlert('Data berhasil diekstrak dan diisikan ke formulir.', { icon: 'success', title: 'Ekstraksi Selesai' });
        } else {
            if (typeof showAlert === 'function') showAlert('Tidak ditemukan data spesifik dari teks yang dimasukkan.', { icon: 'warning', title: 'Data Tidak Ditemukan' });
        }
    },

    copyText(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        navigator.clipboard.writeText(el.innerText || el.textContent);
        if (typeof showAlert === 'function') showAlert('Template pesan berhasil disalin ke clipboard.', { icon: 'success', title: 'Berhasil Disalin' });
    },

    openCopyModal(data) {
        this.copyModalData = data;
        this.copyModalTab = data.has_instance ? 'ready' : 'queued';
        this.copyModalOpen = true;
    },

    openProvisionModal(data) {
        this.provisionModalData = data;
        this.provPublicIp = '';
        this.provHostname = data.hostname || ('vps-' + data.username);
        this.provOs = data.os_key || 'ubuntu2404';
        this.provSshPort = 22;
        this.provSshUsername = this.defaultSshUserFor(this.provOs);
        this.provRootPassword = '';
        this.provAppUrl = '';
        this.provisionModalOpen = true;
    }
}">

    @include('admin.partials.orders-tabs')

    @php
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
    @endphp

    <!-- Header Card -->
    <div class="bg-white border border-hairline p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-ink bg-creamDark text-ink">
                    <span>Shopee Engine</span>
                </span>
                <span class="font-mono text-[11px] text-muted">Versi 2.0 Multi-Provider</span>
            </div>
            <h2 class="font-display text-base font-bold text-ink">Proses Pesanan Shopee dan Kelola Kredensial</h2>
            <p class="text-xs text-muted mt-0.5">
                Input data pembeli marketplace Shopee. Sistem membuat akun <code class="font-mono font-bold text-ink bg-creamDark px-1">cv_xxxxxxxx</code>, order lunas, faktur, dan template pesan resmi.
            </p>
        </div>
        <div class="sm:text-right shrink-0">
            <span class="font-mono text-[10px] uppercase tracking-widest text-muted block">Akun Toko</span>
            <span class="font-display text-xs font-bold text-ink">CravaCloud Official Store</span>
        </div>
    </div>

    <!-- Credentials Card on Successful Order (Multi-Template) -->
    @if(session('shopee_credentials'))
        @php
            $c = session('shopee_credentials');
            $cleanPhone = !empty($c['customer_phone']) ? preg_replace('/[^0-9]/', '', $c['customer_phone']) : null;
            if ($cleanPhone && str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62' . substr($cleanPhone, 1);
            }
        @endphp
        <div class="bg-white border-2 border-electric p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="font-display text-sm font-bold text-ink">Pesanan Berhasil Diproses &amp; Kredensial Dibuat</h3>
                    <p class="text-xs text-muted mt-0.5">Pilih template pesan di bawah, salin, dan kirimkan ke pembeli di Shopee atau WhatsApp.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if($cleanPhone)
                        <a :href="'https://wa.me/{{ $cleanPhone }}?text=' + encodeURIComponent(document.getElementById('tmpl-' + activeMsgTab).innerText)"
                           target="_blank"
                           class="px-3.5 py-2 border border-hairline bg-white hover:border-ink text-ink font-mono font-bold text-[11px] uppercase transition-colors flex items-center gap-1.5">
                            <span>Kirim via WhatsApp</span>
                        </a>
                    @endif

                    <button @click="copyText('tmpl-' + activeMsgTab)" class="px-3.5 py-2 bg-electric hover:bg-electricHover text-white font-mono font-bold text-[11px] uppercase transition-colors flex items-center gap-1.5 cursor-pointer">
                        <span>Salin Template Aktif</span>
                    </button>
                </div>
            </div>

            <!-- Tab Headers -->
            <div class="flex flex-wrap items-center gap-1 border-b border-hairline font-mono text-xs">
                <button @click="activeMsgTab = 'ready'"
                        :class="activeMsgTab === 'ready' ? 'border-b-2 border-electric font-bold text-ink' : 'text-muted hover:text-ink'"
                        class="px-3 py-2 transition-colors whitespace-nowrap cursor-pointer">
                    Template 1: Kredensial Siap Pakai
                </button>
                <button @click="activeMsgTab = 'queued'"
                        :class="activeMsgTab === 'queued' ? 'border-b-2 border-electric font-bold text-ink' : 'text-muted hover:text-ink'"
                        class="px-3 py-2 transition-colors whitespace-nowrap cursor-pointer">
                    Template 2: Server Dalam Antrean
                </button>
                <button @click="activeMsgTab = 'review'"
                        :class="activeMsgTab === 'review' ? 'border-b-2 border-electric font-bold text-ink' : 'text-muted hover:text-ink'"
                        class="px-3 py-2 transition-colors whitespace-nowrap cursor-pointer">
                    Template 3: Konfirmasi &amp; Ulasan
                </button>
            </div>

            <!-- Tab 1 -->
            <div x-show="activeMsgTab === 'ready'" class="bg-ink text-cream p-4 font-mono text-xs leading-relaxed select-all whitespace-pre-wrap" id="tmpl-ready">Halo Kak {{ $c['customer_name'] }}!
Terima kasih telah memesan Cloud VPS di CravaCloud (No. Pesanan: {{ $c['shopee_order_id'] }}).

Berikut detail akun dan akses dashboard VPS Anda:
--------------------------------------------------
URL Dashboard : {{ $c['login_url'] }}
Username      : {{ $c['username'] }}
Password      : {{ $c['password'] }}
Paket VPS     : {{ $c['package'] }} ({{ $c['spec_specs'] }})
Datacenter    : {{ $c['datacenter'] }}
Sistem Operasi: {{ $c['os'] }}
Control Panel : {{ $c['control_panel'] }}
@if(!empty($c['is_provisioned']))
Hostname      : {{ $c['hostname'] }}
IP Publik     : {{ $c['public_ip'] }}
Port Akses    : {{ $c['ssh_port'] }}
User SSH      : {{ $c['ssh_username'] ?? 'root' }}
Password SSH  : {{ $c['root_password'] }}
@if(!empty($c['app_url']))
Akses Panel   : {{ $c['app_url'] }}
@endif
@endif
--------------------------------------------------

Silakan login ke dashboard untuk mengecek status dan mengelola server Anda.
Jika butuh panduan setup atau konfigurasi, tim kami siap membantu melalui tiket support di dashboard.

Salam hangat,
Tim CravaCloud</div>

            <!-- Tab 2 -->
            <div x-show="activeMsgTab === 'queued'" style="display: none;" class="bg-ink text-cream p-4 font-mono text-xs leading-relaxed select-all whitespace-pre-wrap" id="tmpl-queued">Halo Kak {{ $c['customer_name'] }}!
Terima kasih telah memesan Cloud VPS di CravaCloud (No. Pesanan: {{ $c['shopee_order_id'] }}).

Pesanan Anda telah kami terima dan server saat ini sedang dalam proses antrean konfigurasi datacenter (maksimal 1x24 jam, umumnya lebih cepat).

Detail akses akun dashboard Anda:
--------------------------------------------------
URL Dashboard : {{ $c['login_url'] }}
Username      : {{ $c['username'] }}
Password      : {{ $c['password'] }}
Paket VPS     : {{ $c['package'] }} ({{ $c['spec_specs'] }})
Datacenter    : {{ $c['datacenter'] }}
Sistem Operasi: {{ $c['os'] }}
--------------------------------------------------

Setelah proses alokasi selesai, IP publik dan kredensial root server akan otomatis tampil di dashboard Anda.
Mohon ditunggu sebentar ya Kak. Terima kasih atas kesabaran Anda.

Salam hangat,
Tim CravaCloud</div>

            <!-- Tab 3 -->
            <div x-show="activeMsgTab === 'review'" style="display: none;" class="bg-ink text-cream p-4 font-mono text-xs leading-relaxed select-all whitespace-pre-wrap" id="tmpl-review">Halo Kak {{ $c['customer_name'] }}!
Server Cloud VPS untuk No. Pesanan Shopee {{ $c['shopee_order_id'] }} telah selesai dikonfigurasi dan aktif berjalan normal.

Jika Anda telah selesai memeriksa dan mencoba akses server, kami mohon bantuannya untuk:
1. Membuka aplikasi Shopee dan menekan tombol "Pesanan Diterima".
2. Memberikan ulasan bintang 5 untuk mengaktifkan garansi pergantian dan dukungan bantuan prioritas selama 30 hari penuh.

Jika ada kendala teknis atau pertanyaan, silakan hubungi kami kapan saja melalui tiket bantuan dashboard.
Terima kasih telah mempercayakan kebutuhan server Anda di CravaCloud!</div>
        </div>
    @endif

    <!-- Smart Paste / Raw Text Extractor Box -->
    <div class="bg-white border border-hairline p-5 space-y-3">
        <div class="flex items-center justify-between cursor-pointer gap-3" @click="smartPasteOpen = !smartPasteOpen">
            <div>
                <h3 class="font-display text-sm font-bold text-ink">Ekstrak Cepat dari Chat Shopee (Smart Paste)</h3>
                <p class="text-xs text-muted mt-0.5">Tempelkan teks rincian pesanan dari chat atau catatan pembeli untuk mengisi form secara instan.</p>
            </div>
            <button type="button" class="font-mono text-[11px] font-bold uppercase text-ink border border-hairline hover:border-ink px-3 py-1.5 bg-creamDark/40 shrink-0 cursor-pointer">
                <span x-text="smartPasteOpen ? 'Sembunyikan' : 'Buka Kotak Paste'"></span>
            </button>
        </div>

        <div x-show="smartPasteOpen" x-cloak class="space-y-3 pt-2 border-t border-hairline">
            <textarea x-model="smartPasteText" rows="3" placeholder="Contoh teks chat:&#10;No Pesanan: 240915SHP88123, Email: budi@gmail.com, WA: 08123456789, Paket: Starter S, OS: Ubuntu 24.04"
                      class="{{ $inputClass }} font-mono"></textarea>
            <div class="flex justify-end">
                <button type="button" @click="parseSmartText()" class="px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono font-bold text-xs uppercase tracking-wider transition-colors cursor-pointer">
                    Ekstrak ke Formulir
                </button>
            </div>
        </div>
    </div>

    <!-- Form Section -->
    <div class="bg-white border border-hairline p-5 sm:p-6">
        <h3 class="font-display text-base font-bold text-ink pb-3 mb-4 border-b border-hairline">Form Transaksi Shopee</h3>

        <form action="{{ route('admin.shopee.process') }}" method="POST" class="space-y-5 text-xs">
            @csrf

            <!-- Section 1: Buyer Info -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="{{ $labelClass }}">Shopee Order ID *</label>
                    <input type="text" name="shopee_order_id" required x-model="shopeeOrderId" placeholder="Contoh: 240912SHP88123"
                           class="{{ $inputClass }} font-mono">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Nama Pembeli *</label>
                    <input type="text" name="customer_name" required x-model="customerName" placeholder="Budi Santoso"
                           class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Email Pembeli *</label>
                    <input type="email" name="customer_email" required x-model="customerEmail" placeholder="budi@gmail.com"
                           class="{{ $inputClass }} font-mono">
                </div>

                <div>
                    <label class="{{ $labelClass }}">No HP / WA (Opsional)</label>
                    <input type="text" name="customer_phone" x-model="customerPhone" placeholder="087812345678"
                           class="{{ $inputClass }} font-mono">
                </div>
            </div>

            <!-- Section 2: VPS Specs & Lokasi -->
            <div class="grid sm:grid-cols-3 gap-4 pt-4 border-t border-hairline">
                <div>
                    <label class="{{ $labelClass }}">Paket VPS *</label>
                    <select name="vps_spec_id" required x-model="vpsSpecId" @change="syncSpecSettings()" class="{{ $inputClass }}">
                        @foreach($specs as $spec)
                            <option value="{{ $spec->id }}">
                                {{ $spec->name }} ({{ $spec->cpu }}c/{{ $spec->ram }}G) &mdash; Rp {{ number_format($spec->sell_price, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Lokasi Datacenter *</label>
                    <select name="datacenter_location" required x-model="datacenterLocation" class="{{ $inputClass }}">
                        <option value="indonesia">Indonesia (Jakarta DCI-ID)</option>
                        <option value="singapore">Singapore</option>
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Sistem Operasi *</label>
                    <select name="os" required x-model="os" class="{{ $inputClass }} font-mono text-[11px]">
                        <template x-for="(label, key) in operatingSystems" :key="key">
                            <option :value="key" x-text="label"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- Section 2B: Panel & App Stack -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-hairline">
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">Control Panel / Aplikasi Stack *</label>
                    <select name="control_panel" required x-model="controlPanel" class="{{ $inputClass }}">
                        @foreach($stackLabels as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Hostname Kustom (Opsional)</label>
                    <input type="text" name="hostname" x-model="hostname" placeholder="vps-custom-name"
                           class="{{ $inputClass }} font-mono">
                </div>

                <div class="flex items-end">
                    <div class="p-2 text-muted font-mono text-[11px] w-full">
                        Cloud VPS KVM + 1-Click Stack
                    </div>
                </div>
            </div>

            <!-- Section 3: Mode Eksekusi Provisioning Shopee -->
            <div class="p-4 bg-creamDark/40 border border-hairline space-y-3">
                <div class="font-display font-bold text-ink text-xs uppercase tracking-wider">Mode Provisioning Shopee</div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-2.5 p-3 border border-hairline bg-white cursor-pointer"
                           :class="shopeeMode === 'auto' ? 'border-electric bg-cream' : ''">
                        <input type="radio" name="shopee_mode" value="auto" x-model="shopeeMode" @change="autoProvision = false" class="mt-0.5 accent-electric">
                        <span>
                            <strong class="block text-ink">Otomatis Tencent Cloud</strong>
                            <span class="text-[11px] text-muted">Direkomendasikan bila paket terpetakan &amp; auto-provisioning aktif. Tanpa input IP/password manual.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2.5 p-3 border border-hairline bg-white cursor-pointer"
                           :class="shopeeMode === 'manual' ? 'border-electric bg-cream' : ''">
                        <input type="radio" name="shopee_mode" value="manual" x-model="shopeeMode" class="mt-0.5 accent-electric">
                        <span>
                            <strong class="block text-ink">Input Manual (Darurat / Override)</strong>
                            <span class="text-[11px] text-muted">Alokasi IP manual langsung bila provisioning otomatis tidak digunakan.</span>
                        </span>
                    </label>
                </div>

                <div x-show="shopeeMode === 'manual'" x-cloak class="pt-2 border-t border-hairline space-y-3">
                    <label class="flex items-center gap-2.5 cursor-pointer font-semibold text-ink text-xs">
                        <input type="checkbox" name="auto_provision" value="1" x-model="autoProvision" class="w-4 h-4 border-ink accent-electric">
                        <span>Langsung alokasikan IP Publik dan aktifkan status Running sekarang</span>
                    </label>

                    <div x-show="autoProvision" x-cloak class="grid sm:grid-cols-3 gap-3 pt-2 border-t border-hairline">
                    <div>
                        <label class="{{ $labelClass }}">Alokasi IP Publik Node *</label>
                        <input type="text" name="public_ip" x-model="publicIp" placeholder="Contoh: 103.150.12.88"
                               class="{{ $inputClass }} font-mono">
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">Port SSH / RDP (Default 22)</label>
                        <input type="number" name="ssh_port" x-model="sshPort" placeholder="22"
                               class="{{ $inputClass }} font-mono">
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">Username SSH (Default ubuntu)</label>
                        <input type="text" name="ssh_username" x-model="sshUsername" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false"
                               class="{{ $inputClass }} font-mono">
                    </div>

                    <div>
                        <label class="{{ $labelClass }}">Password SSH Kustom (Opsional)</label>
                        <input type="text" name="root_password" x-model="rootPassword" placeholder="Biarkan kosong untuk auto-generate"
                               class="{{ $inputClass }} font-mono">
                    </div>

                    <div class="sm:col-span-3" x-show="controlPanel !== 'none'">
                        <label class="{{ $labelClass }}">Link Control Panel (Opsional)</label>
                        <input type="text" name="app_url" value="{{ old('app_url') }}" placeholder="http://103.150.12.88:8000 atau https://panel.domain.com"
                               class="{{ $inputClass }} font-mono">
                        <p class="mt-1 text-muted">Link yang tampil di dashboard pelanggan. Bisa diisi nanti di Admin &gt; Cloud Instances.</p>
                    </div>
                </div>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Proses Order Shopee
                </button>
            </div>
        </form>
    </div>

    <!-- Recent Shopee Orders Table -->
    <div class="bg-white border border-hairline overflow-hidden">
        <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-hairline">
            <div>
                <h3 class="font-display font-bold text-ink text-base">Riwayat Pesanan Shopee</h3>
                <p class="text-xs text-muted mt-0.5">Daftar transaksi marketplace yang telah masuk ke sistem</p>
            </div>

            <!-- Search and Status Filter -->
            <form action="{{ route('admin.shopee') }}" method="GET" class="flex flex-wrap items-center gap-2">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari Order ID, Nama, IP..."
                       class="px-3 py-1.5 border border-hairline text-xs font-mono bg-white text-ink focus:outline-none focus:border-electric w-48 sm:w-60">

                <select name="status" class="px-3 py-1.5 border border-hairline text-xs font-mono focus:outline-none focus:border-electric bg-white text-ink">
                    <option value="">Semua Status</option>
                    <option value="needs_provision" {{ request('status') === 'needs_provision' ? 'selected' : '' }}>Menunggu IP (Paid)</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif Running</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                </select>

                <button type="submit" class="px-3.5 py-1.5 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase transition-colors cursor-pointer">
                    Filter
                </button>

                @if(request('q') || request('status'))
                    <a href="{{ route('admin.shopee') }}" class="px-2.5 py-1.5 border border-hairline hover:border-ink text-ink font-mono text-xs">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-creamDark/60 text-muted font-mono text-[10px] font-bold uppercase tracking-widest border-b border-hairline">
                    <tr>
                        <th class="px-5 py-3">No. Pesanan Shopee</th>
                        <th class="px-5 py-3">Pelanggan</th>
                        <th class="px-5 py-3">Paket &amp; Lokasi</th>
                        <th class="px-5 py-3">IP Node / Hostname</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Tanggal</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse($recentShopeeOrders as $so)
                        @php
                            $instance = $so->vpsInstance;
                            $cleanRowPhone = !empty($so->customer->phone) ? preg_replace('/[^0-9]/', '', $so->customer->phone) : '';
                            if ($cleanRowPhone && str_starts_with($cleanRowPhone, '0')) {
                                $cleanRowPhone = '62' . substr($cleanRowPhone, 1);
                            }
                            $rowPayload = [
                                'id' => $so->id,
                                'shopee_order_id' => $so->shopee_order_id,
                                'customer_name' => $so->customer->full_name ?? 'Pelanggan Shopee',
                                'customer_email' => $so->customer->email ?? '',
                                'customer_phone' => $cleanRowPhone,
                                'username' => $so->customer->username ?? '',
                                'package' => $so->vpsSpec->name ?? 'Cloud VPS',
                                'provider' => $so->provider_label,
                                'datacenter' => ucfirst($so->datacenter_location),
                                'os' => $so->os_label,
                                'os_key' => $so->os,
                                'control_panel' => $so->control_panel_label,
                                'needs_panel_url' => $so->control_panel !== 'none',
                                'status' => $so->status,
                                'has_instance' => (bool)$instance,
                                'hostname' => $instance?->hostname ?? ($so->hostname ?? ('vps-' . ($so->customer->username ?? 'node'))),
                                'public_ip' => $instance?->public_ip ?? '',
                                'ssh_port' => $instance?->ssh_port ?? 22,
                                'ssh_username' => $instance?->ssh_user ?? 'root',
                                'login_url' => route('login'),
                            ];
                        @endphp
                        <tr class="hover:bg-creamDark/30 transition-colors">
                            <td class="px-5 py-3.5 font-mono font-bold text-ink">
                                {{ $so->shopee_order_id }}
                                <span class="block text-[11px] text-muted font-mono font-normal">#ORD-{{ $so->id }}</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-semibold text-ink block">{{ $so->customer->full_name }}</span>
                                <span class="text-muted font-mono text-[11px]">&#64;{{ $so->customer->username }}</span>
                                @if($so->customer->phone)
                                    <span class="text-muted font-mono text-[11px] block">{{ $so->customer->phone }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-medium text-ink block">{{ $so->vpsSpec->name ?? 'Standard' }}</span>
                                <span class="text-muted text-[11px] block">Datacenter: {{ ucfirst($so->datacenter_location) }}</span>
                                <span class="text-muted font-mono text-[10px] block">{{ $so->os_label }}</span>
                            </td>
                            <td class="px-5 py-3.5 font-mono">
                                @if($instance && $instance->public_ip)
                                    <span class="font-bold text-ink block">{{ $instance->public_ip }}</span>
                                    <span class="text-muted text-[11px] block">{{ $instance->hostname }}</span>
                                @else
                                    <span class="text-muted italic">Belum dialokasikan</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                @if($so->status === 'paid' && !$instance)
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-ink bg-creamDark text-ink">
                                        Menunggu IP
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase border border-hairline bg-white text-ink">
                                        {{ $so->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-muted font-mono">
                                {{ $so->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if(in_array($so->status, ['paid', 'failed']) && !$instance)
                                        <button type="button" @click="openProvisionModal({{ json_encode($rowPayload) }})"
                                                class="px-2.5 py-1.5 bg-electric hover:bg-electricHover text-white font-mono font-bold text-[10px] uppercase transition-colors cursor-pointer">
                                            Provision
                                        </button>
                                    @endif

                                    <button type="button" @click="openCopyModal({{ json_encode($rowPayload) }})"
                                            class="px-2.5 py-1.5 border border-hairline bg-white hover:border-ink text-ink font-mono font-bold text-[10px] uppercase transition-colors cursor-pointer">
                                        Salin Pesan
                                    </button>

                                    @if($so->invoice)
                                        <a href="{{ route('dashboard.invoice.print', $so->invoice->id) }}"
                                           @click.prevent="$dispatch('open-invoice-modal', { url: '{{ route('dashboard.invoice.print', $so->invoice->id) }}' })"
                                           class="px-2 py-1.5 text-electric font-mono font-bold text-[11px] hover:underline cursor-pointer">
                                            Faktur
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center font-mono text-xs text-muted">
                                Belum ada riwayat pesanan Shopee pada kriteria ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($recentShopeeOrders->hasPages())
            <div class="p-4 border-t border-hairline">
                {{ $recentShopeeOrders->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: Salin Pesan Riwayat (Multi-Template) -->
    <div x-show="copyModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/50" style="display: none;">
        <div class="bg-white border border-ink p-6 max-w-xl w-full space-y-4 shadow-xl" @click.away="copyModalOpen = false">
            <div class="flex items-center justify-between border-b border-hairline pb-3">
                <div>
                    <h3 class="font-display text-base font-bold text-ink">Salin Template Pesan Pembeli</h3>
                    <p class="font-mono text-xs text-muted" x-text="copyModalData ? 'No. Pesanan: ' + copyModalData.shopee_order_id + ' (' + copyModalData.customer_name + ')' : ''"></p>
                </div>
                <button type="button" @click="copyModalOpen = false" class="text-muted hover:text-ink font-mono text-xs font-bold uppercase cursor-pointer">Tutup</button>
            </div>

            <div class="flex items-center gap-2 border-b border-hairline font-mono text-xs">
                <button type="button" @click="copyModalTab = 'ready'"
                        :class="copyModalTab === 'ready' ? 'border-b-2 border-electric font-bold text-ink' : 'text-muted hover:text-ink'"
                        class="px-2.5 py-2 transition-colors cursor-pointer">
                    Akses Dashboard &amp; Server
                </button>
                <button type="button" @click="copyModalTab = 'queued'"
                        :class="copyModalTab === 'queued' ? 'border-b-2 border-electric font-bold text-ink' : 'text-muted hover:text-ink'"
                        class="px-2.5 py-2 transition-colors cursor-pointer">
                    Server Sedang Disiapkan
                </button>
                <button type="button" @click="copyModalTab = 'review'"
                        :class="copyModalTab === 'review' ? 'border-b-2 border-electric font-bold text-ink' : 'text-muted hover:text-ink'"
                        class="px-2.5 py-2 transition-colors cursor-pointer">
                    Ulasan &amp; Konfirmasi
                </button>
            </div>

            <div x-show="copyModalTab === 'ready'" class="bg-ink text-cream p-4 font-mono text-xs leading-relaxed select-all whitespace-pre-wrap" id="modal-tmpl-ready">Halo Kak <span x-text="copyModalData?.customer_name"></span>!
Terima kasih telah memesan Cloud VPS di CravaCloud (No. Pesanan: <span x-text="copyModalData?.shopee_order_id"></span>).

Berikut detail akun dan akses dashboard VPS Anda:
--------------------------------------------------
URL Dashboard : <span x-text="copyModalData?.login_url"></span>
Username      : <span x-text="copyModalData?.username"></span>
Password      : (Gunakan password akun CravaCloud Anda)
Paket VPS     : <span x-text="copyModalData?.package"></span>
Datacenter    : <span x-text="copyModalData?.datacenter"></span>
Sistem Operasi: <span x-text="copyModalData?.os"></span>
Control Panel : <span x-text="copyModalData?.control_panel"></span>
Hostname      : <span x-text="copyModalData?.hostname"></span>
IP Publik     : <span x-text="copyModalData?.public_ip || '(Dalam proses alokasi)'"></span>
Port Akses    : <span x-text="copyModalData?.ssh_port || '22'"></span>
User SSH      : <span x-text="copyModalData?.ssh_username || 'root'"></span>
--------------------------------------------------

Silakan login ke dashboard untuk mengecek status dan mengelola server Anda.
Jika butuh panduan setup atau konfigurasi, tim kami siap membantu melalui tiket support di dashboard.

Salam hangat,
Tim CravaCloud</div>

            <div x-show="copyModalTab === 'queued'" style="display: none;" class="bg-ink text-cream p-4 font-mono text-xs leading-relaxed select-all whitespace-pre-wrap" id="modal-tmpl-queued">Halo Kak <span x-text="copyModalData?.customer_name"></span>!
Terima kasih telah memesan Cloud VPS di CravaCloud (No. Pesanan: <span x-text="copyModalData?.shopee_order_id"></span>).

Pesanan Anda telah kami terima dan server saat ini sedang dalam proses antrean konfigurasi datacenter (maksimal 1x24 jam, umumnya lebih cepat).

Detail akses akun dashboard Anda:
--------------------------------------------------
URL Dashboard : <span x-text="copyModalData?.login_url"></span>
Username      : <span x-text="copyModalData?.username"></span>
Password      : (Gunakan password akun CravaCloud Anda)
Paket VPS     : <span x-text="copyModalData?.package"></span>
Datacenter    : <span x-text="copyModalData?.datacenter"></span>
Sistem Operasi: <span x-text="copyModalData?.os"></span>
--------------------------------------------------

Setelah proses alokasi selesai, IP publik dan kredensial root server akan otomatis tampil di dashboard Anda.
Mohon ditunggu sebentar ya Kak. Terima kasih atas kesabaran Anda.

Salam hangat,
Tim CravaCloud</div>

            <div x-show="copyModalTab === 'review'" style="display: none;" class="bg-ink text-cream p-4 font-mono text-xs leading-relaxed select-all whitespace-pre-wrap" id="modal-tmpl-review">Halo Kak <span x-text="copyModalData?.customer_name"></span>!
Server Cloud VPS untuk No. Pesanan Shopee <span x-text="copyModalData?.shopee_order_id"></span> telah aktif dan berjalan normal.

Jika Anda telah selesai memeriksa dan mencoba akses server, kami mohon bantuannya untuk:
1. Membuka aplikasi Shopee dan menekan tombol "Pesanan Diterima".
2. Memberikan ulasan bintang 5 untuk mengaktifkan garansi pergantian dan dukungan bantuan prioritas selama 30 hari penuh.

Jika ada kendala teknis atau pertanyaan, silakan hubungi kami kapan saja melalui tiket bantuan dashboard.
Terima kasih telah mempercayakan kebutuhan server Anda di CravaCloud!</div>

            <div class="flex items-center justify-between pt-2 border-t border-hairline">
                <template x-if="copyModalData?.customer_phone">
                    <a :href="'https://wa.me/' + copyModalData.customer_phone + '?text=' + encodeURIComponent(document.getElementById('modal-tmpl-' + copyModalTab).innerText)"
                       target="_blank"
                       class="px-3.5 py-2 border border-hairline bg-white hover:border-ink text-ink font-mono font-bold text-xs uppercase transition-colors flex items-center gap-1.5">
                        <span>Kirim via WhatsApp</span>
                    </a>
                </template>
                <div class="flex-1" x-show="!copyModalData?.customer_phone"></div>

                <button type="button" @click="copyText('modal-tmpl-' + copyModalTab)" class="px-4 py-2 bg-electric hover:bg-electricHover text-white font-mono font-bold text-xs uppercase transition-colors cursor-pointer">
                    Salin Pesan
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Provision Cepat -->
    <div x-show="provisionModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/50" style="display: none;">
        <div class="bg-white border border-ink p-6 max-w-md w-full shadow-xl" @click.away="provisionModalOpen = false">
            <h3 class="font-display text-base font-bold text-ink mb-1">Provisioning Cepat Server Shopee</h3>
            <p class="text-xs text-muted mb-3">
                Alokasikan IP Publik dan hostname untuk pesanan <strong class="font-mono text-ink" x-text="provisionModalData ? provisionModalData.shopee_order_id : ''"></strong>.
            </p>

            <div x-show="provisionModalData" class="mb-4 p-3.5 bg-creamDark/40 border border-hairline text-xs space-y-1">
                <div class="flex justify-between"><span class="text-muted">Pelanggan:</span><span class="font-semibold text-ink" x-text="provisionModalData?.customer_name"></span></div>
                <div class="flex justify-between"><span class="text-muted">Paket &amp; Datacenter:</span><span class="font-semibold text-ink" x-text="(provisionModalData?.package || '') + ' · ' + (provisionModalData?.datacenter || '')"></span></div>
                <div class="flex justify-between"><span class="text-muted">Stack:</span><span class="font-semibold text-ink" x-text="provisionModalData?.control_panel"></span></div>
            </div>

            <form :action="provisionModalData ? '/admin/orders/' + provisionModalData.id + '/provision' : '#'" method="POST" class="space-y-3.5 text-xs">
                @csrf

                <div>
                    <label class="{{ $labelClass }}">Alokasi IP Publik Node *</label>
                    <input type="text" name="public_ip" required x-model="provPublicIp" placeholder="103.150.12.88 atau 139.180.xxx.xxx"
                           class="{{ $inputClass }} font-mono">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Hostname Server *</label>
                    <input type="text" name="hostname" required x-model="provHostname" placeholder="vps-customer-node"
                           class="{{ $inputClass }} font-mono">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Sistem Operasi *</label>
                    <input type="text" name="os" required x-model="provOs"
                           class="{{ $inputClass }} font-mono">
                </div>

                <div x-show="provisionModalData?.needs_panel_url">
                    <label class="{{ $labelClass }}">Link Control Panel *</label>
                    <input type="text" name="app_url" x-model="provAppUrl" :required="!!provisionModalData?.needs_panel_url" :disabled="!provisionModalData?.needs_panel_url"
                           placeholder="http://103.150.12.88:8000 atau https://panel.domain.com"
                           class="{{ $inputClass }} font-mono">
                    <p class="mt-1 text-muted">Link ini yang tampil di dashboard pelanggan. Wajib diawali http:// atau https://.</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $labelClass }}">Port SSH/RDP</label>
                        <input type="number" name="ssh_port" x-model="provSshPort" placeholder="22"
                               class="{{ $inputClass }} font-mono">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Username SSH</label>
                        <input type="text" name="ssh_username" x-model="provSshUsername" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false"
                               class="{{ $inputClass }} font-mono">
                    </div>
                </div>

                <div>
                    <label class="{{ $labelClass }}">Password SSH <span class="text-electric">*</span></label>
                    <input type="text" name="root_password" required minlength="8" x-model="provRootPassword" placeholder="Password dari retail"
                           class="{{ $inputClass }} font-mono">
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
                    <button type="button" @click="provisionModalOpen = false" class="px-4 py-2 border border-hairline bg-white hover:border-ink text-ink font-mono text-xs font-bold uppercase cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Mulai Provisioning
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection