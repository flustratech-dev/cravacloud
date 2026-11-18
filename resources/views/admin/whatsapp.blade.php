@extends('layouts.admin', [
    'title' => 'WhatsApp Gateway',
    'headerTitle' => 'WhatsApp Gateway Management',
    'backUrl' => route('admin.index'),
    'backLabel' => 'Kembali ke Dashboard Admin'
])

@section('content')
<div class="space-y-6">

    @php
        $inputClass = 'w-full px-3 py-2 border border-hairline text-xs text-ink bg-white focus:outline-none focus:border-electric';
        $labelClass = 'block font-mono text-[10px] font-bold text-muted uppercase tracking-widest mb-1.5';
    @endphp

    <!-- 1. KARTU STATUS KONEKSI -->
    <div class="bg-white border border-hairline p-5 sm:p-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-display text-base font-bold text-ink">Status Koneksi WhatsApp Gateway</h2>
                <p class="text-xs text-muted mt-0.5">
                    Notifikasi pelanggan dikirim melalui CravaCloud WA Gateway. Penautan nomor, QR code, dan riwayat pengiriman dikelola di dashboard gateway.
                </p>
            </div>
            <span id="wa-badge" class="inline-flex items-center gap-1.5 px-3 py-1 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">
                <span class="w-2 h-2 bg-muted animate-pulse"></span>
                Memeriksa koneksi&hellip;
            </span>
        </div>

        <div id="wa-message" class="hidden text-xs px-3.5 py-2.5 bg-creamDark border border-ink text-ink font-mono"></div>

        <!-- Statistik Koneksi (Tampil Saat Terhubung) -->
        <div id="wa-stats" class="hidden grid grid-cols-2 md:grid-cols-4 gap-px bg-hairline border border-hairline">
            <div class="p-3.5 bg-white">
                <div class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Sesi Terhubung</div>
                <div class="font-display text-xl font-bold text-ink mt-1" id="wa-sessions">&mdash;</div>
            </div>
            <div class="p-3.5 bg-white">
                <div class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Terkirim Bulan Ini</div>
                <div class="font-display text-xl font-bold text-electric mt-1" id="wa-sent">&mdash;</div>
            </div>
            <div class="p-3.5 bg-white">
                <div class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Gagal Bulan Ini</div>
                <div class="font-display text-xl font-bold text-ink mt-1" id="wa-failed">&mdash;</div>
            </div>
            <div class="p-3.5 bg-white">
                <div class="font-mono text-[10px] font-bold text-muted uppercase tracking-widest">Workspace</div>
                <div class="font-mono text-sm font-bold text-ink mt-1 truncate" id="wa-workspace">&mdash;</div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-2">
            <a href="{{ $gatewayUrl }}/sessions" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                <span>Kelola Nomor &amp; Scan QR</span>
            </a>
            <a href="{{ $gatewayUrl }}/messages" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-hairline hover:border-ink text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Riwayat Pesan Gateway</span>
            </a>
            <button type="button" id="wa-reload"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-hairline hover:border-ink text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Muat Ulang Status</span>
            </button>
        </div>
    </div>

    <!-- 2. KARTU KREDENSIAL GATEWAY -->
    <div class="bg-white border border-hairline p-5 sm:p-6 space-y-5">
        <div>
            <h2 class="font-display text-base font-bold text-ink">Kredensial &amp; Konfigurasi Gateway</h2>
            <p class="text-xs text-muted mt-0.5">
                Pengaturan ini disimpan di basis data CravaCloud sehingga Anda dapat mengubahnya kapan saja tanpa perlu menyunting file <code class="font-mono bg-creamDark px-1">.env</code> atau me-restart web server.
            </p>
        </div>

        <form action="{{ route('admin.whatsapp.settings') }}" method="POST" class="space-y-4">
            @csrf

            <!-- Toggle Enable -->
            <div class="p-4 bg-creamDark/40 border border-hairline flex items-center justify-between gap-4">
                <div>
                    <label for="wa_enabled" class="font-display text-sm font-bold text-ink cursor-pointer">
                        Aktifkan Pengiriman WhatsApp
                    </label>
                    <p class="text-xs text-muted mt-0.5">
                        Bila dimatikan, semua pengiriman notifikasi WhatsApp dilewati secara aman. Notifikasi email tetap berjalan normal.
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" name="wa_enabled" id="wa_enabled" value="1" class="sr-only peer"
                           @checked(old('wa_enabled', $pengaturan['wa_enabled']['value'] === '1'))>
                    <div class="w-11 h-6 bg-hairline border border-ink peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:border after:border-ink after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-electric"></div>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- URL Gateway -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="wa_url" class="{{ $labelClass }} mb-0">URL Gateway</label>
                        @if($pengaturan['wa_url']['from_database'])
                            <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">Database</span>
                        @endif
                    </div>
                    <input type="url" name="wa_url" id="wa_url"
                           value="{{ old('wa_url', $pengaturan['wa_url']['value']) }}"
                           placeholder="https://wa.cravacloud.id"
                           class="{{ $inputClass }} font-mono">
                    <p class="text-[11px] text-muted mt-1">Alamat endpoint server CravaCloud WA Gateway.</p>
                </div>

                <!-- API Key Gateway -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="wa_key" class="{{ $labelClass }} mb-0">API Key Gateway</label>
                        @if($pengaturan['wa_key']['from_database'])
                            <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">Terenkripsi</span>
                        @endif
                    </div>
                    <input type="password" name="wa_key" id="wa_key"
                           autocomplete="new-password"
                           placeholder="{{ $pengaturan['wa_key']['masked'] ?: 'Masukkan API Key Gateway' }}"
                           class="{{ $inputClass }} font-mono">
                    <p class="text-[11px] text-muted mt-1">
                        @if($pengaturan['wa_key']['masked'])
                            Saat ini terpasang: <code class="font-mono bg-creamDark px-1 text-ink">{{ $pengaturan['wa_key']['masked'] }}</code>. Kosongkan jika tidak ingin mengubah.
                        @else
                            Dapatkan API Key dari dashboard WhatsApp Gateway pada workspace CravaCloud.
                        @endif
                    </p>
                </div>

                <!-- Session ID -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="wa_session" class="{{ $labelClass }} mb-0">Session ID <span class="text-muted font-normal lowercase">(opsional)</span></label>
                        @if($pengaturan['wa_session']['from_database'])
                            <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">Database</span>
                        @endif
                    </div>
                    <input type="text" name="wa_session" id="wa_session"
                           value="{{ old('wa_session', $pengaturan['wa_session']['value']) }}"
                           placeholder="Kosongkan untuk memakai sesi pertama terhubung"
                           class="{{ $inputClass }} font-mono">
                    <p class="text-[11px] text-muted mt-1">Kosongkan agar gateway otomatis memakai sesi WhatsApp aktif pertama.</p>
                </div>

                <!-- Request Timeout -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="wa_timeout" class="{{ $labelClass }} mb-0">Batas Waktu Request (Detik)</label>
                        @if($pengaturan['wa_timeout']['from_database'])
                            <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">Database</span>
                        @endif
                    </div>
                    <input type="number" name="wa_timeout" id="wa_timeout" min="1" max="60"
                           value="{{ old('wa_timeout', $pengaturan['wa_timeout']['value'] ?: 10) }}"
                           class="{{ $inputClass }} font-mono">
                    <p class="text-[11px] text-muted mt-1">Waktu tunggu HTTP maksimal sebelum request dilewati tanpa menahan proses.</p>
                </div>
            </div>

            <div class="pt-3 border-t border-hairline flex items-center justify-between">
                <button type="submit"
                        class="px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Simpan Pengaturan Kredensial
                </button>
            </div>
        </form>

        @php
            $tersimpanDiDb = collect($pengaturan)->filter(fn ($k) => $k['from_database'])->keys();
        @endphp

        @if($tersimpanDiDb->isNotEmpty())
            <div class="pt-4 border-t border-hairline">
                <p class="text-xs text-muted mb-2">
                    Kolom berikut tersimpan di database dan menimpa konfigurasi bawaan <code class="font-mono bg-creamDark px-1">.env</code>. Klik tombol untuk mengembalikannya ke nilai default:
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach($tersimpanDiDb as $kolom)
                        <form action="{{ route('admin.whatsapp.settings.forget') }}" method="POST"
                              onsubmit="return confirm('Kembalikan {{ $kolom }} ke nilai default .env?');">
                            @csrf
                            <input type="hidden" name="field" value="{{ $kolom }}">
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-creamDark hover:border-ink border border-hairline text-ink text-xs font-mono font-medium transition-colors cursor-pointer">
                                <span>{{ $kolom }}</span>
                                <span class="text-muted hover:text-ink font-bold">&times;</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- 3. KARTU UJI KIRIM PESAN -->
    <div class="bg-white border border-hairline p-5 sm:p-6 space-y-4">
        <div>
            <h2 class="font-display text-base font-bold text-ink">Uji Kirim Pesan WhatsApp</h2>
            <p class="text-xs text-muted mt-0.5">
                Kirim pesan uji coba ke nomor Anda sendiri untuk memastikan koneksi antara CravaCloud, gateway, dan WhatsApp berfungsi normal.
            </p>
        </div>

        <form id="wa-test-form" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="wa-test-phone" class="{{ $labelClass }}">Nomor WhatsApp Tujuan</label>
                    <input type="text" id="wa-test-phone" placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx"
                           class="{{ $inputClass }} font-mono" required>
                    <p class="text-[11px] text-muted mt-1">Format: 08xx, 62xx, atau +62xx (otomatis dinormalisasi).</p>
                </div>
                <div class="md:col-span-2">
                    <label for="wa-test-message" class="{{ $labelClass }}">Isi Pesan Uji</label>
                    <textarea id="wa-test-message" rows="2" class="{{ $inputClass }}" required>Halo, ini adalah pesan uji koneksi WhatsApp Gateway resmi dari CravaCloud.</textarea>
                </div>
            </div>

            <button type="submit" id="wa-test-submit"
                    class="px-5 py-2.5 bg-ink hover:bg-electric text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors disabled:opacity-50 cursor-pointer">
                Kirim Pesan Uji
            </button>

            <div id="wa-test-result" class="hidden p-3 border text-xs"></div>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const urlStatus = @json(route('admin.whatsapp.status'));
    const urlTest = @json(route('admin.whatsapp.test'));
    const csrfToken = @json(csrf_token());

    const badge = document.getElementById('wa-badge');
    const messageBox = document.getElementById('wa-message');
    const statsBox = document.getElementById('wa-stats');
    const testResult = document.getElementById('wa-test-result');

    const statusMap = {
        ready: {
            text: 'Terhubung & Aktif',
            classes: 'bg-electric text-white border-electric',
            dot: 'bg-white'
        },
        disconnected: {
            text: 'Belum Ada Nomor Tertaut',
            classes: 'bg-creamDark text-ink border-ink',
            dot: 'bg-ink'
        },
        not_configured: {
            text: 'Belum Dikonfigurasi',
            classes: 'bg-creamDark text-muted border-hairline',
            dot: 'bg-muted'
        },
        offline: {
            text: 'Gateway Tidak Terjangkau',
            classes: 'bg-ink text-cream border-ink',
            dot: 'bg-cream'
        },
        error: {
            text: 'Koneksi Gagal',
            classes: 'bg-ink text-cream border-ink',
            dot: 'bg-cream'
        },
        loading: {
            text: 'Memeriksa koneksi…',
            classes: 'bg-creamDark text-ink border-hairline',
            dot: 'bg-muted animate-pulse'
        }
    };

    function renderStatus(status, data) {
        const item = statusMap[status] || statusMap.error;
        badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 font-mono text-[10px] font-bold uppercase border ' + item.classes;
        badge.innerHTML = `<span class="w-2 h-2 ${item.dot}"></span><span>${item.text}</span>`;

        if (data && data.message) {
            messageBox.textContent = data.message;
            messageBox.classList.remove('hidden');
        } else {
            messageBox.classList.add('hidden');
        }

        if (data && data.sessions) {
            statsBox.classList.remove('hidden');
            document.getElementById('wa-sessions').textContent = `${data.sessions.connected ?? 0} / ${data.sessions.total ?? 0}`;
            document.getElementById('wa-sent').textContent = data.usage ? Number(data.usage.messages_sent ?? 0).toLocaleString() : '0';
            document.getElementById('wa-failed').textContent = data.usage ? Number(data.usage.messages_failed ?? 0).toLocaleString() : '0';
            document.getElementById('wa-workspace').textContent = data.workspace || 'CravaCloud';
        } else {
            statsBox.classList.add('hidden');
        }
    }

    async function loadStatus() {
        renderStatus('loading', null);
        try {
            const res = await fetch(urlStatus, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            renderStatus(data.status, data);
        } catch (err) {
            renderStatus('offline', { message: 'Tidak dapat menghubungi server internal CravaCloud.' });
        }
    }

    document.getElementById('wa-reload').addEventListener('click', loadStatus);

    document.getElementById('wa-test-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn = document.getElementById('wa-test-submit');
        const phone = document.getElementById('wa-test-phone').value;
        const msg = document.getElementById('wa-test-message').value;

        btn.disabled = true;
        btn.textContent = 'Mengirim pesan uji…';
        testResult.classList.add('hidden');

        try {
            const res = await fetch(urlTest, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ phone: phone, message: msg })
            });

            const data = await res.json();
            const success = (res.status === 200 && data.status === 'ok');

            testResult.classList.remove('hidden');
            if (success) {
                testResult.className = 'p-3 border text-xs bg-white border-electric text-ink';
                testResult.innerHTML = `<strong>Berhasil!</strong> ${data.message} <span class="font-mono text-[11px] block mt-1">ID Pesan: ${data.message_id} | Nomor: ${data.to}</span>`;
            } else {
                testResult.className = 'p-3 border-2 text-xs bg-creamDark border-ink text-ink';
                testResult.innerHTML = `<strong>Gagal:</strong> ${data.message || 'Terjadi kesalahan pada gateway.'}`;
            }
        } catch (err) {
            testResult.classList.remove('hidden');
            testResult.className = 'p-3 border-2 text-xs bg-creamDark border-ink text-ink';
            testResult.innerHTML = '<strong>Galat:</strong> Tidak dapat menghubungi server untuk mengirim pesan uji.';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Kirim Pesan Uji';
            loadStatus();
        }
    });

    loadStatus();
})();
</script>
@endpush