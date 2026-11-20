# Audit Codebase + Blueprint Arsitektur Backend Cravacloud × Tencent Cloud Lighthouse / TAT / Billing

> Status: **DISETUJUI Ryan 2026-11-19 dengan 5 revisi wajib (sudah diterapkan di dokumen ini). Phase 1–2 dieksekusi; uji live berbayar Phase 3 wajib konfirmasi ulang sebelum `CreateInstances` real.**
> Tanggal audit: 2026-11-19 · Mode audit: read-only · Driver aktif saat ini: `manual`
> Kredensial Tencent (`SecretKey.csv`) TIDAK pernah dicetak ke output/log/commit; dimuat ke `.env` (gitignored) tanpa echo nilai.

### Revisi wajib final (2026-11-19, dari review Ryan)

1. **Quota anti-DoS:** tidak ada hard-lock kuota 24 jam di order `pending`. Checkout = cek ketersediaan + maks 2 order pending per user; **reservasi keras saat `paid`** (§2.4).
2. **Nama API resmi:** reinstall = **`ResetInstance`** (singular); filter nama `DescribeInstances` = **`instance-name`**; TAT `RunCommand` wajib menunggu **`DescribeAutomationAgentStatus == Online`**; bila pakai `Parameters` wajib **`EnableParameter: true`** dan value berupa **JSON string** (§2.1–2.3).
3. **Ubuntu default user = `ubuntu`** (bukan root). `ResetInstancesPassword` pada VM `RUNNING` memicu shutdown+reboot → wajib poll **`LatestOperationState == SUCCESS` && `RUNNING`** lalu **agent TAT Online** sebelum `RunCommand`; `ssh_username` disesuaikan per blueprint agar kredensial customer langsung bisa login (§2.3).
4. **Recover `SHUTDOWN` (recycle bin):** tidak bisa `StartInstances` — harus **`RenewInstances`** lalu sync ulang `public_ip` dari `DescribeInstances` (IP bisa berubah) (§2.6).
5. **Firewall diff:** `DescribeFirewallRules` dulu, tambahkan hanya port yang belum terbuka (hindari error duplikat 22/80/443) (§2.3).

---

## BAGIAN 1 — RINGKASAN HASIL AUDIT CODEBASE

### 1.1 Yang sudah matang (pondasi kuat, tinggal disambungkan)

| Komponen | Path | Kondisi |
| :--- | :--- | :--- |
| Kontrak provider | `app/Services/Provisioning/Contracts/VpsProviderContract.php` | Lengkap: `provision/start/stop/reboot/forceReboot/reinstall/destroy/status/listInventory`. Siap diimplement driver Tencent tanpa mengubah caller. |
| Registry provider | `app/Services/Provisioning/ProviderManager.php` | Laravel Manager; default dari `services.provisioning.default` (`PROVISIONING_DRIVER`). Tinggal tambah `createTencentDriver()`. |
| Base job + retry | `app/Jobs/Provisioning/AbstractProvisioningJob.php` | 5 attempt, backoff `[10,30,60,120,300]`, timeout 600s, guard task `succeeded/cancelled`, `ProviderException(retryable)` dihormati, `failed()` menandai task + `onFailure`. |
| Exception provider | `app/Services/Provisioning/Exceptions/ProviderException.php` | Sudah punya `retryable`, `providerCode`, `providerResponse` — pas untuk mapping error Tencent. |
| State machine Order | `app/Services/OrderStateMachine.php` | Transisi legal lengkap (`paid→provisioning→active`, `failed→provisioning` retry), `lockForUpdate`, history audit, hook `DB::afterCommit` → `PaymentCompletionService` saat `paid`. |
| State machine VPS | `app/Services/VpsStateMachine.php` | Sama matangnya; status `provisioning/running/stopped/rebooting/reinstalling/suspended/terminated/error`. |
| Titik tunggal pasca-lunas | `app/Services/PaymentCompletionService.php` | Idempoten (`paid_notified_at` klaim atomik), semua jalur bayar (Midtrans/Xendit/Lynk/QRIS/verifikasi admin/Shopee) bermuara sini. **Hook ideal untuk memicu provisioning otomatis.** |
| Renewal idempoten | `app/Services/RenewalService.php` | `handleRenewalPayment()` lock + `renewal_applied_at`; perpanjangan dihitung dari `max(now, expires_at)` — sisa hari pelanggan tidak hangus. |
| Cron billing | `app/Console/Commands/Billing*.php` + `routes/console.php` | Terjadwal harian 02:00–05:00 WIB, `withoutOverlapping()->onOneServer()`, semua punya `--dry-run`. |
| Reconcile | `app/Console/Commands/ProviderReconcileCommand.php` | Sudah memanggil `$provider->status()` per instance + auto-correct opsional (sengaja tidak dijadwalkan karena manual no-op). |
| HPP riil & margin | `app/Models/SupplierPurchase.php`, `app/Services/MarginService.php`, `app/Services/SupplierCoverageService.php` | `actual_cost` per pembelian, deteksi gap coverage pelanggan vs supplier, reminder admin. Tinggal diisi otomatis oleh driver. |
| Alert admin | `app/Services/AdminAlertService.php` | WA + email, fail-safe per kanal; `digest()`/`whatsAppOnly()` siap dipakai untuk quota/balance alert. |
| Notifikasi kredensial | `app/Notifications/VpsProvisionedNotification.php` | Email + WA + database; sudah mengirim IP/port/user/password. Dipanggil dari `ProvisionVpsJob::onSuccess`. |
| Keamanan kredensial (parsial) | `app/Models/VpsInstance.php` L198 | `initial_root_password` sudah cast `encrypted`; reveal dibatasi RateLimiter 30/5menit + audit `root_password_revealed_at`. |
| Multi-tenant | `DashboardController` | Semua query di-scope `organization_id` + permission middleware (`vps.read/manage/reinstall/credentials`); tidak ditemukan kebocoran antar-org. |
| Katalog 8 paket | `database/migrations/2026_11_19_000001_sync_starter_enterprise_vps_catalog.php` | Data-only; 8 paket vps + 4 `ai_combo` + 3 `managed_db` sudah tersinkron dengan harga di brief. |

### 1.2 Gap & bug kritis (urut prioritas)

**G1 — Pipeline provisioning otomatis TIDUR (tidak pernah di-dispatch).**
`ProvisionVpsJob`, `StartVpsJob`, `StopVpsJob`, `RebootVpsJob`, `ReinstallVpsJob` punya **nol call site** di seluruh `app/`. `ProvisioningTask` tidak pernah dibuat oleh kode mana pun (hanya relasi baca di `Order`/`VpsInstance`/`DashboardController`). Alur nyata saat ini 100% manual: `AdminController@provision` (L385) menerima input IP + password admin → buat `VpsInstance` → langsung `running` → order `active` dalam satu transaksi. Jalur kedua: `AdminController@processShopeeOrder` (L904+) dan `Api/AdminApiController@provision` (L107).
→ Implikasi: menyambung driver Tencent = membangun orkestrasi dispatch, bukan sekadar menambah kelas provider.

**G2 — Tidak ada idempotency di `ProvisionVpsJob::run()` (risiko double-purchase).**
`run()` (L28–38) memanggil `$provider->provision()` tanpa syarat di setiap attempt. Aman untuk `ManualProvider` (no-op), tetapi begitu driver Tencent memanggil `CreateInstances`, retry setelah kegagalan pasca-create (mis. TAT timeout) = **beli instance kedua**. Lighthouse `CreateInstances` **tidak mendukung ClientToken** — guard wajib di sisi Cravacloud (checkpoint + adopsi via `DescribeInstances`). Lihat desain §2.2.

**G3 — Job lifecycle hilang.** Tidak ada `DestroyVpsJob`, `RenewVpsJob`, `ResetPasswordJob`, `UpgradeBundleJob`, `InstallStackJob`. `destroy()` ada di kontrak tapi tak punya job/wiring.

**G4 — Billing cron & renewal masih DB-only.**
- `BillingSuspendOverdueCommand` (L44–59): hanya `Subscription.status` + `VpsStateMachine→suspended`. Tidak ada `StopInstances`.
- `BillingTerminateExpiredCommand` (L48–65): hanya status DB. Tidak ada `IsolateInstances`/`TerminateInstances`, tidak ada pembebasan kuota.
- `RenewalService::applyRenewal()` (L178–244): hanya tanggal + status; tidak ada `RenewInstances`, tidak ada `SupplierPurchase` otomatis.
- `BillingRenewCommand` → `BillingService::renewSubscription()` (L372): generate invoice renewal — sudah benar menunggu pembayaran; aman disambung.
- `AdminController@suspendInstance/unsuspendInstance/terminateInstance` (L1399/1423/1447): DB-only juga.

**G5 — Skema DB belum punya mapping upstream & kuota pool.**
- `vps_specs`: tidak ada `upstream_bundle_id` / `upstream_bundle_type`.
- `datacenter_regions`: punya `capacity/capacity_used` per-region (bukan per pool bundle), `provider_region_id`, `metadata` JSON — **tapi tabel kosong, tidak ada seeder**. Kuota Tencent = 20 `STARTER_BUNDLE` + 20 `GENERAL_BUNDLE` per region → butuh tabel pool terpisah.
- `orders`: tidak ada `datacenter_region_id` FK & `metadata` JSON; lokasi hanya enum string `datacenter_location (singapore|indonesia)`.
- `supplier_purchases`: tidak ada `currency`, `exchange_rate`, referensi upstream, `metadata` — HPP USD Tencent butuh kurs tercatat.
- `provisioning_tasks`: cukup (JSON `input/output` bisa menampung checkpoint + InvocationId); `kind` varchar(30) bebas ditambah nilai baru.

**G6 — White-label terkunci di kode, bukan hanya data.**
`VpsSpec::allowedProviders()`/`defaultProvider()` (L109–154) **hard-code `['cravacloud']`** dan meng-override nilai DB; migrasi `2026_11_19_000002` menghapus nilai provider `tencent` dari data. `Order::operatingSystems()` hanya punya key `cravacloud`; `customerProviderLabel()` sudah netral untuk provider tak dikenal.
→ Keputusan desain: **jangan** mengubah label customer. Provider internal (`vps_instances.provider='tencent'`) dipisahkan dari label publik (`orders.provider='cravacloud'` tetap). Resolusi driver lewat config + mapping spec/region, bukan lewat `orders.provider`.

**G7 — Kredensial plaintext di beberapa kolom.**
`orders.root_password`, `orders.db_password`, `vps_instances.db_password` tanpa cast `encrypted` (hanya `vps_instances.initial_root_password` yang terenkripsi). Perlu migrasi re-enkripsi satu kali.

**G8 — Day-2 self-service belum ada.**
Tidak ada route/controller/UI untuk: power start/stop/reboot eksekusi, reset root password, VNC console, firewall CRUD, traffic quota live, upgrade paket. Yang ada: reinstall & "server unreachable" berbasis **tiket support** (`DashboardController@requestReinstall` L195 / `@reportUnreachable` L276, throttle 10/menit), reveal password (bukan reset), renew. Komentar di `routes/web.php` L268–271 menegaskan ini desain lama "beli retail tanpa API". `VpsActivityLog::getActionBadgeAttribute` sudah menyiapkan label `start/stop/reboot/force_reboot/reinstall` (belum terpakai).

**G9 — Proteksi aksi sensitif belum lengkap.**
Tidak ada `RateLimiter::for` terdefinisi; aksi admin instance & `POST /api/admin/vps/provision` tanpa throttle; tidak ada policy class (otorisasi = middleware permission + where-clause org manual — konsisten, tapi aksi power baru wajib ditambah limiter).

**G10 — OS katalog lebih luas dari blueprint terverifikasi.**
`Order::operatingSystems()` menjual 12 OS (termasuk CentOS & Windows) padahal blueprint Lighthouse `ap-jakarta` yang terverifikasi hanya Ubuntu 24.04/22.04/26.04, Debian 12/13, Rocky 9.4 (+ varian Docker). Perlu pembatasan katalog atau verifikasi blueprint tambahan.

**G11 — Queue masih `database`.**
`.env.example` `QUEUE_CONNECTION=database`. Untuk polling TAT + banyak job lifecycle, Redis + Supervisor disarankan (stack sudah menyiapkan `REDIS_*`). Bukan blocker (database queue berfungsi), tapi throughput & `withoutOverlapping` lebih sehat di Redis.

**G12 — `provider:reconcile` tidak dijadwalkan** (sengaja, komentar `routes/console.php` L110–122) — harus diaktifkan kembali saat driver live, plus sinkronisasi kuota pool.

---

## BAGIAN 2 — BLUEPRINT ARSITEKTUR LENGKAP

### 2.0 Diagram alur target (zero-touch)

