@extends('layouts.app', ['title' => 'CravaCloud Pesanan Berhasil', 'robots' => 'noindex, follow'])

@section('content')
@php
    $isPaid = (bool) ($order->paid_at || in_array($order->status, ['paid', 'provisioning', 'active'], true) || ($order->invoice && $order->invoice->status === 'paid'));
@endphp
<div class="py-12 sm:py-16 bg-cream min-h-screen">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white border-2 border-ink p-6 sm:p-10 text-center">

            @if($order->status === 'active' && $order->vpsInstance)
                <div class="w-12 h-12 bg-emerald-600 text-white flex items-center justify-center mx-auto mb-5">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                </div>

                <span class="font-mono text-[10px] uppercase tracking-widest text-emerald-700 font-bold block mb-1">PROVISIONING SELESAI</span>
                <h1 class="font-display text-2xl sm:text-3xl font-bold text-ink tracking-tight mb-2">VPS Anda Sudah Aktif</h1>
                <p class="text-xs sm:text-sm text-muted mb-8 max-w-md mx-auto">
                    Cloud VPS Anda telah berhasil di-setup dan siap digunakan. Kelola server dan kredensial Anda melalui dashboard klien.
                </p>
            @else
                <div class="w-12 h-12 bg-ink text-cream flex items-center justify-center mx-auto mb-5">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>

                <span class="font-mono text-[10px] uppercase tracking-widest text-electric font-bold block mb-1">STATUS PESANAN CLOUD</span>
                <h1 class="font-display text-2xl sm:text-3xl font-bold text-ink tracking-tight mb-2">Pesanan Anda Sedang Diproses</h1>
                <p class="text-xs sm:text-sm text-muted mb-8 max-w-md mx-auto leading-relaxed">
                    @if($isPaid)
                        Pembayaran telah dikonfirmasi lunas. Tim teknis kami sedang melakukan setup server Anda. Kredensial server akan dikirimkan ke email Anda.
                    @else
                        Pembayaran Anda telah diterima dan masuk dalam antrean verifikasi. Server VPS segera disiapkan setelah terkonfirmasi.
                    @endif
                </p>

                {{-- Editorial Stepper --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-hairline border border-hairline mb-8 text-left">
                    <div class="p-3 bg-white">
                        <span class="font-mono text-[10px] font-bold text-electric block">01 // SELESAI</span>
                        <span class="font-display text-xs font-bold text-ink">Pesanan Dibuat</span>
                    </div>
                    <div class="p-3 bg-white">
                        <span class="font-mono text-[10px] font-bold text-electric block">02 // SELESAI</span>
                        <span class="font-display text-xs font-bold text-ink">Pembayaran Diterima</span>
                    </div>
                    <div class="p-3 {{ $isPaid ? 'bg-white' : 'bg-creamDark/40' }}">
                        <span class="font-mono text-[10px] font-bold {{ $isPaid ? 'text-electric' : 'text-muted' }} block">03 // {{ $isPaid ? 'PROSES' : 'ANTRIAN' }}</span>
                        <span class="font-display text-xs font-bold {{ $isPaid ? 'text-ink' : 'text-muted' }}">Setup Server</span>
                    </div>
                    <div class="p-3 bg-creamDark/40">
                        <span class="font-mono text-[10px] font-bold text-muted block">04 // MENUNGGU</span>
                        <span class="font-display text-xs font-bold text-muted">Server Aktif</span>
                    </div>
                </div>
            @endif

            {{-- Order Details Box --}}
            <div class="bg-creamDark/40 border border-hairline p-5 sm:p-6 text-left mb-8 space-y-5 text-xs">
                <div class="flex flex-wrap justify-between items-center gap-3 pb-3 border-b border-hairline">
                    <div>
                        <span class="font-mono text-[10px] uppercase tracking-wider text-muted block">Nomor Invoice</span>
                        <span class="font-mono font-bold text-ink text-base">{{ $order->invoice->invoice_number ?? 'INV-' . $order->id }}</span>
                    </div>
                    <span class="px-3 py-1 font-mono text-[11px] font-bold uppercase
                        {{ $isPaid ? 'bg-emerald-700 text-white' : 'bg-ink text-cream' }}">
                        {{ $isPaid ? 'LUNAS' : 'DIPROSES (VERIFIKASI)' }}
                    </span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">VPS Name</span>
                        <span class="font-mono font-bold text-ink">{{ $order->hostname ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Paket VPS</span>
                        <span class="font-display font-bold text-ink">{{ $order->vpsSpec->name ?? 'Cloud VPS' }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Infrastruktur</span>
                        <span class="font-semibold text-ink">{{ $order->provider_label }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Datacenter &amp; OS</span>
                        <span class="font-semibold text-ink capitalize">{{ $order->datacenter_location }} · {{ $order->os_label }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-hairline">
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Stack Server</span>
                        <span class="font-semibold text-ink">{{ $order->control_panel_label }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Spesifikasi</span>
                        <span class="font-mono font-semibold text-ink">
                            {{ $order->vpsSpec->cpu ?? '-' }} vCPU · {{ $order->vpsSpec->ram ?? '-' }} GB · {{ $order->vpsSpec->disk ?? '-' }} GB
                        </span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Siklus Tagihan</span>
                        <span class="font-semibold text-ink">
                            @if($order->billing_cycle === 'annual') 12 Bulan (1 Tahun)
                            @elseif($order->billing_cycle === 'semi_annual') 6 Bulan
                            @elseif($order->billing_cycle === 'quarterly') 3 Bulan
                            @else 1 Bulan @endif
                        </span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Total Tagihan</span>
                        <span class="font-bold text-electric font-mono text-sm">Rp {{ number_format($order->amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                {{-- VPS Credentials when active --}}
                @if($order->vpsInstance && $order->status === 'active')
                    @php $vps = $order->vpsInstance; @endphp
                    <div class="bg-white p-5 border border-ink space-y-4" x-data="{
                        copiedSsh: false,
                        sshCmd: '{{ $vps->ssh_command }}',
                        copyText(text, type) {
                            navigator.clipboard.writeText(text);
                            if (type === 'ssh') { this.copiedSsh = true; setTimeout(() => this.copiedSsh = false, 2000); }
                        }
                    }">
                        <div class="flex items-center justify-between border-b border-hairline pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <p class="font-mono font-bold text-ink text-xs uppercase tracking-wider">Informasi Akses Server</p>
                            </div>
                            @php $badge = $vps->statusBadge; @endphp
                            <span class="font-mono text-[10px] font-bold uppercase px-2 py-0.5 border border-hairline bg-creamDark text-ink">
                                {{ $badge['label'] }}
                            </span>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Hostname</span>
                                <span class="font-mono font-bold text-ink">{{ $vps->hostname }}</span>
                            </div>
                            <div>
                                <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">IP Publik &amp; Port</span>
                                <span class="font-mono font-bold text-ink">{{ $vps->public_ip }}:{{ $vps->ssh_port ?? 22 }}</span>
                            </div>
                            <div>
                                <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Sistem Operasi</span>
                                <span class="font-semibold text-ink">{{ $vps->os }}</span>
                            </div>
                            <div>
                                <span class="font-mono text-[10px] uppercase text-muted block mb-0.5">Masa Berlaku</span>
                                <span class="font-semibold text-ink">
                                    {{ $vps->expires_at ? $vps->expires_at->timezone('Asia/Jakarta')->format('d M Y') : '—' }}
                                </span>
                            </div>
                        </div>

                        <div class="p-3.5 bg-ink text-white space-y-1.5">
                            <div class="flex items-center justify-between font-mono text-[10px] text-white/60 uppercase">
                                <span>Perintah Terminal SSH</span>
                                <button type="button" @click="copyText(sshCmd, 'ssh')" class="text-white hover:text-electric font-bold cursor-pointer">
                                    <span x-text="copiedSsh ? 'TERSALIN!' : 'SALIN PERINTAH'"></span>
                                </button>
                            </div>
                            <div class="font-mono text-xs text-emerald-400 select-all">
                                {{ $vps->ssh_command }}
                            </div>
                        </div>

                        <div class="p-3 bg-amber-50 border border-amber-400 text-xs text-amber-950">
                            <span class="font-bold">Catatan:</span> Username &amp; password SSH dapat dilihat di menu detail VPS pada Dashboard (tab Akses, klik ikon mata).
                        </div>
                    </div>
                @elseif($isPaid)
                    <div class="bg-white p-5 border border-electric text-center">
                        <p class="font-display text-sm font-bold text-electric">Server Sedang Di-Setup</p>
                        <p class="text-xs text-muted mt-1 leading-relaxed">Pembayaran telah dikonfirmasi lunas. Sistem sedang mempersiapkan VPS Anda secara otomatis. Kredensial akses tersedia aman di Dashboard setelah setup selesai dan pemberitahuan dikirimkan langsung ke email Anda.</p>
                    </div>
                @else
                    <div class="bg-amber-50 p-5 border border-amber-500 text-center">
                        <p class="font-display text-sm font-bold text-amber-950">Menunggu Pembayaran</p>
                        <p class="text-xs text-amber-900 mt-1">Silakan selesaikan pembayaran sesuai metode yang dipilih. Setelah pembayaran terkonfirmasi, VPS Anda segera disiapkan.</p>
                        @if($order->invoice && $order->invoice->due_at)
                            <p class="font-mono text-xs text-amber-950 mt-2 font-bold">Batas waktu pembayaran: {{ $order->invoice->due_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="{{ route('dashboard.index', $isPaid ? ['payment_success' => 1, 'order_id' => $order->id] : []) }}"
                   class="w-full sm:w-auto rounded-none px-7 py-3.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                    Buka Dashboard
                </a>
                @if($order->invoice)
                    <a href="{{ route('dashboard.invoice.print', $order->invoice->id) }}"
                       @click.prevent="$dispatch('open-invoice-modal', { url: '{{ route('dashboard.invoice.print', $order->invoice->id) }}' })"
                       class="w-full sm:w-auto rounded-none px-6 py-3.5 bg-white border border-ink hover:bg-creamDark text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                        Cetak Invoice
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
