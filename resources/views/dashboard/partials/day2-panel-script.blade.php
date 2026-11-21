{{--
    Phase 5F — shared Alpine component for the Day-2 panel.
    Include ONCE per page (customer detail OR admin instances). The markup
    partial (day2-panel.blade.php) may be rendered multiple times on the admin
    list; this script must not be duplicated.
--}}
<script>
    function day2Panel(cfg) {
        return {
            id: cfg.id, status: cfg.status, caps: cfg.caps, routes: cfg.routes, csrf: cfg.csrf,
            redirect: cfg.redirect, upgradeSpecs: cfg.upgradeSpecs || [],
            lazy: Boolean(cfg.lazy), activated: false,
            busy: false, globalError: '', fwError: '',
            task: null, pollTimer: null,
            traffic: null, rules: [],
            fw: { protocol: 'TCP', port: '', cidr: '0.0.0.0/0', description: '' },
            upgradeTarget: '', preview: null,

            init() {
                if (!this.lazy) {
                    this.activate();
                }
            },
            activate() {
                if (this.activated) return;
                this.activated = true;
                if (this.caps.traffic) this.loadTraffic();
                if (this.caps.firewall) this.loadFirewall();
                this.poll();
            },
            statusLabel() {
                return ({ running: 'Aktif', stopped: 'Berhenti', rebooting: 'Restart…', reinstalling: 'Install Ulang…', suspended: 'Ditangguhkan', error: 'Bermasalah', provisioning: 'Disiapkan…', terminated: 'Dihentikan' })[this.status] || this.status;
            },
            taskLabel(kind) {
                return ({
                    provision: 'Menyiapkan server',
                    install_stack: 'Memasang stack / aplikasi',
                    start: 'Menyalakan server',
                    stop: 'Mematikan server',
                    reboot: 'Restart server',
                    reset_password: 'Reset password',
                    upgrade: 'Upgrade spesifikasi',
                    reinstall: 'Install ulang OS',
                })[kind] || 'Memproses';
            },
            fmtBytes(b) {
                b = Number(b || 0);
                if (b >= 1099511627776) return (b / 1099511627776).toFixed(2) + ' TB';
                if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
                if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
                return b + ' B';
            },
            fmtIdr(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); },
            headers() { return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }; },

            async call(url, method, body, opts = {}) {
                if (!opts.silent) this.globalError = '';
                try {
                    const res = await fetch(url, { method, headers: this.headers(), body: body ? JSON.stringify(body) : undefined });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.ok === false) {
                        const msg = data.error || data.message || 'Permintaan gagal diproses.';
                        this.globalError = msg;
                        if (!opts.silent && typeof showAlert === 'function') showAlert(msg, { icon: 'error', title: 'Gagal' });
                        return null;
                    }
                    return data;
                } catch (e) {
                    const msg = 'Gagal terhubung ke server.';
                    this.globalError = msg;
                    if (!opts.silent && typeof showAlert === 'function') showAlert(msg, { icon: 'error', title: 'Gagal' });
                    return null;
                }
            },

            async power(action) {
                const destructive = ['stop', 'force_reboot'].includes(action);
                if (destructive && !confirm(action === 'stop' ? 'Matikan server sekarang? Layanan akan berhenti sampai dinyalakan lagi.' : 'Restart paksa dapat menyebabkan kehilangan data yang belum disimpan. Lanjutkan?')) return;
                this.busy = true;
                const data = await this.call(this.routes.power, 'POST', { action });
                this.busy = false;
                if (data) { this.startPolling(); if (typeof showAlert === 'function') showAlert('Permintaan ' + action + ' sedang diproses.', { icon: 'success', title: 'Diproses' }); }
            },
            async resetPassword() {
                if (!confirm('Reset password login server? Password baru akan dikirim lewat email & notifikasi.')) return;
                this.busy = true;
                const data = await this.call(this.routes.reset, 'POST', {});
                this.busy = false;
                if (data) { this.startPolling(); if (typeof showAlert === 'function') showAlert('Reset password sedang diproses. Cek email/notifikasi.', { icon: 'success', title: 'Diproses' }); }
            },
            async openConsole() {
                this.busy = true;
                const data = await this.call(this.routes.console, 'GET');
                this.busy = false;
                // Single-use URL: open immediately, never persist it.
                if (data && data.url) window.open(data.url, '_blank', 'noopener');
            },

            async loadTraffic() { const d = await this.call(this.routes.traffic, 'GET', undefined, { silent: true }); if (d) this.traffic = d; },
            async loadFirewall() { const d = await this.call(this.routes.firewall, 'GET', undefined, { silent: true }); if (d) this.rules = d.rules || []; },
            async addRule() {
                this.fwError = '';
                if (!this.fw.port) { this.fwError = 'Port wajib diisi.'; return; }
                this.busy = true;
                const d = await this.call(this.routes.firewallStore, 'POST', this.fw);
                this.busy = false;
                if (d) { this.fw.port = ''; this.fw.description = ''; this.loadFirewall(); }
                else this.fwError = this.globalError;
            },
            async deleteRule(r) {
                const isSsh = ['TCP', 'ALL'].includes(r.protocol) && String(r.port).split(',').some(seg => {
                    seg = seg.trim();
                    if (seg.includes('-')) { const [a, b] = seg.split('-').map(Number); return a <= 22 && b >= 22; }
                    return Number(seg) === 22;
                });
                let confirmSsh = false;
                if (isSsh) {
                    if (!confirm('Menghapus aturan SSH (port 22) dapat mengunci akses ke server. Yakin ingin menghapus?')) return;
                    confirmSsh = true;
                } else if (!confirm('Hapus aturan firewall ' + r.protocol + ' ' + r.port + '?')) return;
                this.busy = true;
                const d = await this.call(this.routes.firewallDestroy, 'DELETE', { protocol: r.protocol, port: r.port, cidr: r.cidr, action: r.action, confirm_ssh: confirmSsh });
                this.busy = false;
                if (d) this.loadFirewall();
            },

            async previewUpgrade() {
                this.preview = null;
                if (!this.upgradeTarget) return;
                const d = await this.call(this.routes.upgradePreview, 'POST', { target_spec_id: Number(this.upgradeTarget) });
                if (d) this.preview = d;
            },
            async submitUpgrade() {
                if (!this.preview || !this.preview.ok) return;
                if (!confirm('Buat invoice upgrade sebesar ' + this.fmtIdr(this.preview.total) + '? Upgrade diterapkan setelah invoice dibayar.')) return;
                this.busy = true;
                const d = await this.call(this.routes.upgrade, 'POST', { target_spec_id: Number(this.upgradeTarget) });
                this.busy = false;
                if (d) {
                    if (typeof showAlert === 'function') showAlert('Invoice upgrade dibuat. Silakan selesaikan pembayaran.', { icon: 'success', title: 'Invoice dibuat' });
                    window.location.href = this.redirect;
                }
            },

            async poll() {
                const res = await fetch(this.routes.poll, { headers: { 'Accept': 'application/json' } });
                const d = await res.json().catch(() => ({}));
                if (d.vps_status) this.status = d.vps_status;
                this.task = d.task || null;
                if (this.task && ['pending', 'running'].includes(this.task.status)) this.startPolling();
                else this.stopPolling();
            },
            startPolling() {
                if (this.pollTimer) return;
                this.pollTimer = setInterval(async () => {
                    const prev = this.task ? this.task.status : null;
                    const kind = this.task ? this.task.kind : null;
                    await this.poll();
                    const now = this.task ? this.task.status : null;
                    if (prev && ['pending', 'running'].includes(prev) && now === 'succeeded') {
                        if (this.caps.traffic) this.loadTraffic();
                        if (kind === 'reset_password') {
                            window.dispatchEvent(new CustomEvent('vps-password-reset'));
                        }
                        if (typeof showAlert === 'function') showAlert('Aksi selesai diproses.', { icon: 'success', title: 'Selesai' });
                        if (['reinstall', 'upgrade', 'install_stack', 'provision'].includes(kind)) {
                            setTimeout(() => window.location.reload(), 1200);
                        }
                    }
                }, 4000);
            },
            stopPolling() { if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; } },
        };
    }
</script>