```
Checkout ──► OrderController@store
              │  DB::transaction
              │  ├─ UpstreamQuotaService::assertAvailable(region, bundle_type)  [cek saja, TANPA lock]
              │  ├─ guard maks 2 order pending per user (anti-DoS, revisi 1)
              │  └─ Order(pending, provider='cravacloud', region FK)
              ▼
Bayar (Midtrans/Xendit/Lynk/QRIS/admin) ──► OrderStateMachine→paid
              ▼  DB::afterCommit
        PaymentCompletionService::handle
              ├─ renewal? ──► RenewalService ──► RenewVpsJob ──► RenewInstances(Period=N)
              └─ baru?   ──► ProvisioningOrchestrator::start(order)
                              ├─ UpstreamQuotaService::reserveHard()   [atomik DI SINI, saat paid]
                              ├─ buat VpsInstance(status=provisioning, provider='tencent')
                              ├─ buat ProvisioningTask(kind=provision)
                              └─ dispatch ProvisionVpsJob
                                    ▼
                  TencentLighthouseProvider::provision  (checkpoint per langkah)
                    1 BalanceGuard::assertSufficient(hpp)      [DescribeAccountBalance]
                    2 CreateInstances(PREPAID, Period=1, NOTIFY_AND_MANUAL_RENEW)
                      └─► SIMPAN InstanceId SEGERA (checkpoint 'created')
                    3 poll DescribeInstances → RUNNING          [checkpoint 'running']
                    4 ResetInstancesPassword (memicu reboot!)   [checkpoint 'password_set']
                      └─ poll LatestOperationState=SUCCESS && RUNNING  ['operation_success']
                    5 poll TAT DescribeAutomationAgentStatus=Online    ['agent_online']
                    6 DescribeFirewallRules → diff → CreateFirewallRules(port baru saja) ['firewall']
                    7 TatClient::runCommand(script stack)       [checkpoint 'tat_invoked' + InvocationId]
                                    ▼  onSuccess (infra siap)
                  InstallStackJob (kind=install_stack, polling ringan)
                    poll DescribeInvocationTasks → SUCCESS      [progress realtime ke task]
                    ├─ SupplierPurchase::create(actual_cost USD→IDR, kurs, upstream ref)
                    ├─ quota: reserved→used
                    ├─ VpsStateMachine→running, OrderStateMachine→active
                    └─ VpsProvisionedNotification (email+WA) + VpsActivityLog
                                    ▼
Day-2: power/reset-password/VNC/firewall/traffic/upgrade (endpoint baru, capability-gated)
Cron : billing:* ──► Stop/Isolate/Terceminate via job ──► lepas slot kuota
       provider:reconcile ──► status + sinkron kuota pool + drift alert
       tencent:check-balance ──► low-balance alert WA+email
```

### 2.1 Pilar 1 — Tencent API Client & Driver

**Keputusan: native client pakai `Http` facade, TANPA `tencentcloud/tencentcloud-sdk-php`.**
Alasan: hanya ±15 action yang dipakai; SDK menambah ratusan kelas model + dependency Guzzle terpisah; native memberi kontrol penuh atas retry, logging (redaksi kredensial), timeout, dan `Http::fake()` untuk testing. TC3 signer ditulis sekali (~80 baris) dan diuji dengan known-answer test dari dokumen resmi.

**File baru:**

```
app/Services/Provisioning/Tencent/
├── Tc3Signer.php            # TC3-HMAC-SHA256: CanonicalRequest → StringToSign → SecretDate/SecretService/SecretSigning → Authorization header
├── TencentApiClient.php     # call(service, version, action, payload, ?region): array — Http::timeout(30)->retry(3, 500) utk network-level;
│                            #   endpoint per-service; Billing tanpa header Region; log ter-redaksi (tidak pernah mencetak SecretId/Key/payload password)
├── TencentErrorMapper.php   # kode error Tencent → ProviderException(retryable, providerCode, providerResponse)
├── LighthouseClient.php     # wrapper typed: createInstances, describeInstances (filter resmi `instance-name`), startInstances,
│                            #   stopInstances, rebootInstances, isolateInstances, terminateInstances, renewInstances,
│                            #   resetInstancesPassword, resetInstance (reinstall, SINGULAR), modifyInstancesBundle,
│                            #   describeInstanceVncUrl, createFirewallRules, describeFirewallRules, deleteFirewallRules,
│                            #   describeInstancesTrafficPackages, describeBundles, describeBlueprints, describeRegions
├── TatClient.php            # runCommand, describeInvocationTasks, describeAutomationAgentStatus (wajib Online sebelum invoke)
└── BillingClient.php        # describeAccountBalance
```

**Konfigurasi (`config/services.php` + `.env.example`, nilai asli hanya di `.env`):**

```php
'tencent' => [
    'secret_id'  => env('TENCENT_SECRET_ID'),
    'secret_key' => env('TENCENT_SECRET_KEY'),
    'region'     => env('TENCENT_REGION', 'ap-jakarta'),
    'endpoints'  => [
        'lighthouse' => 'lighthouse.intl.tencentcloudapi.com',   // 2020-03-24
        'tat'        => 'tat.intl.tencentcloudapi.com',          // 2020-10-28
        'billing'    => 'billing.intl.tencentcloudapi.com',      // 2018-07-09, tanpa Region
    ],
    'low_balance_usd'   => env('TENCENT_LOW_BALANCE_USD', 20),
    'quota_warn_pct'    => env('TENCENT_QUOTA_WARN_PCT', 80),
    'balance_cache_ttl' => 60,
],
'provisioning' => ['default' => env('PROVISIONING_DRIVER', 'manual')],
```

**Mapping error (`TencentErrorMapper`):**

| Kode Tencent | retryable | Penanganan |
| :--- | :--- | :--- |
| `FailedOperation.InsufficientBalance` | **tidak** | Order→`failed` + alert admin "top-up lalu retry-provision". Pembayaran customer TIDAK dibatalkan. |
| `ResourceInsufficient` / `ResourcesSoldOut` | tidak | Alert admin + tawarkan region fallback (keputusan D4). |
| `InvalidParameterValue.*`, `InvalidParameter`, `MissingParameter` | tidak | Bug config/payload → alert admin, log payload ter-redaksi. |
| `AuthFailure.*`, `UnauthorizedOperation` | tidak | Alert darurat kredensial/permission. |
| `OperationDenied.*` (mis. bundle downgrade) | tidak | Tolak dengan pesan netral ke customer. |
| `RequestLimitExceeded` | ya | Backoff job (sudah ada) + jitter di client. |
| `InternalError`, `ServerError`, timeout/network | ya | Retry transient. |
| `FailedOperation.InstanceNotRunning`, state tidak sesuai | ya | Poll/wait lalu retry (bounded). |
| TAT agent belum siap / `InvalidInvocationTask.*` transient | ya | Retry terbatas; setelah N kali → failed + alert. |

**Driver & kontrak kapabilitas (backward-compatible dengan `ManualProvider`):**

```php
// app/Services/Provisioning/Providers/TencentLighthouseProvider.php
class TencentLighthouseProvider implements
    VpsProviderContract,
    SupportsStackInstall,   // installStack(instance, task): array  — invoke TAT + return invocation id
    SupportsPasswordReset,  // resetPassword(instance, task, string $newPassword): array
    SupportsVncConsole,     // vncUrl(instance): string  — on-demand, TIDAK di-cache
    SupportsFirewall,       // listFirewallRules / addFirewallRules / deleteFirewallRules
    SupportsTrafficQuota,   // trafficUsage(instance): array{used_bytes,total_bytes,percent}
    SupportsRenewal,        // renew(instance, task, int $periodMonths): array
    SupportsUpgrade         // upgradeBundle(instance, task, string $targetBundleId): array
{ ... }
```

Interface kapabilitas terpisah di `app/Services/Provisioning/Contracts/`. Controller/Job mengecek `instanceof` → fitur otomatis disabled untuk instance `manual` (tanpa mengubah `ManualProvider` sama sekali). `ProviderManager::createTencentDriver()` diregistrasi; fallback manual tetap.

**White-label:** `vps_instances.provider = 'tencent'` (internal, hanya admin). `orders.provider` tetap `'cravacloud'`; `VpsSpec::allowedProviders()` TIDAK diubah. Semua `current_step`/pesan task memakai bahasa netral ("Menyiapkan server", "Memasang stack", dst). `provider_meta`, `output` task, bundle id, dan nama endpoint **tidak boleh** bocor ke response customer (`provisioning-status.json` hanya mengembalikan `progress`, `current_step`, `status`).

### 2.2 Pilar 1b — Idempotency anti double-purchase (WAJIB, desain inti)

Lighthouse `CreateInstances` tanpa ClientToken → guard 3 lapis:

1. **Checkpoint persisten per langkah.** `TencentLighthouseProvider::provision()` menulis `task.output['checkpoint']` + `instance_id` **segera setelah setiap langkah sukses** (bukan di akhir). Nilai: `created → running → password_set → operation_success → agent_online → firewall → tat_invoked → tat_done`.
2. **Resume, bukan ulang.** Awal `provision()`: jika `instance.provider_resource_id` ATAU `task.output['instance_id']` sudah ada → **lewati `CreateInstances`**, lanjut dari checkpoint berikutnya. `ProvisionVpsJob::run()` tidak berubah signature — guard hidup di dalam provider sehingga semua jalur retry (job retry, admin retry-provision, dispatch ganda) terlindungi.
3. **Adopsi orphan untuk kegagalan ambigu.** Jika request `CreateInstances` timeout/network-error (respons tak diketahui): sebelum mencoba create lagi, panggil `DescribeInstances` dengan **`Filters: [{"Name": "instance-name", "Values": ["crava-{order_id}-{instance_id}"]}]`** (nama deterministik; nama filter resmi `instance-name`, bukan `InstanceName`). Ketemu → adopsi (simpan id, lanjut). Tidak ketemu → baru create. `ProviderReconcileCommand` ditambah deteksi orphan (instance bernama `crava-*` tanpa pasangan DB) → alert admin, jangan auto-terminate.

**Dua tahap job (menghindari timeout 600s saat install stack besar):**
- `ProvisionVpsJob` (existing, dimodifikasi ringan): infra saja (create→running→password→firewall→invoke TAT). `onSuccess`: jika stack ≠ `none` dan provider `SupportsStackInstall` → order TETAP `provisioning`, dispatch `InstallStackJob`; jika tidak → finalisasi seperti sekarang.
- `InstallStackJob` (baru, `kind=install_stack`): poll `DescribeInvocationTasks` (backoff job existing; tiap attempt cek status lalu return/throw retryable — attempt pendek, bukan sleep panjang). Sukses → finalisasi (state running/active, `SupplierPurchase`, notifikasi). Gagal permanen → instance `error`, order `failed`, alert admin; **retry TAT aman** (script idempotent + instance sudah ada).
- Timeout `InstallStackJob` dinaikkan (mis. 900s) dan `tries` ditambah untuk stack berat (Dify+Ollama bisa >15 menit).

### 2.3 Pilar 2 — 1-Click Auto-Install Engine (TAT)

**File baru:**
```
app/Services/Provisioning/StackInstallerService.php     # orkestrasi: pilih blueprint, rakit script, invoke TAT, poll
app/Services/Provisioning/Stacks/StackDefinition.php    # value object: script, ports[], needsDocker, credentials, appUrlTemplate
app/Services/Provisioning/Stacks/StackRegistry.php      # map kode stack (Order::stackLabels) → StackDefinition
resources/provisioning/stacks/*.sh                      # template bash idempotent (blade-style placeholder {{...}})
```

**Pemilihan BlueprintId otomatis:**
- Stack berbasis Docker (coolify, dokploy, docker, managed_database, n8n, dify_ollama, ollama, anythingllm, librechat, vscode_server, gitea_forgejo, uptime_kuma, netdata_beszel, wordpress, ghost, strapi_directus, prestashop_bagisto, claude_opencode, agent_zero, openclaw, hermes_agent, omniroute, 9router) → **`lhbp-2cacsycc`** (Ubuntu 24.04 + Docker 29).
- Pure OS / stack ringan non-Docker (none, aapanel, cloudpanel, cyberpanel, hestiacp) → **`lhbp-1l4ptuvm`** (Ubuntu 24.04).
- OS lain dipetakan via config `tencent.blueprints`: `ubuntu2204→lhbp-qnuz61zs`, `ubuntu2604→lhbp-qg4ymn4o`, `debian12→lhbp-mxml4cnq`, `debian13→lhbp-36w1bvaq`, `rocky94→lhbp-8g1z9f6a`. OS tanpa blueprint terverifikasi (CentOS, Windows, Debian 10/11) → **ditolak saat checkout** untuk order baru (keputusan D2).

**Kontrak script bash (semua stack):**
```bash
#!/usr/bin/env bash
set -euo pipefail
MARKER=/var/lib/cravacloud/.installed-{{stack}}
[ -f "$MARKER" ] && { echo "already installed"; exit 0; }   # idempotent — retry TAT aman
exec > >(tee -a /var/log/cravacloud-install.log) 2>&1
export DEBIAN_FRONTEND=noninteractive
# 1. apt update + dependensi dasar  2. install stack (docker compose / installer resmi)
# 3. hardening dasar (ufw allow port stack, fail2ban opsional)  4. tulis kredensial app ke /root/.crava-credentials (chmod 600)
mkdir -p /var/lib/cravacloud && touch "$MARKER"
echo "CRAVA_INSTALL_OK {{stack}}"
```
- Stack proxy OmniRoute/9Router: script meng-set outbound proxy `43.173.51.90:20129` (env/config aplikasi), bukan membuka port inbound.
- Managed DB: install engine via Docker (mysql:8 / postgres / redis), buat database+user sesuai `orders.db_name/db_user`, bind + password kuat, port sesuai `db_port`; CloudBeaver bila `db_manager=cloudbeaver` (port 8080/8443).
- Kredensial root **tidak pernah** lewat TAT — via `ResetInstancesPassword` (langkah 4 provisioning). Kredensial app (panel admin, DB user) di-generate di dalam server; opsi delivery lihat keputusan D6.

**Port firewall otomatis per stack (`CreateFirewallRules` setelah RUNNING):**

| Stack | Port inbound |
| :--- | :--- |
| none / docker | 22 |
| coolify | 22, 80, 443, 8000 |
| dokploy | 22, 80, 443, 3000 |
| cloudpanel | 22, 80, 443, 8443 |
| aapanel | 22, 80, 443, 8888 |
| cyberpanel / hestiacp | 22, 80, 443, 8090 / 8083 |
| managed_database | 22 + db_port (3306/5432/6379/27017/6333) + 8443 (CloudBeaver) |
| n8n | 22, 5678 (+80/443 bila reverse proxy) |
| dify_ollama / ollama | 22, 80, 443 |
| vscode_server | 22, 8080 |
| gitea_forgejo | 22, 3000 |
| uptime_kuma | 22, 3001 |
| netdata_beszel | 22, 19999, 8090 |
| hermes_agent / openclaw / agent_zero / claude_opencode | 22, 80, 443 |
| omniroute / 9router | 22 (outbound proxy, tanpa port tambahan) |

