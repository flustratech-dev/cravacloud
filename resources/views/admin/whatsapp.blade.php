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
        $isEnabled = $pengaturan['wa_enabled']['value'] === '1';
    @endphp

    @include('admin.partials.system-tabs')

    <!-- 1. KARTU STATUS KONEKSI -->
    <div class="bg-white border border-hairline p-5 sm:p-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-display text-base font-bold text-ink">Status Koneksi WhatsApp Gateway</h2>
                <p class="text-xs text-muted mt-0.5">
                    Gateway ini digunakan khusus untuk pengiriman <strong>Admin Operations Alerts</strong> ke nomor WhatsApp admin. Notifikasi pelanggan dikirim melalui Email &amp; Dashboard.
                </p>
            </div>
            <span id="wa-badge" class="inline-flex items-center gap-1.5 px-3 py-1 font-mono text-[10px] font-bold uppercase bg-creamDark text-ink border border-hairline">
                <span class="w-2 h-2 bg-muted animate-pulse"></span>
                Memeriksa koneksi&hellip;
            </span>
        </div>

        <div id="wa-warning" class="hidden text-xs px-3.5 py-2.5 bg-amber-50 border border-amber-400 text-amber-900 font-mono"></div>

        <div id="wa-message" class="hidden text-xs px-3.5 py-2.5 bg-creamDark border border-ink text-ink font-mono"></div>

        <!-- Statistik Koneksi -->
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

        <!-- Daftar Sesi Tertaut -->
        <div id="wa-sessions-wrap" class="hidden border border-hairline overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-creamDark/50 border-b border-hairline font-mono text-[10px] uppercase text-muted">
                        <th class="px-3.5 py-2">Nama Sesi</th>
                        <th class="px-3.5 py-2">Nomor</th>
                        <th class="px-3.5 py-2">Status</th>
                        <th class="px-3.5 py-2">Keterangan</th>
                    </tr>
                </thead>
                <tbody id="wa-sessions-body" class="divide-y divide-hairline"></tbody>
            </table>
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
            <a href="{{ $gatewayUrl }}/docs" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-hairline hover:border-ink text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors">
                <span>Dokumentasi API</span>
            </a>
            <button type="button" id="wa-reload"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-hairline hover:border-ink text-ink font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Muat Ulang Status</span>
            </button>
        </div>
    </div>

    <!-- 2. KARTU KONFIGURASI GATEWAY (READ-ONLY .ENV) -->
    <div class="bg-white border border-hairline p-5 sm:p-6 space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-base font-bold text-ink">Konfigurasi Gateway (.env)</h2>
                <p class="text-xs text-muted mt-0.5">
                    Seluruh kredensial dan pengaturan WhatsApp Gateway dikelola penuh melalui file <code class="font-mono bg-creamDark px-1">.env</code> (<code class="font-mono bg-creamDark px-1">WA_GATEWAY_*</code>) agar konsisten di lingkungan dev, staging, dan production.
                </p>
            </div>
            <span class="font-mono text-[10px] font-bold uppercase px-2.5 py-1 border border-electric bg-creamDark text-electric">ENV-ONLY</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-3.5 border border-hairline bg-creamDark/20">
                <div class="flex items-center justify-between mb-1">
                    <span class="{{ $labelClass }} mb-0">Status Pengiriman (WA_GATEWAY_ENABLED)</span>
                    <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">ENV</span>
                </div>
                <div class="font-mono text-xs font-bold text-ink">
                    {{ $isEnabled ? 'Aktif (true)' : 'Nonaktif (false)' }}
                </div>
            </div>

            <div class="p-3.5 border border-hairline bg-creamDark/20">
                <div class="flex items-center justify-between mb-1">
                    <span class="{{ $labelClass }} mb-0">URL Gateway (WA_GATEWAY_URL)</span>
                    <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">ENV</span>
                </div>
                <div class="font-mono text-xs font-bold text-ink break-all">
                    {{ $pengaturan['wa_url']['value'] ?: 'Belum diatur' }}
                </div>
            </div>

            <div class="p-3.5 border border-hairline bg-creamDark/20">
                <div class="flex items-center justify-between mb-1">
                    <span class="{{ $labelClass }} mb-0">API Key (WA_GATEWAY_KEY)</span>
                    <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">ENV</span>
                </div>
                <div class="font-mono text-xs font-bold text-ink">
                    {{ $pengaturan['wa_key']['masked'] ?: 'Belum diisi di .env' }}
                </div>
            </div>

            <div class="p-3.5 border border-hairline bg-creamDark/20">
                <div class="flex items-center justify-between mb-1">
                    <span class="{{ $labelClass }} mb-0">Session ID (WA_GATEWAY_SESSION)</span>
                    <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">ENV</span>
                </div>
                <div class="font-mono text-xs text-ink">
                    {{ $pengaturan['wa_session']['value'] ?: 'Otomatis (sesi terhubung pertama)' }}
                </div>
            </div>

            <div class="p-3.5 border border-hairline bg-creamDark/20">
                <div class="flex items-center justify-between mb-1">
                    <span class="{{ $labelClass }} mb-0">Timeout / Connect Timeout</span>
                    <span class="font-mono text-[10px] font-bold uppercase text-electric bg-creamDark px-1.5 py-0.5 border border-hairline">ENV</span>
                </div>
                <div class="font-mono text-xs text-ink">
                    {{ $pengaturan['wa_timeout']['value'] ?: 5 }} detik / {{ $pengaturan['wa_connect_timeout']['value'] ?: 3 }} detik
                </div>
            </div>

            <div class="p-3.5 border border-hairline bg-creamDark/20">
                <div class="flex items-center justify-between mb-1">
                    <span class="{{ $labelClass }} mb-0">Endpoint Webhook (X-VexaHost-Signature)</span>
                    <span class="font-mono text-[10px] font-bold uppercase {{ $webhookConfigured ? 'text-electric' : 'text-muted' }} bg-creamDark px-1.5 py-0.5 border border-hairline">
                        {{ $webhookConfigured ? 'Secret Terpasang' : 'Secret Kosong' }}
                    </span>
                </div>
                <div class="font-mono text-xs text-ink break-all">{{ $webhookUrl }}</div>
                <p class="text-[11px] text-muted mt-1">Atur <code class="font-mono bg-creamDark px-1">WA_GATEWAY_WEBHOOK_SECRET</code> di <code class="font-mono bg-creamDark px-1">.env</code> untuk verifikasi event webhook gateway.</p>
            </div>
        </div>
    </div>

    <!-- 3. KARTU UJI KIRIM PESAN -->
    <div class="bg-white border border-hairline p-5 sm:p-6 space-y-4">
        <div>
            <h2 class="font-display text-base font-bold text-ink">Uji Kirim Pesan WhatsApp</h2>
            <p class="text-xs text-muted mt-0.5">
                Kirim pesan uji coba ke nomor atau ID grup Anda sendiri untuk memastikan koneksi antara CravaCloud, gateway, dan WhatsApp berfungsi normal.
            </p>
        </div>

        <form id="wa-test-form" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="wa-test-phone" class="{{ $labelClass }}">Nomor WhatsApp / ID Grup Tujuan</label>
                    <input type="text" id="wa-test-phone" placeholder="08xxxxxxxxxx, 628xxxxxxxxxx, atau 120363xxxx@g.us"
                           class="{{ $inputClass }} font-mono" required>
                    <p class="text-[11px] text-muted mt-1">Format: 08xx, 62xx, +62xx (otomatis dinormalisasi), atau ID grup (@g.us).</p>
                </div>
                <div class="md:col-span-2">
                    <label for="wa-test-message" class="{{ $labelClass }}">Isi Pesan Uji</label>
                    <textarea id="wa-test-message" rows="2" maxlength="4096" class="{{ $inputClass }}" required>Halo, ini adalah pesan uji koneksi WhatsApp Gateway resmi dari CravaCloud.</textarea>
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
    const warningBox = document.getElementById('wa-warning');
    const messageBox = document.getElementById('wa-message');
    const statsBox = document.getElementById('wa-stats');
    const sessionsWrap = document.getElementById('wa-sessions-wrap');
    const sessionsBody = document.getElementById('wa-sessions-body');
    const testResult = document.getElementById('wa-test-result');
    let pollTimer = null;

    const statusMap = {
        ready: {
            text: 'Terhubung & Aktif',
            classes: 'bg-electric text-white border-electric',
            dot: 'bg-white'
        },
        partial: {
            text: 'Sebagian Sesi Terputus',
            classes: 'bg-amber-50 text-amber-900 border-amber-400',
            dot: 'bg-amber-600'
        },
        inactive: {
            text: 'Workspace Nonaktif',
            classes: 'bg-ink text-cream border-ink',
            dot: 'bg-cream'
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

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderStatus(status, data) {
        const item = statusMap[status] || statusMap.error;
        badge.className = 'inline-flex items-center gap-1.5 px-3 py-1 font-mono text-[10px] font-bold uppercase border ' + item.classes;
        badge.innerHTML = `<span class="w-2 h-2 ${item.dot}"></span><span>${escapeHtml(item.text)}</span>`;

        const warnings = [];
        if (data && data.enabled === false) {
            warnings.push('Pengiriman WA nonaktif di .env (WA_GATEWAY_ENABLED=false).');
        }
        if (data && data.failure_spike) {
            warnings.push('Terdeteksi lonjakan pesan gagal (+10 lebih dari cek terakhir). Periksa kesehatan nomor di WhatsApp ponsel.');
        }
        if (warnings.length > 0) {
            warningBox.textContent = warnings.join(' ');
            warningBox.classList.remove('hidden');
        } else {
            warningBox.classList.add('hidden');
        }

        if (data && data.message) {
            messageBox.textContent = data.message;
            messageBox.classList.remove('hidden');
        } else {
            messageBox.classList.add('hidden');
        }

        if (data && data.sessions) {
            statsBox.classList.remove('hidden');
            const limitText = data.sessions.limit != null ? ` (maks ${data.sessions.limit})` : '';
            document.getElementById('wa-sessions').textContent = `${data.sessions.connected ?? 0} / ${data.sessions.total ?? 0}${limitText}`;

            const sent = data.usage ? Number(data.usage.messages_sent ?? 0).toLocaleString() : '0';
            const quota = data.usage && data.usage.quota != null ? ` / ${Number(data.usage.quota).toLocaleString()}` : '';
            document.getElementById('wa-sent').textContent = sent + quota;

            document.getElementById('wa-failed').textContent = data.usage ? Number(data.usage.messages_failed ?? 0).toLocaleString() : '0';
            document.getElementById('wa-workspace').textContent = data.workspace || 'CravaCloud';
        } else {
            statsBox.classList.add('hidden');
        }

        if (data && Array.isArray(data.session_list) && data.session_list.length > 0) {
            sessionsBody.innerHTML = data.session_list.map((s) => {
                const st = s.status || 'unknown';
                const stClass = st === 'connected'
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                    : (st === 'failed' || st === 'disconnected' ? 'border-red-300 bg-red-50 text-red-700' : 'border-hairline bg-creamDark text-ink');
                const note = s.last_error || (s.push_name ? `Nama profil: ${s.push_name}` : '-');
                return `<tr>
                    <td class="px-3.5 py-2 font-bold text-ink">${escapeHtml(s.name || s.id)}</td>
                    <td class="px-3.5 py-2 font-mono text-ink">${escapeHtml(s.phone_number || '-')}</td>
                    <td class="px-3.5 py-2 font-mono text-[10px] uppercase"><span class="px-1.5 py-0.5 border ${stClass}">${escapeHtml(st)}</span></td>
                    <td class="px-3.5 py-2 text-muted">${escapeHtml(note)}</td>
                </tr>`;
            }).join('');
            sessionsWrap.classList.remove('hidden');
        } else {
            sessionsWrap.classList.add('hidden');
            sessionsBody.innerHTML = '';
        }
    }

    async function loadStatus(fresh = false) {
        clearTimeout(pollTimer);
        renderStatus('loading', null);
        try {
            const targetUrl = fresh ? `${urlStatus}?fresh=1` : urlStatus;
            const res = await fetch(targetUrl, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            renderStatus(data.status, data);
        } catch (err) {
            renderStatus('offline', { message: 'Tidak dapat menghubungi server internal CravaCloud.' });
        } finally {
            pollTimer = setTimeout(() => loadStatus(false), 30000);
        }
    }

    document.getElementById('wa-reload').addEventListener('click', () => loadStatus(true));

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
                const sched = data.scheduled_at ? ` | Dijadwalkan: ${escapeHtml(data.scheduled_at)}` : '';
                testResult.innerHTML = `<strong>Berhasil!</strong> ${escapeHtml(data.message)} <span class="font-mono text-[11px] block mt-1">ID Pesan: ${escapeHtml(data.message_id)} | Status: ${escapeHtml(data.queue_status || 'queued')} | Tujuan: ${escapeHtml(data.to)}${sched}</span>`;
            } else {
                testResult.className = 'p-3 border-2 text-xs bg-creamDark border-ink text-ink';
                const retryInfo = data.retry_after ? ` (Coba lagi dalam ${escapeHtml(data.retry_after)} detik)` : '';
                testResult.innerHTML = `<strong>Gagal:</strong> ${escapeHtml(data.message || 'Terjadi kesalahan pada gateway.')}${retryInfo}`;
            }
        } catch (err) {
            testResult.classList.remove('hidden');
            testResult.className = 'p-3 border-2 text-xs bg-creamDark border-ink text-ink';
            testResult.innerHTML = '<strong>Galat:</strong> Tidak dapat menghubungi server untuk mengirim pesan uji.';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Kirim Pesan Uji';
            loadStatus(true);
        }
    });

    loadStatus(false);
})();
</script>
@endpush