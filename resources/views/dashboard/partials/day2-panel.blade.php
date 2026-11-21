{{--
    Phase 5F — Customer Day-2 self-service panel.
    Included ONLY when $day2['capable'] is true (auto-provisioned tencent).
    Manual/retail servers never see this; they keep the legacy buttons.
    100% white-label: no vendor name, bundle id or upstream detail is rendered.
    The VNC URL is fetched on-demand and opened directly — never stored/logged.
--}}
@php
    // Context: 'customer' (default) or 'admin'. Selects the route set so the
    // same panel markup serves both the customer detail page and the admin
    // instances mirror. The shared JS lives in day2-panel-script.blade.php
    // (include ONCE per page).
    $day2Context = $day2Context ?? 'customer';
    $isAdminCtx = $day2Context === 'admin';
    $r = fn (string $customer, string $admin) => $isAdminCtx ? route($admin, $vps->id) : route($customer, $vps->id);
    $day2Config = [
        'id' => $vps->id,
        'status' => $vps->status,
        'caps' => $day2,
        'csrf' => csrf_token(),
        'lazy' => $isAdminCtx,
        'redirect' => $isAdminCtx ? route('admin.instances') : route('dashboard.billing'),
        'routes' => [
            'power' => $r('dashboard.vps.day2.power', 'admin.instances.day2.power'),
            'reset' => $r('dashboard.vps.day2.reset-password', 'admin.instances.day2.reset-password'),
            'console' => $r('dashboard.vps.day2.console', 'admin.instances.day2.console'),
            'firewall' => $r('dashboard.vps.day2.firewall.index', 'admin.instances.day2.firewall.index'),
            'firewallStore' => $r('dashboard.vps.day2.firewall.store', 'admin.instances.day2.firewall.store'),
            'firewallDestroy' => $r('dashboard.vps.day2.firewall.destroy', 'admin.instances.day2.firewall.destroy'),
            'traffic' => $r('dashboard.vps.day2.traffic', 'admin.instances.day2.traffic'),
            'upgradePreview' => $r('dashboard.vps.day2.upgrade.preview', 'admin.instances.day2.upgrade.preview'),
            'upgrade' => $r('dashboard.vps.day2.upgrade', 'admin.instances.day2.upgrade'),
            'poll' => $isAdminCtx ? route('admin.instances.day2.status', $vps->id) : route('dashboard.vps.provisioning-status', $vps->id),
        ],
    ];
    $curCpu = (int) ($vps->cpu ?: 0);
    $curRam = (int) ($vps->ram ?: 0);
    $curDisk = (int) ($vps->disk ?: 0);
    $curSpecId = (int) ($vps->order?->vps_spec_id ?? 0);
    $upgradeSpecs = \App\Models\VpsSpec::where('is_active', true)->get()
        ->filter(fn ($s) => $s->hasUpstreamMapping()
            && (int) $s->cpu >= $curCpu && (int) $s->ram >= $curRam && (int) $s->disk >= $curDisk
            && (int) $s->id !== $curSpecId
            && ((int) $s->cpu > $curCpu || (int) $s->ram > $curRam || (int) $s->disk > $curDisk))
        ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'cpu' => (int) $s->cpu, 'ram' => (int) $s->ram, 'disk' => (int) $s->disk, 'price' => (float) $s->sell_price])
        ->values();
    $day2Config['upgradeSpecs'] = $upgradeSpecs;
@endphp