**Urutan provisioning wajib (revisi 2 & 3 — state/agent gate sebelum TAT):**
1. `CreateInstances` → simpan InstanceId (checkpoint).
2. Poll `DescribeInstances` → `RUNNING`.
3. `ResetInstancesPassword` — **pada VM RUNNING ini memicu shutdown+reboot**. Wajib poll `DescribeInstances` sampai **`LatestOperationState == SUCCESS` && `LatestOperation == ResetInstancesPassword` && state `RUNNING`** kembali.
4. Poll **`TatClient::describeAutomationAgentStatus` → `Online`** (agent bisa offline sesaat setelah reboot).
5. `DescribeFirewallRules` → **diff** → `CreateFirewallRules` hanya port yang belum terbuka (revisi 5; hindari error duplikat 22/80/443 yang sudah ada di default rule).
6. `RunCommand` (baru boleh setelah langkah 3–4 beres).

**Default user & SSH (revisi 3):** image Ubuntu Lighthouse memakai default user **`ubuntu`**, bukan `root`. `ResetInstancesPassword` mereset password default user tersebut. Maka:
- `vps_instances.ssh_username` di-set per blueprint: Ubuntu → `ubuntu`; Debian/Rocky → user default image (diverifikasi saat uji live Phase 3; peta disimpan di config `tencent.default_users`).
- Notifikasi kredensial (`VpsProvisionedNotification` sudah memakai `$instance->ssh_user`) otomatis benar; instruksi `sudo -i` untuk akses root disertakan di email/WA.
- Opsi root-login langsung: script TAT non-secret meng-set `PermitRootLogin prohibit-password` (key-only) — password root tidak pernah dikirim via TAT; login password tetap via default user. Keputusan final soal root-login penuh menyusul hasil uji live.

**Payload TAT `RunCommand` (revisi 2):**
```json
{
  "Region": "ap-jakarta",
  "CommandName": "crava-install-{{stack}}-{{order_id}}",
  "Content": "<base64 script>",
  "CommandType": "SHELL",
  "Timeout": 3600,
  "WorkingDirectory": "/root",
  "InstanceIds": ["lhins-xxxx"],
  "EnableParameter": true,
  "Parameters": "{\"db_password\": \"...\"}"
}
```
- `EnableParameter: true` **wajib** bila memakai `Parameters`; value `Parameters` adalah **JSON string** (bukan objek), dan placeholder di script memakai format `{{db_password}}`.
- Sesuai D6: hindari password plaintext di riwayat TAT — panel (Coolify/Dokploy) pakai first-run setup; managed DB (fase berikutnya, D3 hold) memakai password sekali-pakai yang wajib dirotasi setelah serah terima, atau di-generate di server tanpa lewat Parameters.
Poll `DescribeInvocationTasks(InvocationId)` → `InvocationTaskBasicInfoSet[].TaskStatus` (`RUNNING/SUCCESS/FAILED/TIMEOUT`), `TaskResult` (base64 output) disimpan terpotong ke `task.output` untuk debugging admin.

**Progress realtime:** `StackInstallerService` meng-update `ProvisioningTask.progress/current_step` per fase (10 create, 30 running, 45 password, 55 firewall, 60 invoke, 60–95 poll install, 100 selesai) — endpoint `dashboard.vps.provisioning-status.json` yang sudah ada langsung menampilkan progres tanpa perubahan UI besar.

### 2.4 Pilar 3 — Quota Pool Guard & Multi-Region

**Tabel baru `upstream_quota_pools`** (kuota Tencent per region × bundle_type, bukan per-region saja):

```php
Schema::create('upstream_quota_pools', function (Blueprint $t) {
    $t->id();
    $t->string('region_code', 30);            // ap-jakarta, ap-singapore, ...
    $t->string('bundle_type', 30);            // STARTER_BUNDLE | GENERAL_BUNDLE
    $t->unsignedInteger('capacity')->default(20);
    $t->unsignedInteger('reserved')->default(0);  // checkout pending + provisioning berjalan
    $t->unsignedInteger('used')->default(0);      // instance hidup (running/stopped/suspended/shutdown)
    $t->timestamps();
    $t->unique(['region_code', 'bundle_type']);
});
```

**`app/Services/UpstreamQuotaService.php` (revisi 1 — anti-DoS, tanpa lock 24 jam):**
- **Checkout (`pending`): TIDAK ada hard-reserve.** Hanya `assertAvailable(region, bundleType)` (baca `reserved + used < capacity`) + **batas maks 2 order pending per user** (query count existing). Order pending TTL 24 jam (`ExpirePendingOrdersCommand`) tidak boleh menyandera slot.
- **Saat `paid` (ProvisioningOrchestrator, sebelum dispatch job): reservasi keras atomik** —
  `UPDATE upstream_quota_pools SET reserved = reserved + 1 WHERE region_code=? AND bundle_type=? AND reserved + used < capacity` → affected-rows 0 = penuh. Gagal reservasi saat sudah paid → order ditahan `paid` + alert admin (pilihan region lain / refund manual oleh admin — D4: tidak auto-fallback).
- `commit(region, bundleType)`: reserved−1, used+1 (sa provisioning sukses).
- `releaseReserved(...)`: reserved−1 (provisioning gagal permanen / order cancelled setelah paid). `releaseUsed(...)`: used−1 (terminate sukses).
- `syncFromProvider(region)`: `DescribeInstances` paginated → hitung riil per bundle type → koreksi `used` + deteksi drift → alert. Dipanggil `ProviderReconcileCommand` (dijadwalkan ulang tiap 15 menit) & `tencent:sync-quota`.
- Early warning: pemakaian ≥ `quota_warn_pct` (80% = 16/20) → `AdminAlertService::digest/whatsAppOnly` (dedupe via cache key per pool per hari).

**Integrasi checkout:** `OrderController@store` (dan jalur Shopee/API) memanggil `assertAvailable()` + cek 2-pending-per-user dalam transaksi pembuatan order; penuh → tolak checkout dengan pesan netral + **tawarkan pilihan region lain yang masih longgar (D4: hard-stop + pilihan, bukan auto-fallback diam-diam)**. `ExpirePendingOrdersCommand` tidak perlu release (pending tidak memegang slot). `DatacenterRegionSeeder` baru mengisi `ap-jakarta` (primary) & `ap-singapore` (fallback, nonaktif default) dengan `provider='tencent'`, `provider_region_id`, `metadata.pools`. Mapping `orders.datacenter_location`: `indonesia→ap-jakarta`, `singapore→ap-singapore` (kolom FK baru `orders.datacenter_region_id`).

**Mapping spec → bundle (kolom baru `vps_specs.upstream_bundle_id`, `upstream_bundle_type`):**

| Paket | BundleId | Pool |
| :--- | :--- | :--- |
| Starter XS / S / M / L | `bundle_starter_nmc_lin_med2_01` / `med4_01` / `med8_01` / `med8_02` | STARTER_BUNDLE |
| Enterprise Pro / Max / Ultra / Extreme | `bundle_gen_nmc_lin_lg8_01` / `lg16_01` / `2xlg16_01` / `2xlg32_01` | GENERAL_BUNDLE |
| ai_combo (4 paket) & managed_db (3 paket) | **HOLD (D3 final): TIDAK dipetakan / tidak auto-provision.** Harga jual seeder saat ini rugi terhadap HPP Tencent. Tetap alur manual sampai pricing direvisi Ryan. | — |

`NEWCOMER_BUNDLE` tidak dipakai. Hard-stop checkout bila pool penuh; **hanya 8 paket VPS yang aktif auto-provisioning (D3)** — spec tanpa mapping (`ai_combo`, `managed_db`) tetap manual.

### 2.5 Pilar 4 — Deposit Balance Guard & Financial Safety

**`app/Services/Provisioning/Tencent/BalanceGuard.php`** (atau `app/Services/TencentBalanceGuard.php`):
- `balanceUsd()`: `BillingClient::describeAccountBalance()` + `Cache::remember('tencent:balance', 60s)`. **Unit respons (sen vs dolar) diverifikasi dulu lewat `tencent:probe`** sebelum dipakai menghitung — jangan diasumsikan.
- `assertSufficient(float $hppUsd)`: dipanggil (a) awal provisioning (cache boleh), (b) **tepat sebelum `CreateInstances` dengan cache di-bypass** (fresh). Kurang → `ProviderException(retryable: false, providerCode: 'InsufficientBalance')`.
- Cron baru `tencent:check-balance` (hourly): saldo < `low_balance_usd` (default $20) ATAU < HPP order pending terendah → alert WA+email via `AdminAlertService` (dedupe harian, escalate bila makin turun).
- **Safe failure saat customer sudah bayar:** order → `failed` dengan `failure_reason` jelas + `AdminProvisioningFailedNotification` (sudah ada) + pesan WA "saldo upstream kurang, top-up lalu klik Retry Provision". Tombol `admin.orders.retry-provision` + transisi `failed→provisioning` **sudah ada** — dipakai ulang. Pembayaran customer tidak pernah dibatalkan/di-refund diam-diam; instance tidak dibuat; slot kuota di-release.
- HPP riil: setelah create sukses, `SupplierPurchase::create(['supplier'=>'tencent', 'supplier_order_no'=>InstanceId, 'actual_cost'=>hargaBundleUSD×kurs, 'currency'=>'IDR', 'exchange_rate'=>kurs, 'supplier_expires_at'=>+1 bulan, 'metadata'=>{bundle_id, price_usd, region}])`. Kurs dari config/setting `tencent_usd_idr` (di-update admin; default referensi). `MarginService` & `SupplierCoverageService` langsung bekerja tanpa perubahan.

### 2.6 Pilar 5 — Full Lifecycle Automation

Prinsip: **cron tetap penentu kebijakan (DB), job yang mengeksekusi API** — cron dispatch job, tidak memanggil API inline (tahan gagal, ada retry & audit task).

| Peristiwa | Cron/Service existing | Eksekusi baru |
| :--- | :--- | :--- |
| Renewal dibayar | `PaymentCompletionService→RenewalService::handleRenewalPayment` (idempoten `renewal_applied_at`) | Setelah DB commit → dispatch **`RenewVpsJob`** → `RenewInstances(InstanceIds, InstanceChargePrepaid{Period: months, RenewFlag: NOTIFY_AND_MANUAL_RENEW})` hanya untuk instance `provider='tencent'`. Sukses → `SupplierPurchase` renewal + `supplier_expires_at` baru. Gagal saldo → alert admin + flag `provider_meta.renew_pending`, cron retry harian; masa aktif customer TETAP diperpanjang (janji customer independen dari upstream). |
| Unsuspend / recover | `RenewalService` set `running` di DB | **Revisi 4:** instance `SHUTDOWN` (recycle bin) TIDAK bisa `StartInstances`. Alur: `RenewInstances` dulu (recover + perpanjang) → poll `RUNNING` → **sync ulang `public_ip` dari `DescribeInstances` (IP publik bisa berubah setelah recover)** → update instance + notifikasi customer bila IP berubah → baru `StartVpsJob` bila masih `STOPPED`. |
| Grace habis → suspend | `BillingSuspendOverdueCommand` | Dispatch **`StopVpsJob`** → `StopInstances`. Instance masuk `suspended` DB setelah job sukses (state transisi dipindah ke onSuccess job, bukan di cron). |
| Suspend lama → terminate | `BillingTerminateExpiredCommand` (suspend > 30 hari) | Dispatch **`DestroyVpsJob`** → **2 tahap wajib**: `IsolateInstances` (bila belum `SHUTDOWN`) → poll → `TerminateInstances` → `UpstreamQuotaService::releaseUsed()` → state `terminated`. |
| Reconcile | `ProviderReconcileCommand` | Aktifkan jadwal 15 menit; tambah sync kuota pool + deteksi orphan + drift `supplier_expires_at`. |

**⚠️ Kebijakan retensi (temuan desain penting — keputusan D5):** dengan `NOTIFY_AND_MANUAL_RENEW`, instance Tencent yang tidak diperpanjang akan di-isolate otomatis oleh Tencent saat masa prepaid habis dan **dihapus permanen setelah masa recycle bin** (retensi Lighthouse perlu diverifikasi via probe/docs — umumnya 7 hari, bukan 30). Artinya jendela "suspended 30 hari baru terminate" milik Cravacloud **lebih panjang dari umur data di upstream** untuk VPS. Desain aman:
- `DestroyVpsJob` menghitung deadline = `min(suspended_at + 30h, upstream_expires_at + retensi_upstream − 1h)`;
- `RenewalService::SUPPLIER_LIMITS['vps']=7` sudah konsisten dengan retensi 7 hari — grace customer (5 hari) harus dijaga ≤ retensi upstream;
- janji retensi data ke customer untuk produk tencent diselaraskan (copy ToS/notifikasi) — **perlu persetujuan Ryan**.

### 2.7 Pilar 6 — Day-2 Self-Service Endpoints & Security

**Route customer baru** (group `dashboard/vps/{id}` yang sudah ada, middleware `auth,2fa,org.context` + permission + throttle baru):

