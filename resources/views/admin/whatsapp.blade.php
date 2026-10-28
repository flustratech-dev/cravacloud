@extends('layouts.admin', [
    'title' => 'WhatsApp Gateway',
    'headerTitle' => 'WhatsApp Gateway Management',
    'backUrl' => route('admin.index'),
    'backLabel' => 'Kembali ke Dashboard Admin'
])

@section('content')
<div class="space-y-6">

    @php
        $inputClass = 'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-black';
        $labelClass = 'block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1';
    @endphp

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-lg text-xs space-y-1">
            <span class="font-bold uppercase tracking-wider block mb-1">Periksa isian Anda:</span>
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- 1. KARTU STATUS KONEKSI -->
    <div class="bg-white rounded-lg border border-slate-200 p-5 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-900">Status Koneksi WhatsApp Gateway</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Notifikasi pelanggan dikirim melalui CravaCloud WA Gateway. Penautan nomor, QR code, dan riwayat pengiriman dikelola di dashboard gateway.
                </p>
            </div>
            <span id="wa-badge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                <span class="w-2 h-2 rounded-full bg-slate-400 animate-pulse"></span>
                Memeriksa koneksi&hellip;
            </span>
        </div>

        <div id="wa-message" class="hidden text-xs px-3 py-2 rounded-md bg-amber-50 border border-amber-200 text-amber-900"></div>

        <!-- Statistik Koneksi (Tampil Saat Terhubung) -->
        <div id="wa-stats" class="hidden grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-slate-100">
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Sesi Terhubung</div>
                <div class="text-xl font-bold text-slate-900 mt-1" id="wa-sessions">&mdash;</div>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Terkirim Bulan Ini</div>
                <div class="text-xl font-bold text-slate-900 mt-1" id="wa-sent">&mdash;</div>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Gagal Bulan Ini</div>
                <div class="text-xl font-bold text-slate-900 mt-1" id="wa-failed">&mdash;</div>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Workspace</div>
                <div class="text-sm font-bold text-slate-900 mt-1 truncate" id="wa-workspace">&mdash;</div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-2">
            <a href="{{ $gatewayUrl }}/sessions" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-black text-white text-xs font-semibold hover:bg-slate-800 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                Kelola Nomor &amp; Scan QR
            </a>
            <a href="{{ $gatewayUrl }}/messages" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Riwayat Pesan Gateway
            </a>
            <button type="button" id="wa-reload"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Muat Ulang Status
            </button>
        </div>
    </div>

    <!-- 2. KARTU KREDENSIAL GATEWAY -->
    <div class="bg-white rounded-lg border border-slate-200 p-5 space-y-5">
        <div>
            <h2 class="text-base font-bold text-slate-900">Kredensial &amp; Konfigurasi Gateway</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Pengaturan ini disimpan di basis data CravaCloud sehingga Anda dapat mengubahnya kapan saja tanpa perlu menyunting file <code>.env</code> atau me-restart web server.
            </p>
        </div>

        <form action="{{ route('admin.whatsapp.settings') }}" method="POST" class="space-y-4">
            @csrf

            <!-- Toggle Enable -->
            <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-between">
                <div>
                    <label for="wa_enabled" class="text-sm font-bold text-slate-900 cursor-pointer">
                        Aktifkan Pengiriman WhatsApp
                    </label>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Bila dimatikan, semua pengiriman notifikasi WhatsApp dilewati secara aman. Notifikasi email tetap berjalan normal.
                    </p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="wa_enabled" id="wa_enabled" value="1" class="sr-only peer"
                           @checked(old('wa_enabled', $pengaturan['wa_enabled']['value'] === '1'))>
                    <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-black"></div>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- URL Gateway -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="wa_url" class="{{ $labelClass }}">URL Gateway</label>
                        @if($pengaturan['wa_url']['from_database'])
                            <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Database</span>
                        @endif
                    </div>
                    <input type="url" name="wa_url" id="wa_url"
                           value="{{ old('wa_url', $pengaturan['wa_url']['value']) }}"
                           placeholder="https://wa.cravacloud.id"
                           class="{{ $inputClass }}">
                    <p class="text-[11px] text-slate-500 mt-1">Alamat endpoint server CravaCloud WA Gateway.</p>
                </div>

                <!-- API Key Gateway -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="wa_key" class="{{ $labelClass }}">API Key Gateway</label>
                        @if($pengaturan['wa_key']['from_database'])
                            <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Terenkripsi</span>
                        @endif
                    </div>
                    <input type="password" name="wa_key" id="wa_key"
                           autocomplete="new-password"
                           placeholder="{{ $pengaturan['wa_key']['masked'] ?: 'Masukkan API Key Gateway' }}"
                           class="{{ $inputClass }}">
                    <p class="text-[11px] text-slate-500 mt-1">
                        @if($pengaturan['wa_key']['masked'])
                            Saat ini terpasang: <code class="bg-slate-100 px-1 rounded text-slate-800">{{ $pengaturan['wa_key']['masked'] }}</code>. Kosongkan jika tidak ingin mengubah.
                        @else
                            Dapatkan API Key dari dashboard WhatsApp Gateway pada workspace CravaCloud.
                        @endif
                    </p>
                </div>

                <!-- Session ID -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="wa_session" class="{{ $labelClass }}">Session ID <span class="text-slate-400 font-normal">(Opsional)</span></label>
                        @if($pengaturan['wa_session']['from_database'])
                            <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Database</span>
                        @endif
                    </div>
                    <input type="text" name="wa_session" id="wa_session"
                           value="{{ old('wa_session', $pengaturan['wa_session']['value']) }}"
                           placeholder="Kosongkan untuk memakai sesi pertama terhubung"
                           class="{{ $inputClass }}">
                    <p class="text-[11px] text-slate-500 mt-1">Kosongkan agar gateway otomatis memakai sesi WhatsApp aktif pertama.</p>
                </div>

                <!-- Request Timeout -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="wa_timeout" class="{{ $labelClass }}">Batas Waktu Request (Detik)</label>
                        @if($pengaturan['wa_timeout']['from_database'])
                            <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Database</span>
                        @endif
                    </div>
                    <input type="number" name="wa_timeout" id="wa_timeout" min="1" max="60"
                           value="{{ old('wa_timeout', $pengaturan['wa_timeout']['value'] ?: 10) }}"
                           class="{{ $inputClass }}">
                    <p class="text-[11px] text-slate-500 mt-1">Waktu tunggu HTTP maksimal sebelum request dilewati tanpa menahan proses.</p>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <button type="submit"
                        class="px-5 py-2.5 rounded-lg bg-black text-white text-xs font-bold hover:bg-slate-800 transition-colors">
                    Simpan Pengaturan Kredensial
                </button>
            </div>
        </form>

        @php
            $tersimpanDiDb = collect($pengaturan)->filter(fn ($k) => $k['from_database'])->keys();
        @endphp

        @if($tersimpanDiDb->isNotEmpty())
            <div class="pt-4 border-t border-slate-100">
                <p class="text-xs text-slate-500 mb-2">
                    Kolom berikut tersimpan di database dan menimpa konfigurasi bawaan <code>.env</code>. Klik tombol untuk mengembalikannya ke nilai default:
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach($tersimpanDiDb as $kolom)
                        <form action="{{ route('admin.whatsapp.settings.forget') }}" method="POST"
                              onsubmit="return confirm('Kembalikan {{ $kolom }} ke nilai default .env?');">
                            @csrf
                            <input type="hidden" name="field" value="{{ $kolom }}">
                            <button type="submit"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-mono font-medium transition-colors">
                                <span>{{ $kolom }}</span>
                                <span class="text-slate-400 hover:text-red-600 ml-1 font-bold">&times;</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- 3. KARTU UJI KIRIM PESAN -->
    <div class="bg-white rounded-lg border border-slate-200 p-5 space-y-4">
        <div>
            <h2 class="text-base font-bold text-slate-900">Uji Kirim Pesan WhatsApp</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Kirim pesan uji coba ke nomor Anda sendiri untuk memastikan koneksi antara CravaCloud, gateway, dan WhatsApp berfungsi normal.
            </p>
        </div>

        <form id="wa-test-form" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="wa-test-phone" class="{{ $labelClass }}">Nomor WhatsApp Tujuan</label>
                    <input type="text" id="wa-test-phone" placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx"
                           class="{{ $inputClass }}" required>
                    <p class="text-[11px] text-slate-500 mt-1">Format: 08xx, 62xx, atau +62xx (otomatis dinormalisasi).</p>
                </div>
                <div class="md:col-span-2">
                    <label for="wa-test-message" class="{{ $labelClass }}">Isi Pesan Uji</label>
                    <textarea id="wa-test-message" rows="2" class="{{ $inputClass }}" required>Halo, ini adalah pesan uji koneksi WhatsApp Gateway resmi dari CravaCloud.</textarea>
                </div>
            </div>

            <button type="submit" id="wa-test-submit"
                    class="px-5 py-2.5 rounded-lg bg-slate-900 text-white text-xs font-bold hover:bg-black transition-colors disabled:opacity-50">
                Kirim Pesan Uji
            </button>

            <div id="wa-test-result" class="hidden p-3 rounded-lg text-xs"></div>
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
            classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            dot: 'bg-emerald-500'
        },
        disconnected: {
            text: 'Belum Ada Nomor Tertaut',
            classes: 'bg-amber-50 text-amber-700 border-amber-200',
            dot: 'bg-amber-500'
        },
        not_configured: {
            text: 'Belum Dikonfigurasi',
            classes: 'bg-slate-100 text-slate-700 border-slate-200',
            dot: 'bg-slate-400'
        },
        offline: {
            text: 'Gateway Tidak Terjangkau',
            classes: 'bg-red-50 text-red-700 border-red-200',
            dot: 'bg-red-500'
        },
        error: {
            text: 'Koneksi Gagal',
            classes: 'bg-red-50 text-red-700 border-red-200',
            dot: 'bg-red-500'
        },
        loading: {
            text: 'Memeriksa koneksi…',
            classes: 'bg-slate-100 text-slate-700 border-slate-200',
            dot: 'bg-slate-400 animate-pulse'
        }
    };

    function renderStatus(status, data) {
        const item = statusMap[status] || statusMap.error;
        badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ' + item.classes;
        badge.innerHTML = `<span class="w-2 h-2 rounded-full ${item.dot}"></span><span>${item.text}</span>`;

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

    // Form Uji Kirim
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
                testResult.className = 'p-3 rounded-lg text-xs bg-emerald-50 border border-emerald-200 text-emerald-800';
                testResult.innerHTML = `<strong>Berhasil!</strong> ${data.message} <span class="font-mono text-[11px] block mt-1">ID Pesan: ${data.message_id} | Nomor: ${data.to}</span>`;
            } else {
                testResult.className = 'p-3 rounded-lg text-xs bg-red-50 border border-red-200 text-red-800';
                testResult.innerHTML = `<strong>Gagal:</strong> ${data.message || 'Terjadi kesalahan pada gateway.'}`;
            }
        } catch (err) {
            testResult.classList.remove('hidden');
            testResult.className = 'p-3 rounded-lg text-xs bg-red-50 border border-red-200 text-red-800';
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