<div x-data="day2Panel(@js($day2Config))" x-init="init()" @day2-open-{{ $vps->id }}.window="activate()" class="space-y-6">

    <template x-if="globalError">
        <div class="border border-ink bg-creamDark/60 px-4 py-3 font-mono text-[11px] text-ink flex items-center justify-between gap-3">
            <span x-text="globalError"></span>
            <button type="button" @click="globalError = ''" class="font-bold px-1.5 cursor-pointer">&times;</button>
        </div>
    </template>

    {{-- Status + task progress --}}
    <div class="border-2 border-ink bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-mono text-[10px] uppercase tracking-wider text-muted">Status Server</p>
                <p class="font-display text-lg font-bold text-ink uppercase" x-text="statusLabel()"></p>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full" :class="status === 'running' ? 'bg-emerald-500' : (status === 'error' ? 'bg-red-500' : 'bg-amber-400')"></span>
                <span class="font-mono text-xs text-muted" x-text="status"></span>
            </div>
        </div>
        <template x-if="task && (task.status === 'pending' || task.status === 'running')">
            <div class="mt-4">
                <div class="flex justify-between font-mono text-[11px] text-muted mb-1">
                    <span x-text="taskLabel(task.kind)"></span>
                    <span x-text="(task.progress || 0) + '%'"></span>
                </div>
                <div class="h-2 bg-creamDark border border-hairline">
                    <div class="h-full bg-electric transition-all" :style="'width:' + (task.progress || 0) + '%'"></div>
                </div>
                <p class="font-mono text-[11px] text-muted mt-1" x-text="task.current_step || 'Memproses…'"></p>
            </div>
        </template>
        <template x-if="task && task.status === 'failed' && task.error">
            <p class="mt-3 font-mono text-[11px] text-red-600" x-text="task.error"></p>
        </template>
    </div>

    {{-- Power --}}
    <div class="border border-hairline bg-white p-5">
        <h3 class="font-display text-base font-bold text-ink mb-1">Kontrol Daya</h3>
        <p class="font-mono text-[11px] text-muted mb-4">Nyalakan, matikan, atau restart server Anda secara mandiri.</p>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="power('start')" :disabled="busy || !['stopped','suspended','error'].includes(status)"
                class="rounded-none px-4 py-2.5 bg-emerald-700 hover:bg-emerald-800 disabled:opacity-40 disabled:cursor-not-allowed text-white font-mono text-xs font-bold uppercase cursor-pointer">Nyalakan</button>
            <button type="button" @click="power('stop')" :disabled="busy || status !== 'running'"
                class="rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark disabled:opacity-40 disabled:cursor-not-allowed font-mono text-xs font-bold uppercase text-ink cursor-pointer">Matikan</button>
            <button type="button" @click="power('reboot')" :disabled="busy || status !== 'running'"
                class="rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark disabled:opacity-40 disabled:cursor-not-allowed font-mono text-xs font-bold uppercase text-ink cursor-pointer">Restart</button>
            <button type="button" @click="power('force_reboot')" :disabled="busy || status !== 'running'"
                class="rounded-none px-4 py-2.5 border border-red-600 bg-white hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed font-mono text-xs font-bold uppercase text-red-600 cursor-pointer">Restart Paksa</button>
        </div>
    </div>

    {{-- Console + reset password --}}
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="border border-hairline bg-white p-5" x-show="caps.console">
            <h3 class="font-display text-base font-bold text-ink mb-1">Konsol Server (VNC)</h3>
            <p class="font-mono text-[11px] text-muted mb-4">Akses layar server langsung dari browser. Tautan hanya berlaku sekali dan ~15 detik.</p>
            <button type="button" @click="openConsole()" :disabled="busy || status !== 'running'"
                class="rounded-none px-4 py-2.5 bg-ink hover:bg-electric disabled:opacity-40 disabled:cursor-not-allowed text-white font-mono text-xs font-bold uppercase cursor-pointer">Buka Konsol</button>
            <p class="font-mono text-[10px] text-muted mt-2" x-show="status !== 'running'">Server harus aktif (running) untuk membuka konsol.</p>
        </div>
        <div class="border border-hairline bg-white p-5" x-show="caps.reset_password">
            <h3 class="font-display text-base font-bold text-ink mb-1">Reset Password</h3>
            <p class="font-mono text-[11px] text-muted mb-4">Buat password login baru secara otomatis. Password baru tersimpan terenkripsi di tab Detail Akses &amp; SSH (pemberitahuan dikirim via email{{ $isAdminCtx ? '' : ' &amp; notifikasi dasbor' }}).</p>
            <button type="button" @click="resetPassword()" :disabled="busy || status !== 'running'"
                class="rounded-none px-4 py-2.5 border border-ink bg-white hover:bg-creamDark disabled:opacity-40 disabled:cursor-not-allowed font-mono text-xs font-bold uppercase text-ink cursor-pointer">Reset Password</button>
        </div>
    </div>

    {{-- Traffic --}}
    <div class="border border-hairline bg-white p-5" x-show="caps.traffic">
        <div class="flex items-center justify-between mb-1">
            <h3 class="font-display text-base font-bold text-ink">Kuota Trafik Bulanan</h3>
            <button type="button" @click="loadTraffic()" class="font-mono text-[10px] uppercase text-electric underline cursor-pointer">Muat ulang</button>
        </div>
        <template x-if="traffic">
            <div>
                <div class="flex justify-between font-mono text-[11px] text-muted mb-1">
                    <span x-text="fmtBytes(traffic.used_bytes) + ' terpakai'"></span>
                    <span x-text="fmtBytes(traffic.total_bytes) + ' total'"></span>
                </div>
                <div class="h-3 bg-creamDark border border-hairline">
                    <div class="h-full transition-all" :class="traffic.percent >= 90 ? 'bg-red-500' : (traffic.percent >= 70 ? 'bg-amber-400' : 'bg-electric')" :style="'width:' + Math.min(100, traffic.percent || 0) + '%'"></div>
                </div>
                <p class="font-mono text-[10px] text-muted mt-1">
                    <span x-text="(traffic.percent || 0).toFixed(1) + '%'"></span>
                    <template x-if="traffic.reset_at"><span> · reset <span x-text="traffic.reset_at"></span></span></template>
                </p>
            </div>
        </template>
        <p class="font-mono text-[11px] text-muted" x-show="!traffic">Memuat data trafik…</p>
    </div>

    {{-- Firewall --}}
    <div class="border border-hairline bg-white p-5" x-show="caps.firewall">
        <h3 class="font-display text-base font-bold text-ink mb-1">Firewall</h3>
        <p class="font-mono text-[11px] text-muted mb-4">Atur port yang boleh diakses dari internet. Hati-hati menghapus aturan SSH (port 22).</p>

        <div class="overflow-x-auto border border-hairline mb-4">
            <table class="w-full font-mono text-[11px]">
                <thead class="bg-creamDark text-ink uppercase">
                    <tr>
                        <th class="text-left px-3 py-2">Protokol</th>
                        <th class="text-left px-3 py-2">Port</th>
                        <th class="text-left px-3 py-2">Sumber (CIDR)</th>
                        <th class="text-left px-3 py-2">Aksi</th>
                        <th class="text-left px-3 py-2">Keterangan</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(r, i) in rules" :key="i">
                        <tr class="border-t border-hairline">
                            <td class="px-3 py-2" x-text="r.protocol"></td>
                            <td class="px-3 py-2" x-text="r.port"></td>
                            <td class="px-3 py-2" x-text="r.cidr"></td>
                            <td class="px-3 py-2" x-text="r.action"></td>
                            <td class="px-3 py-2 text-muted" x-text="r.description || '—'"></td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="deleteRule(r)" class="text-red-600 uppercase font-bold hover:underline cursor-pointer">Hapus</button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="rules.length === 0"><td colspan="6" class="px-3 py-3 text-muted">Belum ada aturan firewall.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="grid sm:grid-cols-5 gap-2 items-end">
            <div>
                <label class="block font-mono text-[10px] uppercase text-muted mb-1">Protokol</label>
                <select x-model="fw.protocol" class="w-full border border-ink bg-white px-2 py-2 font-mono text-xs">
                    <option>TCP</option><option>UDP</option><option>ICMP</option><option>ALL</option>
                </select>
            </div>
            <div>
                <label class="block font-mono text-[10px] uppercase text-muted mb-1">Port</label>
                <input x-model="fw.port" type="text" placeholder="80 / 80,443 / 8000-8100" class="w-full border border-ink bg-white px-2 py-2 font-mono text-xs">
            </div>
            <div>
                <label class="block font-mono text-[10px] uppercase text-muted mb-1">CIDR</label>
                <input x-model="fw.cidr" type="text" placeholder="0.0.0.0/0" class="w-full border border-ink bg-white px-2 py-2 font-mono text-xs">
            </div>
            <div>
                <label class="block font-mono text-[10px] uppercase text-muted mb-1">Keterangan</label>
                <input x-model="fw.description" type="text" maxlength="100" placeholder="opsional" class="w-full border border-ink bg-white px-2 py-2 font-mono text-xs">
            </div>
            <button type="button" @click="addRule()" :disabled="busy" class="rounded-none px-4 py-2 bg-electric hover:bg-electricHover disabled:opacity-40 text-white font-mono text-xs font-bold uppercase cursor-pointer">Tambah</button>
        </div>
        <p class="font-mono text-[11px] text-red-600 mt-2" x-show="fwError" x-text="fwError"></p>
    </div>

    {{-- Upgrade --}}
    <div class="border border-hairline bg-white p-5" x-show="caps.upgrade">
        <h3 class="font-display text-base font-bold text-ink mb-1">Upgrade Spesifikasi</h3>
        <p class="font-mono text-[11px] text-muted mb-4">Tingkatkan CPU/RAM/disk. Biaya dihitung prorata sesuai sisa masa aktif; upgrade diterapkan setelah invoice dibayar.</p>
        <template x-if="upgradeSpecs.length > 0">
            <div>
                <div class="flex flex-wrap gap-2 items-end mb-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block font-mono text-[10px] uppercase text-muted mb-1">Paket tujuan</label>
                        <select x-model="upgradeTarget" @change="previewUpgrade()" class="w-full border border-ink bg-white px-2 py-2 font-mono text-xs">
                            <option value="">— pilih paket —</option>
                            <template x-for="s in upgradeSpecs" :key="s.id">
                                <option :value="s.id" x-text="s.name + ' (' + s.cpu + ' vCPU / ' + s.ram + ' GB / ' + s.disk + ' GB)'"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <template x-if="preview">
                    <div class="border border-hairline bg-creamDark/40 p-4 mb-3 font-mono text-[11px] space-y-1">
                        <template x-if="preview.ok">
                            <div class="space-y-1">
                                <p class="text-ink">Sisa masa aktif: <strong x-text="preview.remaining_days + ' hari'"></strong></p>
                                <p class="text-ink">Selisih bulanan: <strong x-text="fmtIdr(preview.monthly_difference)"></strong></p>
                                <p class="text-ink">Prorata: <strong x-text="fmtIdr(preview.prorata_amount)"></strong></p>
                                <p class="text-ink">Pajak: <strong x-text="fmtIdr(preview.tax_amount)"></strong></p>
                                <p class="text-electric font-bold text-sm">Total: <span x-text="fmtIdr(preview.total)"></span></p>
                            </div>
                        </template>
                        <template x-if="!preview.ok">
                            <p class="text-red-600" x-text="preview.error"></p>
                        </template>
                    </div>
                </template>
                <button type="button" @click="submitUpgrade()" :disabled="busy || !preview || !preview.ok"
                    class="rounded-none px-4 py-2.5 bg-electric hover:bg-electricHover disabled:opacity-40 disabled:cursor-not-allowed text-white font-mono text-xs font-bold uppercase cursor-pointer">Buat Invoice Upgrade</button>
            </div>
        </template>
        <p class="font-mono text-[11px] text-muted" x-show="upgradeSpecs.length === 0">Tidak ada paket upgrade yang tersedia untuk server ini.</p>
    </div>

    @if(!$isAdminCtx && $vps->acceptsServiceRequests())
    {{-- Auto Reinstall OS --}}
    <div class="border border-hairline bg-white p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="font-display text-base font-bold text-ink mb-1">Install Ulang OS (Reinstall Otomatis)</h3>
                <p class="font-mono text-[11px] text-muted">Pasang ulang sistem operasi &amp; stack pilihan secara otomatis (~2–5 menit). Seluruh data di server terhapus permanen.</p>
            </div>
            <button type="button" @click="reinstallModal = true"
                :disabled="busy || status === 'reinstalling' || status === 'provisioning'"
                class="rounded-none px-4 py-2.5 border border-red-600 bg-white hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed text-red-600 font-mono text-xs font-bold uppercase whitespace-nowrap self-start sm:self-auto cursor-pointer">
                Reinstall OS Otomatis
            </button>
        </div>
    </div>
    @elseif($isAdminCtx && $vps->acceptsServiceRequests())
    @php
        $adminStackList = app(\App\Services\Provisioning\Stacks\StackRegistry::class)->all();
        $adminOsList = collect(\App\Models\Order::osLabels())->except(['custom']);
    @endphp
    <div class="border border-hairline bg-white p-5">
        <h3 class="font-display text-base font-bold text-ink mb-1">Reinstall OS Otomatis (Admin Override)</h3>
        <p class="font-mono text-[11px] text-muted mb-3">Memasang ulang OS dan stack via provider secara otomatis. Seluruh data di server akan terhapus.</p>
        <form action="{{ route('admin.instances.day2.reinstall', $vps->id) }}" method="POST"
              data-confirm="Mulai reinstall otomatis untuk {{ $vps->hostname }}? Seluruh data di server akan terhapus!"
              class="grid sm:grid-cols-4 gap-2 items-end">
            @csrf
            <div>
                <label class="block font-mono text-[10px] uppercase text-muted mb-1">Sistem Operasi</label>
                <select name="os" class="w-full border border-ink bg-white px-2 py-2 font-mono text-xs text-ink">
                    @foreach($adminOsList as $osKey => $osLabel)
                        <option value="{{ $osKey }}" {{ ($vps->os ?? 'ubuntu_24_04') === $osKey ? 'selected' : '' }}>{{ $osLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-mono text-[10px] uppercase text-muted mb-1">Control Panel / Stack</label>
                <select name="control_panel" class="w-full border border-ink bg-white px-2 py-2 font-mono text-xs text-ink">
                    @foreach($adminStackList as $stackDef)
                        <option value="{{ $stackDef->key }}" {{ ($vps->control_panel ?? 'none') === $stackDef->key ? 'selected' : '' }}>{{ $stackDef->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-mono text-[10px] uppercase text-muted mb-1">Ketik Hostname ({{ $vps->hostname }})</label>
                <input type="text" name="confirm_hostname" placeholder="{{ $vps->hostname }}" required
                       class="w-full border border-ink bg-white px-2 py-2 font-mono text-xs text-ink">
            </div>
            <button type="submit" class="rounded-none px-4 py-2 bg-ink hover:bg-electric text-cream font-mono text-xs font-bold uppercase cursor-pointer">
                Reinstall Otomatis
            </button>
        </form>
    </div>
    @endif

    <p class="font-mono text-[11px] text-red-600" x-show="globalError" x-text="globalError"></p>
</div>