| Method/URI | Aksi | Provider call | Proteksi |
| :--- | :--- | :--- | :--- |
| POST `.../power` `{action: start|stop|reboot}` | dispatch Start/Stop/RebootVpsJob | StartInstances / StopInstances / RebootInstances | `permission:vps.manage`, `throttle:vps-power` (10/menit/user+instance), guard state, konfirmasi UI |
| POST `.../reset-password` | generate password kuat → **`ResetPasswordJob`** → `ResetInstancesPassword` (force stop bila diwajibkan API) | `ResetInstancesPassword` | `permission:vps.credentials`, limiter 3/jam, audit + notifikasi WA/email, cast encrypted |
| GET `.../console` | **on-demand** `DescribeInstanceVncUrl` → redirect/JSON langsung ke browser | harus `RUNNING`; URL 15 detik single-use | `permission:vps.manage`, limiter 10/menit, **tidak di-cache, tidak masuk log/response lain**, audit akses |
| GET/POST/DELETE `.../firewall` | CRUD rule instance | DescribeFirewallRules / CreateFirewallRules / DeleteFirewallRules | `permission:vps.manage`, limiter 20/menit, validasi port/CIDR, audit per perubahan |
| GET `.../traffic.json` | bar kuota live | DescribeInstancesTrafficPackages | `permission:vps.read`, cache 60s |
| POST `.../upgrade` `{target_spec}` | validasi **upgrade-only** (cpu/ram/disk/bandwidth target ≥ sumber) → invoice selisih → setelah paid dispatch **`UpgradeBundleJob`** | ModifyInstancesBundle | `permission:vps.manage`, limiter 5/jam; downgrade ditolak (arahkan ke `replacement_spec_id` flow EOL yang sudah ada) |

**Admin:** mirror endpoint di group `/admin/instances/{id}/...` (tanpa ownership, tetap `admin` middleware + throttle baru + `AdminAuditLog`). UI `admin/instances.blade.php` & `dashboard/show.blade.php` ditambah panel Day-2; tombol lama (tiket reinstall/unreachable) tetap ada sebagai fallback untuk instance `manual` dan saat provider error. Reinstall otomatis: `DashboardController@requestReinstall` di-upgrade — untuk instance tencent, alur tiket tetap dibuat (audit) tapi job reinstall + install-stack langsung di-dispatch; `ReinstallVpsJob` memanggil **`ResetInstance`** (singular, dengan `BlueprintId` pilihan) lalu poll `LatestOperationState` → TAT ulang (agent Online dulu).

**Multi-tenant & audit:** semua query tetap `where('organization_id', ...)` + `findOrFail`; setiap aksi menulis `VpsActivityLog` via `VpsInstance::logActivity()` (badge action sudah tersedia) dan `ProvisioningTask` (kind terkait) untuk aksi async; `RateLimiter::for('vps-power'|'vps-sensitive'|'vps-console')` didefinisikan di `AppServiceProvider`/`RouteServiceProvider`. Response publik tidak pernah memuat `provider`, `provider_meta`, bundle id, atau nama Tencent.

### 2.8 Pilar 7 — Testing & Rollout Aman

**Unit:**
- `Tc3SignerTest`: known-answer vector resmi TC3 (signature deterministik dari contoh dokumen Tencent).
- `TencentErrorMapperTest`: tiap kode error → retryable/non-retryable benar.
- `UpstreamQuotaServiceTest`: reserve atomik saat sisa 1 slot + 2 checkout paralel → tepat 1 sukses; commit/release; sync koreksi drift.
- `StackRegistryTest`: semua kode `Order::stackLabels()` punya definition + port + blueprint; tidak ada stack yatim.

**Feature (`Http::fake()`):**
- Provision sukses end-to-end: urutan CreateInstances→DescribeInstances→ResetPassword→Firewall→RunCommand→InvocationTasks; order active; `SupplierPurchase` tercatat; notifikasi terkirim; label customer tetap `cravacloud`.
- **Retry idempotent:** attempt 1 create sukses lalu TAT gagal → attempt 2 `Http::assertSentCount(CreateInstances) === 1` (resume dari checkpoint).
- Timeout ambigu → `DescribeInstances` by name menemukan orphan → adopsi, bukan create kedua.
- Quota penuh → checkout ditolak, tidak ada order tercipta; alert 80% terkirim sekali.
- `InsufficientBalance` setelah paid → order failed, pembayaran utuh, alert admin, retry-provision setelah saldo cukup → sukses.
- Renewal hanya setelah invoice paid; `RenewInstances` terpanggil sekali (idempoten).
- Terminate 2 tahap (Isolate dulu) + slot kuota bebas.
- VNC: URL tidak di-cache (2 klik = 2 panggilan API), instance non-running ditolak.
- Upgrade downgrade ditolak; upgrade sukses setelah invoice selisih paid.
- Regression: `ManualProvider` + alur admin manual existing tetap lulus (suite lama tidak diubah).

**Diagnostic command:** `php artisan tencent:probe {--dry-run} {--live}`
- `--dry-run` (default): cek env terisi (tanpa mencetak nilai), signer self-test, resolve config region/bundle/blueprint, cek mapping semua spec aktif.
- `--live`: read-only API — `DescribeRegions`, `DescribeBundles` (cocokkan 8 BundleId + harga), `DescribeBlueprints` (cocokkan 7 BlueprintId), `DescribeAccountBalance` (**verifikasi unit saldo**), TAT `DescribeCommands` ringan. Output tabel PASS/FAIL per item. Wajib lulus sebelum flip driver.

**Rollout:** `PROVISIONING_DRIVER=manual` tetap sampai Phase 5; flip per-spesifikasi via `vps_specs.default_provider` internal? — **tidak**: flag rollout pakai setting `provisioning_auto_enabled` + whitelist spec id (canary 1 paket, mis. Starter XS), sehingga rollback = matikan setting tanpa deploy. Queue pindah Redis + Supervisor (`queue:work redis --queue=provisioning,default --tries=5`) sebelum canary.

### 2.9 Rancangan migrasi DB minimal (6 migrasi + 1 seeder)

1. `add_upstream_mapping_to_vps_specs` — `upstream_bundle_id` (string 60, nullable), `upstream_bundle_type` (string 30, nullable). Data mapping 8 paket via migrasi data terpisah (pola `2026_11_19_000001`).
2. `create_upstream_quota_pools` — skema §2.4 + seed 2 pool × region aktif.
3. `add_region_and_metadata_to_orders` — `datacenter_region_id` FK nullable, `metadata` JSON nullable.
4. `add_upstream_fields_to_supplier_purchases` — `currency` (8, default IDR), `exchange_rate` (decimal 12,4 nullable), `upstream_reference` (string 120 nullable), `metadata` JSON nullable.
5. `encrypt_existing_plaintext_credentials` — ubah cast `orders.root_password`, `orders.db_password`, `vps_instances.db_password` → `encrypted` + re-enkripsi data lama satu kali (chunk, idempotent, deteksi nilai yang sudah terenkripsi). **Butuh persetujuan D10.**
6. `seed_datacenter_regions` (seeder) — `ap-jakarta` aktif, `ap-singapore` nonaktif, `provider='tencent'`, `metadata.pools`.
7. `provisioning_tasks` — **tanpa perubahan skema** (checkpoint & InvocationId di `output` JSON; `kind` varchar menampung `install_stack/renew/reset_password/upgrade`).

### 2.10 Daftar file baru vs dimodifikasi (ringkas)

**Baru (±24 file):** 7 kelas Tencent client (§2.1), 7 interface kapabilitas, `TencentLighthouseProvider`, `StackInstallerService` + `StackRegistry` + `StackDefinition` + ±20 script `.sh`, `UpstreamQuotaService`, `BalanceGuard`, `ProvisioningOrchestrator`, 5 job baru (`InstallStackJob`, `RenewVpsJob`, `DestroyVpsJob`, `ResetPasswordJob`, `UpgradeBundleJob`), 3 command (`TencentProbeCommand`, `TencentCheckBalanceCommand`, `TencentSyncQuotaCommand`), `Dashboard/VpsActionController` + `Admin/VpsActionController`, 6 migrasi + 1 seeder, ±12 file test.

**Dimodifikasi (±15 file, perubahan bedah):** `ProviderManager` (+1 method), `config/services.php`, `.env.example`, `ProvisionVpsJob` (handoff 2 tahap), `PaymentCompletionService` (+hook orchestrator, guarded), `RenewalService` (+dispatch job), `BillingSuspendOverdueCommand` & `BillingTerminateExpiredCommand` (+dispatch job), `ProviderReconcileCommand` (+sync kuota), `routes/console.php` (+3 jadwal), `routes/web.php` & `routes/api.php` (+route Day-2), `OrderController` (+quota guard), `ExpirePendingOrdersCommand` (+release), `VpsSpec`/`Order`/`VpsInstance`/`SupplierPurchase` (fillable/cast/relasi baru), `DashboardController` + 2 blade (UI Day-2), `AppServiceProvider` (RateLimiter).

---

## BAGIAN 3 — ROADMAP EKSEKUSI BERTAHAP (tiap fase terisolasi & bisa dites sendiri)

| Fase | Lingkup | Gate selesai (definisi done) | Risiko uang |
| :--- | :--- | :--- | :--- |
| **Phase 1 — Fondasi client** | Tc3Signer, TencentApiClient, ErrorMapper, 3 client wrapper, config/env, `tencent:probe --dry-run/--live`, unit test signer+mapper. Driver tetap `manual`. | Probe live PASS semua item (bundle, blueprint, saldo, TAT); suite test hijau; nol perubahan perilaku produksi. | Nol (read-only API). |
| **Phase 2 — Katalog & guard** | Migrasi 1–4 + seeder region, mapping 8 bundle, UpstreamQuotaService + guard checkout (mode observe dulu: log tanpa menolak), BalanceGuard + cron alert, whitelist spec canary. | Checkout existing lulus regression; quota sync cocok dengan jumlah instance riil akun; alert 80%/low-balance teruji (fake). | Nol. |
| **Phase 3 — Provisioning inti** | TencentLighthouseProvider (provision + checkpoint idempotency), ProvisioningOrchestrator + hook PaymentCompletion, InstallStackJob + TAT + script 4 stack awal (none, docker, coolify, cloudpanel — **managed_db HOLD sesuai D3**), SupplierPurchase otomatis, feature test Http::fake lengkap. | Semua feature test lulus; **uji live 1 instance Starter XS ($4.20) HANYA setelah konfirmasi ulang eksplisit dari Ryan sebelum `CreateInstances` real (D12 final)**: create→install→notifikasi→terminate bersih, retry tidak membeli ganda. | 1 instance test (~$4.20 + terminate), atas izin ulang. |
| **Phase 4 — Lifecycle** | RenewVpsJob, wiring suspend/terminate 2 tahap, DestroyVpsJob + release kuota, reconcile terjadwal + orphan detection, penyelarasan retensi (D5). | Uji live siklus: renew instance test, suspend→stop terbukti di console Tencent, terminate 2 tahap + slot bebas; dry-run cron di staging. | Renew 1 bulan instance test (~$4.20). |
| **Phase 5 — Day-2 & rollout** | ResetPasswordJob, VNC on-demand, firewall CRUD, traffic bar, upgrade flow + invoice selisih, UI customer+admin, rate limiter, sisa stack (bertahap per grup), aktifkan semua jadwal, flip canary → penuh. | Feature test Day-2 lulus; uji manual tiap tombol di instance test; canary 1 paket 3–7 hari tanpa insiden → buka semua paket; rollback = setting off. | Instance test bertahan 1 siklus. |

Estimasi kasar: Phase 1–2 cepat (fondasi), Phase 3 inti terbesar, Phase 4–5 menyusul. Tiap fase = PR terpisah, suite test hijau sebelum merge, tanpa migrasi destruktif.

---

## BAGIAN 4 — KEPUTUSAN FINAL D1–D13 (dijawab Ryan 2026-11-19)

- **D1 ✓** Native `Http` client, tanpa SDK.
- **D2 ✓** Katalog OS dibatasi ke 7 blueprint Linux terverifikasi `ap-jakarta`.
- **D3 ✋ HOLD `ai_combo` & `managed_db`** — harga jual seeder sekarang rugi terhadap HPP Tencent. Auto-provisioning **hanya 8 paket VPS** (Starter XS–L, Enterprise Pro–Extreme). Keduanya tetap manual sampai pricing direvisi.
- **D4 ✓** Hard-stop + tawarkan pilihan region; tidak ada auto-fallback diam-diam.
- **D5 ✓** Selaraskan retensi dengan recycle bin upstream. **Final Phase 4 (Lampiran D)**: `recycle_bin_retention_days=15`, `terminate_safety_days=12` (dapat diubah via env, tanpa angka 7 hardcode); terminate aman memakai `min(deadline pelanggan, supplier_expires_at + safety)`.
- **D6 ✓** First-run setup untuk panel (Coolify/Dokploy). Managed DB: jangan simpan password plaintext di riwayat TAT — rotasi/hash aman wajib.
- **D7 ✓** Redis + Supervisor sebelum canary Phase 3/5.
- **D8 ✓** Upgrade prorata selisih (margin dari selisih HPP upstream); downgrade via `replacement_spec_id`.
- **D9 ✓** Threshold $20 + guard HPP order; kurs USD→IDR manual via Settings admin.
- **D10 ✓** Migrasi re-enkripsi kredensial plaintext (chunked, idempotent) di Phase 2.
- **D11 ✓** Arsitektur 2 tahap (`ProvisionVpsJob` + `InstallStackJob`).
- **D12 ✓ Phase 1 & 2 saja dulu (read-only / Rp0).** Uji live berbayar Phase 3 ($4.20) **wajib konfirmasi ulang ke Ryan sebelum `CreateInstances` real**.
- **D13 ✓** Canary per-spesifikasi via setting; Starter XS pertama.

Tabel rekomendasi awal (riwayat, sudah difinalkan di atas):

| # | Keputusan | Rekomendasi |
| :--- | :--- | :--- |
| **D1** | Native `Http` client vs SDK `tencentcloud/tencentcloud-sdk-php`? | **Native** (lebih ringan, terkontrol, mudah di-fake; hanya ±15 action). |
| **D2** | Katalog OS: batasi ke 7 blueprint terverifikasi (Ubuntu 24.04/22.04/26.04, Debian 12/13, Rocky 9.4) dan hapus CentOS/Windows/Debian 10–11 dari checkout baru? | **Batasi.** Windows butuh blueprint & lisensi terpisah (di luar scope Lighthouse Linux saat ini). |
| **D3** | Mapping bundle untuk 4 paket `ai_combo` + 3 `managed_db` (belum ada di brief). Usulan: Terminal Coding Agent→Starter XS; Cloud AI Workstation→Starter S; Hermes Hub→Starter M; Private AI&RAG→Starter L / General sesuai RAM; DB Micro→Starter XS; DB Standard→Starter S; DB Enterprise/Vector→Starter M. | Setujui usulan atau kirim mapping final (harga jual vs HPP harus tetap margin positif). |
| **D4** | Pool penuh saat checkout: hard-stop saja, auto-fallback `ap-singapore`, atau tawarkan pilihan region ke customer? | **Hard-stop + tawarkan pilihan** (auto-fallback diam-diam mengubah lokasi data customer — berisiko kepatuhan/latensi). |
| **D5** | Retensi data: jendela terminate Cravacloud (suspend 30 hari) lebih panjang dari recycle bin upstream (verifikasi ~7 hari setelah expired). Selaraskan janji retensi customer (grace vps 5 hari + suspend pendek) atau terima risiko data hilang lebih awal? | **Selaraskan** untuk produk tencent: terminate dipercepat mengikuti deadline upstream; update copy ToS + notifikasi. Angka retensi pasti diverifikasi di Phase 1. |
| **D6** | Delivery kredensial app (panel admin/DB user): (a) via TAT `Parameters` (tercatat di riwayat TAT akun kita), (b) generate di server + customer set sendiri saat first-run (Coolify/Dokploy mendukung), (c) ambil via TAT lalu simpan terenkripsi + rotasi. | **(b) bila stack mendukung, (a) untuk managed DB** dengan kebijakan rotasi password setelah serah terima. Root password tetap via `ResetInstancesPassword` + notifikasi existing. |
| **D7** | Queue: pindah ke Redis + Supervisor sekarang (Phase 3) atau tetap database queue dulu? | **Redis sebelum canary** (polling TAT + job lifecycle banyak; `.env` sudah menyiapkan REDIS_*). |
| **D8** | Upgrade paket: harga selisih prorata sisa masa aktif, atau siklus baru penuh? Downgrade hanya via `replacement_spec_id` (flow EOL existing)? | **Prorata selisih** (ModifyInstancesBundle menagih selisih ke saldo kita — margin harus dihitung dari selisih HPP upstream, bukan harga jual penuh); downgrade = replacement flow. |
| **D9** | Threshold saldo: $20 default + alert bila < HPP order masuk. Kurs USD→IDR untuk `SupplierPurchase`: setting manual admin (update berkala) atau API kurs otomatis? | **$20 + HPP guard**; kurs manual via Settings dulu (sederhana, auditable), otomatis menyusul bila perlu. |
| **D10** | Migrasi re-enkripsi `orders.root_password`, `orders.db_password`, `vps_instances.db_password` (data lama plaintext)? | **Ya**, satu kali, chunked + idempotent, di Phase 2. Tanpa ini kredensial baru tetap aman tapi data lama terbuka. |
| **D11** | Arsitektur 2 tahap (ProvisionVpsJob infra + InstallStackJob TAT) vs 1 job panjang? | **2 tahap** (attempt pendek, retry aman, progress realtime, tidak menabrak timeout worker). |
| **D12** | Persetujuan uji live berbayar: 1× Starter XS `ap-jakarta` (~$4.20) di Phase 3, renew 1 bulan di Phase 4, lalu terminate bersih? Saldo akun harus cukup sebelum tiap fase. | **Setujui** dengan budget eksplisit; semua uji live dijalankan manual atas perintah Ryan, bukan otomatis oleh agent. |
| **D13** | Flip driver: canary per-spesifikasi via setting `provisioning_auto_enabled` + whitelist (rollback tanpa deploy) — paket canary pertama Starter XS? | **Ya.** `PROVISIONING_DRIVER=tencent` global baru setelah canary stabil 3–7 hari. |

---

*Blueprint disetujui dengan 5 revisi (terapkan di atas). Phase 1–2 berjalan; tidak ada `CreateInstances` real sebelum konfirmasi ulang D12. Kredensial tidak pernah dicetak ke output/log/commit.*
---

## Lampiran A — Hasil Eksekusi Phase 1 (2026-11-19)

**Status: SELESAI — semua cek PASS, Rp0, tanpa perubahan infrastruktur upstream.**

### A.1 Artefak yang dibuat/diubah

| File | Status | Isi |
| :--- | :--- | :--- |
| `app/Services/Provisioning/Tencent/Tc3Signer.php` | baru | TC3-HMAC-SHA256 native, lolos vektor resmi |
| `app/Services/Provisioning/Tencent/TencentApiClient.php` | baru | transport POST JSON, tanpa auto-retry transport, log tanpa payload/kredensial |
| `app/Services/Provisioning/Tencent/TencentErrorMapper.php` | baru | retryable vs non-retryable; unknown = non-retryable (konservatif) |
| `app/Services/Provisioning/Tencent/LighthouseClient.php` | baru | 18 wrapper termasuk `resetInstance` (singular), filter `instance-name`, `renewInstances` prepaid |
| `app/Services/Provisioning/Tencent/TatClient.php` | baru | runCommand, describeInvocationTasks, describeAutomationAgentStatus, describeCommands |
| `app/Services/Provisioning/Tencent/BillingClient.php` | baru | describeAccountBalance (region-less) |
| `app/Providers/ProvisioningServiceProvider.php` | ubah | singleton Tc3Signer/TencentApiClient/Lighthouse/Tat/Billing |
| `config/services.php` | ubah | blok `tencent`: endpoints, versions, regionless, blueprints (7), default_users, threshold |
| `.env.example` | ubah | placeholder `PROVISIONING_DRIVER`, `TENCENT_*` (tanpa nilai) |
| `.env` (gitignored) | ubah | kredensial dimuat silent dari CSV; nilai tidak pernah dicetak |
| `app/Console/Commands/TencentProbeCommand.php` | baru | `tencent:probe {--live} {--region=}` |
| `tests/Unit/Tc3SignerTest.php` | baru | 11 test (vektor resmi: canonical `7019a55b…`, signature `644be983…`) |
| `tests/Unit/TencentErrorMapperTest.php` | baru | 5 test |
| `tests/Feature/TencentApiClientTest.php` | baru | 15 test (Http::fake, header, region-less billing, error mapping, tanpa retry transport) |

### A.2 Hasil test

- Test baru: **31 passed (69 assertions)**.
- Suite penuh: **486 passed, 3 failed** — ketiganya **pre-existing** (diverifikasi dengan stash perubahan Phase 1: tetap gagal tanpa perubahan ini): `NomorWhatsAppTest` (nomor publik) dan 2× `PaymentVerificationTest` (QRIS payload placeholder). Bukan regresi integrasi Tencent; di luar scope Phase 1.

### A.3 Hasil probe live (read-only, Rp0)

| Cek | Hasil |
| :--- | :--- |
| DescribeRegions | PASS — `ap-jakarta` tersedia (11 region) |
| DescribeBundles | PASS — 8/8 bundle, harga live **persis brief**: 4.2 / 6 / 10 / 12.5 / 36 / 54 / 78 / 105 USD |
| DescribeBlueprints | PASS — 7/7 blueprint terverifikasi |
| DescribeInstances | PASS — TotalCount=0 (baseline bersih, belum ada instance) |
| DescribeAccountBalance | PASS auth — **Balance=0, RealBalance=0** (lihat A.4) |
| DescribeCommands (TAT) | PASS — auth TAT OK (36 command tersimpan di akun) |

### A.4 Temuan baru & catatan untuk Phase 2–3

1. **Quirk API baru**: filter `bundle-id` pada `DescribeBundles` menolak terlalu banyak Values sekaligus (`InvalidParameter.FilterValueLimitExceeded`). Probe sudah diperbaiki: chunk per 5 nilai. Kode error ini sudah tercakup non-retryable (`InvalidParameter*`).
2. **Saldo akun = 0.** Verifikasi unit (sen vs dolar) belum bisa disimpulkan dari nilai 0 — ditunda sampai saldo non-zero. **Top-up wajib sebelum uji berbayar Phase 3** ($4.20 Starter XS + margin uji renew/upgrade). BalanceGuard (D9) memakai hasil verifikasi unit ini.
3. **D5 retensi recycle bin**: angka pasti (~7 hari) tidak bisa dibaca via API tanpa instance SHUTDOWN nyata → verifikasi digeser ke Phase 3 (saat uji terminate). Scheduler retensi jangan hardcode angka ini dulu.
4. **Kredensial**: ada dua CSV di Downloads (`SecretKeyTencentCloud.csv` 17 Nov, `SecretKeyTencentCloud2.csv` 19 Nov). Yang dimuat: **file terbaru (2)** — auth terverifikasi tembus Lighthouse + TAT + Billing. Loader one-shot dihapus setelah pakai; nilai tidak pernah muncul di output/log/git.
5. **Queue masih `database`** — D7 (Redis + Supervisor) tetap gate wajib sebelum canary Phase 3.
6. `PROVISIONING_DRIVER` tetap `manual` — belum ada perubahan perilaku provisioning production. Flip driver hanya via canary D13 setelah Phase 2–3 lolos gate.

## Lampiran B — Hasil Eksekusi Phase 2 (2026-11-19)

**Status: SELESAI — guardrail kuota, saldo, canary gate, dan D10 aktif. Rp0, seluruh panggilan upstream read-only. Perilaku provisioning production belum berubah (observe mode + auto-provision OFF).**

### B.1 Artefak yang dibuat/diubah

| File | Status | Isi |
| :--- | :--- | :--- |
| `database/migrations/2026_11_19_100001…100007` (7 file) | baru | mapping upstream di `vps_specs`; data-map 8 bundle; tabel `upstream_quota_pools` (unique region+type, seed ap-jakarta ×2); `orders.datacenter_region_id` + `metadata`; `supplier_purchases.currency/exchange_rate/upstream_reference/metadata` (D9); seed 3 setting guard; pelebaran kolom kredensial ke TEXT (prasyarat D10) |
| `database/seeders/DatacenterRegionSeeder.php` | baru | region ap-jakarta (aktif) & ap-singapore (nonaktif), provider `tencent`, metadata pools; backfill whitelist canary |
| `database/seeders/VpsSpecSeeder.php`, `SettingsSeeder.php`, `DatabaseSeeder.php` | ubah | mapping bundle + setting guard ikut ter-seed di DB fresh (migrasi data berjalan sebelum seeder) |
| `app/Models/UpstreamQuotaPool.php` | baru | `availableSlots()`, `usagePercent()` |
| `app/Models/VpsSpec.php` | ubah | fillable mapping + `hasUpstreamMapping()` |
| `app/Models/Order.php`, `VpsInstance.php` | ubah | **D10: cast `encrypted`** untuk `root_password`/`db_password`; fillable region+metadata; relasi `datacenterRegion()` |
| `app/Models/SupplierPurchase.php` | ubah | fillable/casts field upstream (D9) |
| `app/Services/UpstreamQuotaService.php` | baru | observe/enforce, `reserveHard` atomik (conditional UPDATE), commit/release, `syncFromProvider` (LIVE_STATES termasuk SHUTDOWN), anti-DoS max 2 pending/user, alternatif region white-label, warn ≥80% + alert harian dedupe |
| `app/Services/Provisioning/Exceptions/QuotaExhaustedException.php`, `PendingOrderLimitException.php` | baru | pesan pelanggan netral (tanpa nama vendor) |
| `app/Services/Provisioning/Tencent/BalanceGuard.php` | baru | saldo USD (divisor configurable, cache 60s, `fresh` bypass), `assertSufficient` non-retryable `InsufficientBalance` |
| `app/Services/Provisioning/ProvisioningGate.php` | baru | **D13**: `allows() = autoEnabled && hasUpstreamMapping && whitelist` — default OFF |
| `app/Console/Commands/TencentSyncQuotaCommand.php` | baru | `tencent:sync-quota {--region=}` — koreksi `used` dari DescribeInstances + drift alert |
| `app/Console/Commands/TencentCheckBalanceCommand.php` | baru | `tencent:check-balance` — threshold dinamis (low_balance vs HPP pending terpetakan), dedupe harian + eskalasi |
| `app/Console/Commands/CredentialsReencryptCommand.php` | baru | `credentials:reencrypt {--dry-run} {--chunk=}` — konversi plaintext legacy via raw DB (bypass cast), idempoten, nilai tak pernah dicetak |
| `OrderController.php`, `AdminController.php`, `Api/AdminApiController.php`, `LynkPaymentProcessor.php` | ubah | guard checkout: web = observe/enforce sesuai setting; kanal marketplace (Shopee/Lynk) = **observe-only** (pembayaran captured eksternal; titik enforce di `reserveHard` saat paid); `datacenter_region_id` terisi saat checkout |
| `routes/console.php` | ubah | `tencent:sync-quota` tiap 15 menit, `tencent:check-balance` hourly (Asia/Jakarta, withoutOverlapping, onOneServer) |
| `config/services.php`, `.env.example` | ubah | `balance_divisor` (default 100, unit menunggu verifikasi saldo non-zero), `bundle_prices_usd` (8 harga terverifikasi) |
| `tests/Feature/UpstreamQuotaServiceTest.php` (19), `CheckoutQuotaGuardTest.php` (6), `BalanceGuardTest.php` (8), `CredentialReencryptTest.php` (6), `ProvisioningGateTest.php` (5) | baru | 44 test Phase 2 |

### B.2 Hasil test

- Test baru Phase 2: **44 passed** (quota 19, checkout guard 6, balance 8, re-enkripsi 6, gate 5).
- Suite penuh: **530 passed, 3 failed (3550 assertions, ~40s)** — 3 kegagalan **identik dengan baseline pre-existing** (`NomorWhatsAppTest` + 2× `PaymentVerificationTest` QRIS placeholder). **Nol regresi.** (+44 dari baseline 486.)

### B.3 Eksekusi di DB lokal (MySQL)

| Langkah | Hasil |
| :--- | :--- |
| `php artisan migrate` | 7 migrasi DONE (100001–100007) |
| `credentials:reencrypt --dry-run` → riil | **0 dikonversi, 0 sudah terenkripsi, 0 kosong** di ketiga kolom — DB lokal tidak punya kredensial legacy; cast encrypted aktif untuk tulisan baru |
| `db:seed --class=DatacenterRegionSeeder` | ap-jakarta aktif, ap-singapore nonaktif; 4 pool (2 region × 2 tipe) capacity 20, reserved 0, used 0 |
| Verifikasi mapping | **8/8 paket VPS ter-mapping** (Starter XS/S/M/L → `bundle_starter_nmc_lin_med2_01/med4_01/med8_01/med8_02`; Enterprise Pro/Max/Ultra/Extreme → `bundle_gen_nmc_lin_lg8_01/lg16_01/2xlg16_01/2xlg32_01`); **7 paket ai_combo/managed_db tetap unmapped (D3 HOLD)** |
| Verifikasi setting | `quota_guard_mode=observe`, `provisioning_auto_enabled=false`, `provisioning_auto_spec_whitelist=[14]` (Starter XS). Catatan operasional: setelah migrasi/seed setting, **flush cache SettingsService** agar nilai baru terbaca |

### B.4 Verifikasi live (read-only, Rp0)

- `tencent:sync-quota`: 4 pool diperiksa — Used (DB) 0 = Used (riil) 0 di semua pool, **tanpa drift, tanpa alert** (konsisten dengan TotalCount=0 akun).
- `tencent:probe`: **PASS 11/11** (kredensial SET tanpa nilai, signer vektor resmi, 7 blueprint, error mapper, 8 kandidat auto-provision, queue=database).
- Scan leak: tidak ada prefix kredensial di seluruh file git-tracked.
- `tencent:check-balance` **tidak dijalankan live** (saldo 0 akan memicu alert WA/email riil ke admin); perilaku alert teruji penuh via fake di `BalanceGuardTest` (dedupe harian, eskalasi, threshold HPP pending $105).

### B.5 Keputusan operasional & gate menuju Phase 3

1. **Observe mode aktif (default).** Guard checkout hanya mencatat log `quota.checkout_observe`, tidak menolak. Flip ke `enforce` cukup ubah setting `quota_guard_mode` — tanpa deploy. Kanal marketplace selalu observe; penegakan kuota terjadi di `reserveHard()` saat order paid (Revisi 1).
2. **Auto-provision tetap OFF** (`provisioning_auto_enabled=false`, driver `manual`). `ProvisioningGate` sudah terpasang dan teruji; orchestrator Phase 3 wajib melewatinya. Rollback = matikan switch (teruji).
3. **Saldo akun masih $0** — top-up wajib sebelum uji berbayar Phase 3 ($4.20 Starter XS). Verifikasi unit balance (divisor 100) tetap tertunda sampai saldo non-zero (A.4.2).
4. **D12 berlaku**: eksekusi `CreateInstances` riil di Phase 3 **menunggu konfirmasi ulang eksplisit dari Ryan**. D7 (Redis + Supervisor) tetap gate wajib sebelum canary.
5. Scheduler baru (`sync-quota` 15 menit, `check-balance` hourly) aktif di `routes/console.php`; di production butuh cron `schedule:run` yang sudah ada — tanpa perubahan infrastruktur.

---

## Lampiran C — Hasil Eksekusi Phase 3 (Provisioning Inti & Auto-Install TAT)

**Tanggal:** 19 November 2026 · **Mode: STRICT Rp0 — seluruh pengujian via `Http::fake()`, nol panggilan API riil, nol `CreateInstances` nyata.**

### C.1 Artefak kode

| Artefak | Status | Isi |
| :--- | :--- | :--- |
| `app/Services/Provisioning/Providers/TencentLighthouseProvider.php` | **BARU** | Implementasi `VpsProviderContract` + `SupportsStackInstall` + `SupportsPasswordReset` + `SupportsFirewall`. Idempotency 3 lapis (checkpoint persisten `created → running → password_set → operation_success → agent_online → firewall → tat_invoked → tat_done`; resume via `provider_resource_id`/`task.output['instance_id']`; adopsi orphan `DescribeInstances` filter `instance-name` dengan nama deterministik `crava-{order_id}-{instance_id}`). Urutan aman: create → RUNNING → `ResetInstancesPassword` (UserName per OS) → poll `LatestOperationState=SUCCESS && RUNNING` → gate agent TAT `Online` → firewall diff → `RunCommand`. Polling bounded dalam satu attempt; lewat budget → `ProviderException` **retryable** (`PollTimeout`) sehingga job resume dari checkpoint, bukan gagal permanen |
| `app/Services/Provisioning/ProviderManager.php` | diubah | `createTencentDriver()` (auto-wire container); fallback manual kini di-log (`provisioning.provider_fallback_manual`) agar downgrade diam-diam terlihat. `ManualProvider` **tidak disentuh** |
| `app/Services/Provisioning/Contracts/SupportsStackInstall.php`, `SupportsPasswordReset.php`, `SupportsFirewall.php` | **BARU** | Kontrak kapabilitas opsional per provider |
| `app/Services/Provisioning/Stacks/StackDefinition.php`, `StackRegistry.php` | **BARU** | 4 stack: `none` (port 22), `docker` (22), `coolify` (22/80/443/8000, app URL `http://{ip}:8000`), `cloudpanel` (22/80/443/8443, `https://{ip}:8443`). Pemilihan blueprint D2: stack docker di ubuntu2404 → `lhbp-2cacsycc`; OS lain → map blueprint; OS tak dikenal → null → tetap manual. `ai_combo`/`managed_db` **tidak terdaftar (D3 HOLD)** |
| `app/Services/Provisioning/StackInstallerService.php` + `app/Services/Provisioning/Tencent/ResolvesRegion.php` | **BARU** | Render script → TAT `RunCommand` (Content base64, SHELL, Timeout 3600, WorkingDirectory `/root`); poll ringan `DescribeInvocationTasks` satu round-trip per attempt; substitusi placeholder non-secret saja (`{{stack}}/{{order_id}}/{{hostname}}`) — **D6: tanpa secret lewat TAT**, panel pakai first-run setup |
| `resources/provisioning/stacks/docker.sh`, `coolify.sh`, `cloudpanel.sh` | **BARU** | Bash idempotent: `set -euo pipefail`, marker `/var/lib/cravacloud/.installed-{stack}` (re-run = exit 0), log ke `/var/log/cravacloud-install.log`, guard keberadaan sebelum install, penutup `CRAVA_INSTALL_OK {stack}` |
| `app/Services/Provisioning/ProvisioningOrchestrator.php` | **BARU** | Rantai keputusan saat paid: `ProvisioningGate::allows()` → `reserveHard()` atomik → buat instance+task → dispatch `ProvisionVpsJob`. Pool penuh → **hold order paid + alert admin, tanpa auto-fallback (D4)**. Idempoten (instance/task hidup → skip). Gagal setup setelah reservasi → `releaseReserved()` + order kembali ke alur manual |
| `app/Services/PaymentCompletionService.php` | diubah | Hook `maybeAutoProvision()` setelah notifikasi lunas, hanya order baru, terbungkus try/catch — kegagalan tidak mengganggu penyelesaian pembayaran. Gate OFF → **nol perubahan perilaku** (teruji) |
| `app/Services/Provisioning/ProvisioningFinalizer.php` | **BARU** | Finalisasi bersama 2 tahap: detail instance + app URL panel, `SupplierPurchase` (D9: HPP USD × setting `tencent_usd_idr`, currency IDR, `upstream_reference=InstanceId`, expired +1 bulan, idempoten per reference), settlement kuota idempoten (`task.output['quota_settled']`), transisi running/active, `VpsProvisionedNotification`. Gagal permanen: instance ada di upstream → `commit()` (slot riil terpakai, reconcile mengoreksi); tanpa instance → `releaseReserved()` |
| `app/Jobs/Provisioning/ProvisionVpsJob.php` | diubah | Tahap 1 infra. Sukses + `stack_install_pending` → buat task `install_stack` + dispatch `InstallStackJob` (detail server dipersist dulu agar dashboard menampilkan IP saat install berjalan); tanpa stack → finalisasi langsung. `onFailure` delegasi ke finalizer |
| `app/Jobs/Provisioning/InstallStackJob.php` | **BARU** | Tahap 2 (D11): satu poll `DescribeInvocationTasks` per attempt, `tries=60`, `timeout=120`, backoff [10,20,30,60,60,120] — antrean yang menyediakan jeda, worker tidak tidur panjang. RUNNING → retryable `InstallPending`; SUCCESS → finalisasi; FAILED/TIMEOUT → gagal permanen. Edge resume tanpa invocation id → invoke sekali lalu poll |
| `app/Services/Provisioning/Tencent/LighthouseClient.php` | diubah | `resetInstancesPassword(..., ?string $userName)` — UserName eksplisit per OS |
| `config/services.php` | diubah | `default_users`: **ubuntu→`ubuntu`, debian→`root`, rocky→`root`** (instruksi Phase 3 Ryan; sebelumnya debian→ubuntu). Baru: `poll_interval_seconds` (`TENCENT_POLL_INTERVAL`, default 5) & `poll_max_seconds` (`TENCENT_POLL_MAX`, default 240); interval 0 = tanpa sleep (tes) |
| `app/Http/Controllers/DashboardController.php` | diubah | White-label: `provisioningStatus` menyensor `error` mentah provider non-manual menjadi pesan netral; detail teknis tetap di kolom error untuk admin |
| `database/migrations/2026_11_19_100008_seed_tencent_usd_idr_setting.php` + `SettingsSeeder` | **BARU** | Setting `tencent_usd_idr` (integer, default 16500, group provisioning, sort 93) — kurs dikelola admin tanpa deploy |
| `.env.example` | diubah | `TENCENT_POLL_INTERVAL=5`, `TENCENT_POLL_MAX=240` |
| `tests/Feature/TencentAutoProvisioningTest.php` | **BARU** | 11 feature test, 119 assertions, seluruhnya `Http::fake()` |

### C.2 Hasil test

Test baru Phase 3 — **11 passed (119 assertions)**, memetakan seluruh skenario yang diminta:

| # | Test | Skenario |
| :--- | :--- | :--- |
| 1 | `happy_path_two_stage_coolify_from_paid_to_active` | Paid → orchestrator (reserve 1 slot) → job infra (checkpoint `tat_invoked`) → task `install_stack` → poll RUNNING lalu SUCCESS → running/active, `SupplierPurchase` 4.20 USD × 16500 = **Rp69.300** IDR, kuota commit (reserved 0/used 1), notifikasi customer, app URL `http://{ip}:8000`. `CreateInstances` **tepat 1×**; firewall diff hanya membuat port **8000** (22/80/443 sudah default, termasuk rule koma `80,443`); `UserName=ubuntu`; agent Online sebelum `RunCommand`; re-run job selesai = no-op (purchase & used tetap 1) |
| 2 | `retry_resumes_from_checkpoint_and_never_recreates_instance` | Timeout ambigu setelah create → checkpoint `running` persisten + `provider_resource_id` tersimpan langsung; attempt 2 resume tanpa create/adopsi ulang; `CreateInstances` tetap **1×**, reset password dicoba 2× |
| 3 | `orphan_adopted_by_name_after_ambiguous_create_timeout` | Create timeout ambigu (instance mungkin jadi) → attempt 2 menemukan orphan via filter resmi `instance-name` = `crava-{order}-{instance}` → **adopsi, tidak membeli lagi** (0 create sukses) |
| 4 | `insufficient_balance_fails_safely_payment_intact_and_reservation_released` | Cache saldo basi tinggi di-bypass `fresh:true`; saldo 0 → gagal non-retryable **sebelum** `CreateInstances` (0 panggilan); **pembayaran utuh** (`paid_at` & amount tetap), reservasi dilepas (0/0), alert admin terkirim, order failed + instance error |
| 5 | `full_pool_holds_paid_order_with_alert_and_no_auto_fallback` | Pool penuh → `reserveHard` false → order **ditahan di paid** + digest admin "pool penuh"; tanpa job, tanpa instance, **tanpa fallback** ke ap-singapore (D4) |
| 6 | `customer_outputs_never_leak_vendor_name` | Label publik = merek sama untuk semua provider; email kredensial ter-render + pesan WA + seluruh JSON endpoint polling **bebas kata "tencent"/"lighthouse"**; error mentah `[lighthouse/CreateInstances] TencentCloud...` disensor jadi pesan netral |
| 7 | `gate_off_keeps_manual_flow_unchanged` | Gate OFF → paid tetap paid, tanpa job/instance/reservasi/panggilan upstream; notifikasi pembayaran normal |
| 8 | `unmapped_spec_never_auto_provisions_even_when_enabled` | Spec ai_combo unmapped di-whitelist + switch ON → tetap manual (**D3 HOLD** terjaga di lapisan gate) |
| 9 | `manual_provider_still_finalizes_without_upstream_calls` | Regression alur manual admin: task provider manual → running/active + notifikasi, tanpa `SupplierPurchase`, tanpa reservasi, tanpa panggilan upstream |
| 10 | `stack_install_failure_marks_failed_and_commits_occupied_slot` | TAT FAILED → order failed + instance error + alert admin; karena instance upstream sudah ada, slot **commit** (used 1), bukan release; customer tidak dinotifikasi sukses |
| 11 | `debian_order_resets_root_password_not_ubuntu` | OS debian12 → `UserName=root` + blueprint `debian12` (bukan image docker) |

**Suite penuh: 541 passed, 3 failed (3669 assertions, ~41s).** 3 kegagalan **identik dengan baseline pre-existing** (`NomorWhatsAppTest` nomor publik + 2× `PaymentVerificationTest` QRIS payload placeholder). **Nol regresi** (+11 dari 530).

Catatan teknis tes: satu `Http::fake()` delegasi per test (memanggil `Http::fake()` ulang menghapus riwayat recorded); request yang gagal di level transport (ConnectionException) tidak tercatat di `Http::recorded()` — penghitungan memakai counter handler; aksi tak terduga melempar exception agar panggilan liar mustahil lolos.

### C.3 Eksekusi di DB lokal (MySQL)

| Langkah | Hasil |
| :--- | :--- |
| `php artisan migrate` | Migrasi `100008` DONE — setting `tencent_usd_idr=16500` masuk (idempoten, nilai admin tidak ditimpa) |
| Verifikasi wiring (tinker, tanpa API) | `ProviderManager->driver('tencent')` resolve `TencentLighthouseProvider` dengan 4 kontrak; default driver tetap `manual` |
| Verifikasi setting | `provisioning_auto_enabled=false`, `quota_guard_mode=observe`, `tencent_usd_idr=16500` — **semua gate tetap OFF sesuai instruksi** |

### C.4 Kepatuhan & gate menuju Phase 4

1. **STRICT Rp0 terpenuhi**: tidak ada `CreateInstances` riil maupun panggilan live apa pun di Phase 3; seluruh verifikasi lewat `Http::fake()` + wiring container. Uji live 1× Starter XS ($4.20) tetap **sekali tembak setelah Phase 3–5 selesai**, dengan konfirmasi ulang eksplisit Ryan (D12) + top-up saldo (masih $0) + verifikasi unit divisor balance.
2. **Konfigurasi tidak berubah**: `PROVISIONING_DRIVER=manual`, `provisioning_auto_enabled=false`, `quota_guard_mode=observe`. Aktivasi canary nanti = flip setting + whitelist, tanpa deploy; rollback = matikan switch.
3. **Perubahan perilaku yang disengaja** (di luar itu nol): (a) `default_users.debian` ubuntu→root sesuai instruksi Phase 3; (b) error task provider non-manual disensor di endpoint polling customer (white-label); (c) fallback provider manual kini menulis log warning.
4. **D7 tetap gate wajib sebelum canary**: Redis + Supervisor untuk queue production (tes memakai sync + eksekusi job manual).
5. Fase berikutnya: **Phase 4** (lifecycle: start/stop/reboot/reinstall/destroy/renew + rekonsiliasi) lalu **Phase 5** (Day-2: firewall UI, reset password, monitoring, fulfillment board).

---

## Lampiran D — Hasil Eksekusi Phase 4 (Lifecycle Automation) + Patch A Code Review Phase 3

Tanggal: 2026-11-19 · Mode: **STRICT Rp0** — seluruh pengujian memakai `Http::fake()`; tidak ada satu pun panggilan API mutasi riil (`CreateInstances`/`RenewInstances`/`TerminateInstances`/`StopInstances`/`IsolateInstances`/`StartInstances`) ke upstream.

### D.1 Patch wajib code review Phase 3 (Patch A)

| Temuan | Perbaikan | Artefak |
| :--- | :--- | :--- |
| **A1** `root_password` plaintext bocor ke `provisioning_tasks.output` | Password tidak pernah ditulis ke output task; `AbstractProvisioningJob::stripSensitive()` menghapus kunci kredensial (`root_password`, `password`, `db_password`, `secret`, `secret_key`) di top-level maupun `meta` sebelum `markSucceeded()`. Kredensial hanya hidup terenkripsi di `vps_instances.initial_root_password` | `app/Jobs/Provisioning/AbstractProvisioningJob.php`, `ProvisionVpsJob.php` |
| **A2** `failed()` dipanggil worker setelah `handle()` sudah gagal permanen → `onFailure` dobel (alert ganda, settle ganda) | Guard `if ($task->status === 'failed') return;` di awal `failed()` | `AbstractProvisioningJob.php` |
| **A3a** Flag `quota_settled` hanya di `task.output` → task retry baru bisa commit/release dobel | Flag dipindah ke `order.metadata['quota_settled']` (satu order = satu settlement), fallback baca flag lama di `task.output` untuk task legacy | `app/Services/Provisioning/ProvisioningFinalizer.php` |
| **A3b** Kombinasi OS × stack tidak tervalidasi sebelum `reserveHard()` → slot ter-reserve untuk kombinasi yang mustahil di-install | `StackRegistry::has()` + `supportsOs()` + `blueprintIdFor()` dicek orchestrator SEBELUM reservasi: cloudpanel hanya Ubuntu 22/24 & Debian 12; docker keluarga Ubuntu; coolify ubuntu2204/2404 + debian12/13. Kombinasi tak didukung → tetap alur manual HOLD, bukan kegagalan palsu | `StackRegistry.php`, `ProvisioningOrchestrator.php` |
| **A3c** `markProgress(80)` terpanggil padahal instalasi stack masih pending | Progress 80 dilewati bila `stack_install_pending`; stage install mulai di 65 | `ProvisionVpsJob.php` |

Regresi Patch A: 3 test baru di `TencentAutoProvisioningTest` (total **14 test / 152 assertion hijau**) — assert output task bebas password, `failed()` dobel tidak memicu alert kedua (`assertSentOnDemandTimes(..., 1)`), flag `order.metadata` mencegah double-commit (termasuk fallback legacy), dan cloudpanel+debian13 / docker+debian12 tetap manual tanpa reservasi.

### D.2 Phase 4B — Renewal upstream (renew + recovery recycle bin)

| Artefak | Isi |
| :--- | :--- |
| `config/services.php` + `.env.example` | **B1 (D5)**: `recycle_bin_retention_days` default **15** (`TENCENT_RECYCLE_BIN_RETENTION_DAYS`), `terminate_safety_days` default **12** (`TENCENT_TERMINATE_SAFETY_DAYS`). Tidak ada angka 7 hardcode di jalur terminate |
| `app/Services/Provisioning/Contracts/SupportsRenewal.php` (baru) | Kontrak `renew(VpsInstance, ProvisioningTask, int $months): array` |
| `TencentLighthouseProvider::renew()` | `RenewInstances` dengan `InstanceChargePrepaid: {Period: months, RenewFlag: 'NOTIFY_AND_MANUAL_RENEW'}` (auto-renew Supplier tetap OFF). **Idempoten**: `renew_before_expired_time` (ExpiredTime upstream) dipersist SEBELUM invokasi; retry membandingkan ExpiredTime terkini > before → skip pembelian dobel. Recovery **SHUTDOWN hanya via RenewInstances** (bukan StartInstances), poll sampai RUNNING/STOPPED; STOPPED → `StartInstances` + poll RUNNING; **re-sync public_ip/private_ip** pasca recovery (Revisi 4). Balance guard `fresh:true` di luar blok idempotensi agar retry pasca-gagal-saldo tetap cek saldo |
| `app/Jobs/Provisioning/RenewVpsJob.php` (baru) | kind `renew`. Sukses: `applyInstanceDetails` (IP resync) → VPS running (`allowSame`) → **SupplierPurchase renewal** idempoten per order (reference `{InstanceId}-RENEW-{orderId}`, `supplier_order_no` `AUTO-…`, `supplier_expires_at` dari ExpiredTime riil upstream, fallback expiry pembelian terakhir + months, biaya USD × `tencent_usd_idr`) → clear flag `renew_pending`. Gagal saldo (`insufficient`): `provider_meta['renew_pending']=true` + alert admin `digest('Renewal upstream tertunda — saldo tidak cukup')`, **masa aktif pelanggan tetap utuh**, instance tidak dibalik ke error. Gagal lain: alert, state dipertahankan (reconcile yang menilai ulang) |
| `app/Services/RenewalService.php` | Akhir `applyRenewal`: khusus `provider === 'tencent'` + ada `provider_resource_id` → `DB::afterCommit()` membuat task `renew` + dispatch `RenewVpsJob`. **Periode pelanggan commit dulu, upstream menyusul** — kegagalan upstream tidak pernah membatalkan hak pelanggan. Dispatch gagal → log + alert admin. Alur manual tidak berubah |
| Unsuspend admin | `VpsLifecycleDispatcher::unsuspend()` query state upstream: STOPPED → `StartVpsJob`; SHUTDOWN/ISOLATING → `RenewVpsJob` (input `unsuspend: true`, months 1) |

### D.3 Phase 4C — Suspend & terminate aman dua tahap

| Artefak | Isi |
| :--- | :--- |
| `app/Jobs/Provisioning/DestroyVpsJob.php` (baru) | kind `destroy`. Provider `destroy()`: describe → bila bukan SHUTDOWN/TERMINATED → `IsolateInstances` → poll SHUTDOWN → `TerminateInstances`. Sukses: **`releaseUsed()` idempoten** via flag `provider_meta['quota_released']` → VPS `terminated` (`allowSame`) → order `terminated`. Gagal: alert admin, TIDAK ada transisi terminated, slot TIDAK dilepas |
| `app/Services/Provisioning/VpsLifecycleDispatcher.php` (baru) | Pemakai bersama cron billing + AdminController. `supports()` = tencent + ada resource id (manual tidak pernah masuk sini). `suspend()`: instance sudah `stopped` → transisi langsung tanpa API; selain itu task `stop` dengan `target_status: suspended`. Guard `hasLiveTask()` per kind → cron ulang tidak menumpuk job |
| `StopVpsJob.php` | `onSuccess` membaca `input['target_status']` (whitelist stopped/suspended) → transisi dengan `allowSame`; gagal suspend → alert admin |
| `BillingSuspendOverdueCommand.php` | Tencent: stop upstream dulu via dispatcher; status `suspended` di-set SETELAH job sukses. Manual: transisi langsung (perilaku lama identik) |
| `BillingTerminateExpiredCommand.php` | **Deadline aman = `min(updated_at + suspend-days, supplier_expires_at + terminate_safety_days)`** — mana yang LEBIH DULU, supaya penghapusan terkendali terjadi sebelum upstream menghapus paksa. Tanpa pembelian tercatat → fallback deadline pelanggan (perilaku lama). Tencent → `DestroyVpsJob` (VPS/order terminated setelah sukses); manual → langsung |
| `AdminController.php` (suspend/unsuspend/terminate) | Cabang tencent di awal masing-masing method → dispatcher + `logActivity(status: pending)` + pesan netral "sedang diproses"; error ditangkap → `back()->with('error')`. Jalur manual di bawahnya tidak berubah sedikit pun |

### D.4 Phase 4D — Rekonsiliasi & scheduler

| Artefak | Isi |
| :--- | :--- |
| `ProviderReconcileCommand.php` (ditulis ulang, signature tetap) | **No-op penuh** bila tidak ada instance non-manual (nol panggilan API di deployment manual). Per instance: skip bila ada task lifecycle live (provision/install/renew/destroy/stop/start) agar tidak balapan dengan job; sync status + **re-sync public/private IP**; `--auto-correct` transisi via state machine; **drift `supplier_expires_at` vs ExpiredTime upstream** (toleransi 2 hari) → alert dedupe harian. Lalu `syncFromProvider()` (koreksi `used` dari hitungan riil + alert drift kuota). Terakhir **scan orphan `crava-*`** per region pool: instance upstream bernama crava- yang tidak ada di DB → log + alert admin dedupe harian, **TIDAK PERNAH auto-terminate** (keputusan manusia) |
| `routes/console.php` | `provider:reconcile --auto-correct` **aktif tiap 15 menit** `withoutOverlapping()->onOneServer()` (timezone Asia/Jakarta). Jadwal terpisah `tencent:sync-quota` DIHAPUS — sudah subsumed ke reconcile agar tidak dobel beban API (command tetap tersedia manual). `tencent:check-balance` hourly tetap |
| `tests/Feature/VpsServiceRequestTest.php` | Test jadwal lama (`provider:reconcile` tidak boleh terjadwal — asumsi Phase 1) diperbarui ke kebijakan kini: reconcile TERJADWAL, `maintenance:sync` tetap tidak, `tencent:sync-quota` tidak terjadwal terpisah |

### D.5 Hasil test (semua STRICT Rp0, `Http::fake()`)

File baru: `tests/Feature/TencentLifecycleAutomationTest.php` — **10 test / 91 assertion, semua hijau**:

| # | Skenario | Yang dibuktikan |
| :--- | :--- | :--- |
| 1 | Renewal normal via jalur pembayaran riil | Periode pelanggan diperpanjang DULU (order active), `RenewInstances` tepat 1× (`Period=1`, `RenewFlag=NOTIFY_AND_MANUAL_RENEW`), SupplierPurchase renewal reference `-RENEW-{orderId}` + `supplier_expires_at` = ExpiredTime riil + biaya Rp69.300, re-run job tidak dobel (purchase & API tetap 1), kuota tidak tersentuh renewal |
| 2 | Recovery SHUTDOWN (recycle bin) | `RenewInstances` 1×, `StartInstances` **0×**, poll RECOVERING→RUNNING, **public_ip/private_ip baru tersinkron**, VPS running |
| 3 | Instance STOPPED di-renew | renew → `StartInstances` 1× → RUNNING |
| 4 | Renew saldo kurang | task failed, `provider_meta.renew_pending=true`, alert admin "saldo tidak cukup", **expires_at pelanggan tetap panjang**, instance tetap running, `RenewInstances` 0×, tidak ada purchase baru |
| 5 | `billing:suspend-overdue` | sub suspended; tencent: `StopVpsJob` dispatch, instance MASIH running sampai job sukses → suspended + `StopInstances` 1×; manual: langsung suspended tanpa job |
| 6 | `billing:terminate-expired` + idempotensi | deadline aman lewat → `DestroyVpsJob`; Isolate→SHUTDOWN→Terminate tepat 1× masing-masing; VPS+order terminated SETELAH sukses; `used` 1→0 + flag `quota_released`; task destroy baru saat upstream TERMINATED → nol API tambahan, `used` tetap 0 |
| 7 | Jendela aman belum lewat | customer +20 hari & upstream +7 hari → TIDAK terminate; upstream dimundurkan (−3 hari) → terminate terpicu **oleh jendela upstream lebih dulu** meski tenggang pelanggan belum habis |
| 8 | Rute admin suspend/unsuspend/terminate | suspend → StopVpsJob (running dulu, suspended pasca-sukses); unsuspend STOPPED → StartVpsJob; unsuspend SHUTDOWN → RenewVpsJob (`unsuspend=true`); terminate → DestroyVpsJob (terminated pasca-sukses, kuota lepas); manual suspend → langsung suspended, nol job tambahan |
| 9 | Reconcile deployment manual murni | `Http::assertNothingSent()` — nol panggilan API |
| 10 | Reconcile orphan + drift + sync | status running→stopped + IP baru tersinkron; `used` dikoreksi 1→2; alert orphan & alert drift masa aktif **sampai end-to-end ke WA gateway**; `TerminateInstances`/`IsolateInstances` **0×**; orphan tidak masuk DB |

Catatan mekanika test: instance command Artisan sudah ter-resolve saat migrasi `RefreshDatabase` (sebelum spy bisa dipasang), jadi alert reconcile diassert end-to-end lewat HTTP WA gateway, bukan spy container. `is_admin` tidak fillable (security) — admin test dibuat via `forceFill()`.

**Regresi penuh:** `php artisan test` → **554 passed / 3 failed (3.794 assertion)**. Ketiga kegagalan adalah baseline pre-existing yang sama persis sejak sebelum Phase 1 (NomorWhatsAppTest nomor publik + 2× PaymentVerificationTest placeholder QRIS) — bukan akibat Phase 4. Delta dari baseline 541: +3 test Patch A, +10 test lifecycle. Suite provisioning Phase 3 (14 test) dan billing/renewal manual (41 test) ikut hijau dalam full run.

### D.6 Keputusan desain Phase 4

1. **Urutan keselamatan pelanggan**: perpanjangan pelanggan selalu commit sebelum dispatch upstream (`DB::afterCommit`); kegagalan saldo upstream menandai `renew_pending` + alert, bukan membatalkan periode pelanggan.
2. **Idempotensi renewal 2 lapis**: (a) `renew_before_expired_time` vs ExpiredTime terkini mencegah `RenewInstances` dobel (API non-idempoten); (b) reference purchase `-RENEW-{orderId}` mencegah catatan biaya dobel.
3. **DB tidak pernah mendahului upstream**: suspended/terminated di-set hanya setelah job upstream sukses; gagal = state lama dipertahankan + alert, reconcile yang menilai ulang.
4. **Terminate lebih awal dari dua deadline** (`min`): penghapusan terkendali (Isolate→Terminate + release kuota) harus terjadi sebelum recycle bin upstream menghapus paksa pada hari ke-±15.
5. **Orphan = alert saja**: auto-terminate orphan dilarang (bisa jadi instance pelanggan yang belum tercatat); verifikasi manual di console upstream.
6. **Satu dispatcher bersama** (`VpsLifecycleDispatcher`) untuk cron + admin dengan guard task live → tidak ada tumpukan job saat cron berulang; instance sudah stopped tidak memanggil API saat suspend.
7. **Beban API**: sync-quota subsumed ke reconcile 15 menit; deployment manual murni = nol panggilan API (no-op guard).

### D.7 Kepatuhan & gate menuju Phase 5

1. **STRICT Rp0 terpenuhi**: tidak ada panggilan mutasi riil sepanjang Phase 4; semua verifikasi `Http::fake()` + eksekusi job manual di test.
2. **Gate tetap OFF** (diverifikasi ulang pasca-eksekusi): `PROVISIONING_DRIVER` tidak di-set di `.env` → default config **manual**; `provisioning_auto_enabled=false`; `quota_guard_mode=observe`. Retensi baru memakai default config 15/12 (env opsional belum di-set).
3. **White-label terjaga**: seluruh pesan pelanggan memakai brand CravaCloud; nama vendor hanya muncul di catatan admin/internal (SupplierPurchase, alert admin, log).
4. **Kredensial**: tidak ada SecretId/SecretKey yang dicetak, di-log, atau di-commit; test memakai kredensial dummy `test-id`/`test-key`.
5. **D7 tetap gate wajib sebelum canary**: Redis + Supervisor untuk queue production.
6. **D12 tidak berubah**: uji live berbayar 1× Starter XS ($4.20) hanya setelah Phase 3–5 selesai + konfirmasi ulang eksplisit Ryan + top-up saldo (masih $0) + verifikasi unit divisor balance.
7. Fase berikutnya: **Phase 5** (Day-2 operations: firewall UI, reset password, monitoring, fulfillment board).

---

## Lampiran E — Hasil Eksekusi & Penyelesaian Phase 5 (Day-2 Operations, Stack Installer, Upgrade & Preview UI)

Phase 5 sempat berhenti di tengah jalan karena limit kredit agent sebelumnya (tepat setelah menyusun seeder preview `DemoTencentUiSeeder` dan `StackRegistryTest`, sebelum penulisan suite Day-2, pengujian end-to-end UI, dan dokumentasi penutup). Sisa Phase 5 diaudit, diperbaiki bug runtime-nya, dilengkapi test, dan diselesaikan 100% dengan status **STRICT Rp0**.

### E.1 Pekerjaan yang diselesaikan agent sebelumnya (Checkpoint Handoff)

| Komponen | Artefak Utama | Status Saat Handoff |
| --- | --- | --- |
| Upgrade Paket (prorata) | `app/Services/UpgradeService.php`, `app/Jobs/Provisioning/UpgradeBundleJob.php`, `app/Notifications/VpsUpgradedNotification.php`, `tests/Feature/TencentUpgradeTest.php` (6 test) | Selesai & pass |
| Stack Registry & 26 Script | `app/Services/Provisioning/Stacks/StackRegistry.php`, `app/Services/Provisioning/StackInstallerService.php`, `app/Jobs/Provisioning/InstallStackJob.php`, `resources/provisioning/stacks/*.sh` (26 file), `tests/Feature/StackRegistryTest.php` (7 test) | Selesai & pass |
| Day-2 Service, Actions & Jobs | `app/Services/Provisioning/VpsDay2Service.php`, `app/Jobs/Provisioning/ResetPasswordJob.php`, `app/Notifications/VpsPasswordResetNotification.php`, `app/Http/Controllers/Dashboard/VpsActionController.php`, `app/Http/Controllers/Admin/VpsActionController.php`, rate limiters di `app/Providers/AppServiceProvider.php` | Ditulis, **belum punya test & mengandung bug runtime** |
| Customer Day-2 & Admin Board UI | `resources/views/dashboard/partials/day2-panel.blade.php`, `day2-panel-script.blade.php`, update `dashboard/show.blade.php`, `admin/instances.blade.php`, `admin/monitoring.blade.php` | Ditulis, **ada bug route di Blade** |
| Local UI Preview (Rp0) | `database/seeders/DemoTencentUiSeeder.php`, cache cabang `APP_ENV=local` (`tencent:demo:*`) di `TencentLighthouseProvider.php` | Ditulis, placeholder console route belum ada |

### E.2 Bug Runtime & Celah yang Ditemukan + Diperbaiki

1. **PHP Fatal Error pada `Day2Exception` (`Cannot redeclare non-readonly property Exception::$code as readonly`):**
   - `app/Services/Provisioning/Exceptions/Day2Exception.php` mendeklarasikan `public readonly string $code` yang menabrak properti bawaan `Exception::$code`.
   - **Perbaikan:** Properti diganti menjadi `$errorCode` dan kedua pemanggil (`app/Http/Controllers/Dashboard/VpsActionController.php` & `app/Http/Controllers/Admin/VpsActionController.php`) disinkronkan membaca `$e->errorCode`.
2. **HTTP 500 `RouteNotFoundException` di halaman detail VPS customer:**
   - `resources/views/dashboard/partials/day2-panel.blade.php` mereferensikan `dashboard.vps.day2.console.json` dan `dashboard.vps.day2.traffic.json`, padahal nama route terdaftar di `routes/web.php` tanpa akhiran `.json`.
   - **Perbaikan:** Diperbaiki ke `dashboard.vps.day2.console` dan `dashboard.vps.day2.traffic`.
3. **Katalog OS Reinstall & Label OS belum sinkron dengan katalog terbatas (D2):**
   - `app/Models/Order.php`: menambahkan label `'ubuntu2604'` dan `'debian13'` pada `operatingSystems()` dan menyederhanakan `checkoutOperatingSystems()`.
   - `app/Models/VpsInstance.php`: `reinstallOsOptions()` untuk provider `tencent` dibatasi ke `Order::checkoutOperatingSystems()` sehingga OS Windows/CentOS/versi lama tidak bocor ke modal reinstall.
   - `app/Http/Controllers/DashboardController.php`: `requestReinstall()` memvalidasi kompatibilitas `StackRegistry::supportsOs()` sebelum dispatch reinstall pada instance auto-provision `tencent`.
4. **Placeholder VNC Console khusus mode preview lokal:**
   - Menambahkan route `GET /demo-console-placeholder` (`demo.console.placeholder`) terbatas pada `app()->environment('local')` di `routes/web.php` agar tombol "Buka Konsol" pada demo `DemoTencentUiSeeder` tidak menghasilkan 404.
5. **Penambahan & Sinkronisasi UI "Kelola Layanan" Full-Otomatis (`show.blade.php`, `day2-panel.blade.php`, `day2-panel-script.blade.php`, `index.blade.php`, `DashboardController.php`):**
   - Instance otomatis (`$day2['capable']`) langsung membuka tab **Kontrol Server** sebagai default (untuk VPS standar), menampilkan aksi **Reinstall OS Otomatis** langsung di panel & toolbar (tanpa kata "Ajukan", tanpa field `"Catatan untuk tim"`, tanpa banner SLA `"12 jam kerja"`), menyembunyikan tab **Control Panel** jika stack `'none'` + mengganti pesan panel pending menjadi status instalasi otomatis, menyinkronkan tab **Detail Akses & SSH** & tab **Bantuan & Tiket** agar mengutamakan kontrol mandiri (VNC / Restart Paksa / Reset Password / Daya), serta auto-reload halaman saat task `reinstall`/`upgrade` selesai.
6. **Penambahan Test Suite Day-2 (`tests/Feature/TencentDay2Test.php` — 15 test):**
   - Menguji capabilities gating (hanya VPS `tencent` dengan `upstream_instance_id`), isolasi kepemilikan organisasi (IDOR protection), aksi power (`start`/`stop`/`reboot` + penolakan duplicate live task), reset password terenkripsi + notifikasi tanpa bocor ke task output, VNC URL single-use + audit admin log tanpa menyimpan URL, manajemen firewall (tambah & blokir hapus port SSH 22 tanpa `confirm_ssh=true`), traffic usage meter, pratinjau/eksekusi upgrade paket & penolakan downgrade, rate limiting endpoint Day-2, serta pembatasan OS reinstall & validasi kompatibilitas stack.

### E.3 Hasil Verifikasi Akhir Phase 5

1. **Suite Terfokus Phase 5** (`tests/Feature/TencentDay2Test.php`, `tests/Feature/StackRegistryTest.php`, `tests/Feature/TencentUpgradeTest.php`):
   - **28 passed (816 assertions)** dalam **3.42s** (Rp0 via `Http::fake()`).
2. **Full Test Suite** (`php artisan test` pasca-edit seluruh kode Phase 5):
   - **582 passed (4615 assertions), 3 failed** dalam **46.79s** (naik +15 passed / +114 assertions dari baseline pra-eksekusi **567 passed, 3 failed**).
   - 3 test yang gagal identik dengan baseline pra-eksekusi dan tidak terkait Tencent (`NomorWhatsAppTest` serta 2× `PaymentVerificationTest` karena `QRIS_PAYLOAD_PLACEHOLDER`).
3. **Kompilasi View & Dry-Run CLI:**
   - `php artisan view:cache` & `php artisan view:clear`: sukses tanpa syntax/route error.
   - `php artisan tencent:probe`: **PASS 11/11** (dry-run).
   - Verifikasi `resources/provisioning/stacks/*.sh`: **26 script bash** lengkap sesuai definisi `StackRegistry`.

### E.4 Status Gate & Checklist Go-Live Tersisa (D7 + D12)

- **Gate saat ini tetap OFF:** `.env` tidak mengaktifkan `PROVISIONING_DRIVER=tencent` (default `config('services.provisioning.default')` = `manual`), `QUEUE_CONNECTION=database`, DB setting `provisioning_auto_enabled=0`, `quota_guard_mode=observe`, `recycle_bin_retention_days=15`, `terminate_safety_days=12`, saldo akun Tencent `$0`.
- **Langkah selanjutnya saat siap diuji/go-live:**
  1. *(Opsional Rp0 — Preview UI Day-2 Lokal)* Jalankan `php artisan db:seed --class=DemoTencentUiSeeder`, login dengan `demo-day2@example.com` / `password123`, lalu buka detail VPS `srv-demo-day2`.
  2. **Gate D7 (Wajib sebelum canary):** Pindahkan `QUEUE_CONNECTION=redis` dan jalankan worker di bawah **Supervisor** di server Coolify/production.
  3. **Gate D12 (Uji live berbayar 1×):** Top-up saldo Tencent (misal `$5`), jalankan `php artisan tencent:probe --live` untuk verifikasi satuan `DescribeAccountBalance` (`balance_divisor`, default `100`), lalu uji 1× order Starter XS (`$4.20` — `bundle_starter_nmc_lin_med2_01`) beserta siklus penuh (provision, Day-2 firewall/VNC/reset password/stack, recycle bin & terminate).
  4. **Canary Production:** Setelah langkah 2 & 3 lolos, set `PROVISIONING_DRIVER=tencent`, tambahkan ID spec canary (misal Starter XS) ke `provisioning_auto_spec_whitelist`, lalu aktifkan `provisioning_auto_enabled` dan `quota_guard_mode=enforce`.

