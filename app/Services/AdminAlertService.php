<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SupportTicket;
use App\Notifications\AdminNewPaidOrderNotification;
use App\Support\MailIdentity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Admin Alert Router v2 — Email (HTML) + WhatsApp (Admin-only, 15 event keys).
 *
 * Penerima email diambil dari MailIdentity::adminRecipients().
 * Penerima WA diambil dari Setting 'admin_notification_whatsapp' dan difilter per-event
 * lewat Setting 'admin_alert_wa_events'.
 *
 * Setiap pengiriman dicatat ke ring-buffer 30 entri di Cache ('admin_alerts:recent')
 * agar dapat diaudit di Admin > Pengaturan > Notifikasi & WA Admin.
 */
class AdminAlertService
{
    public const RECENT_CACHE_KEY = 'admin_alerts:recent';

    public const EVENTS = [
        'payment.failed' => [
            'label' => 'Pembayaran Gagal / Anomali Webhook',
            'severity' => 'ACTION',
            'default_wa' => true,
            'default_url' => '/admin/webhooks?status=failed',
            'description' => 'Mismatch nominal, fraud challenge, HTTP 500, atau signature/deny webhook.',
        ],
        'payment.late_settled' => [
            'label' => 'Dana Masuk pada Order Expired / Dibatalkan',
            'severity' => 'CRITICAL',
            'default_wa' => true,
            'default_url' => '/admin/orders',
            'description' => 'Midtrans mengirim settlement untuk order yang sudah expired/cancelled.',
        ],
        'payment.paid_manual' => [
            'label' => 'Order Lunas — Perlu Setup Manual',
            'severity' => 'ACTION',
            'default_wa' => true,
            'default_url' => '/admin/orders?status=needs_provision',
            'description' => 'Order baru paket non-auto/Hold, Canary OFF, atau perpanjangan manual belum ditutup.',
        ],
        'payment.paid_auto' => [
            'label' => 'Order / Renewal Lunas — Diproses Otomatis',
            'severity' => 'INFO',
            'default_wa' => false,
            'default_url' => '/admin/orders',
            'description' => 'Order otomatis Tencent atau renewal yang otomatis aman (default email only).',
        ],
        'payment.shopee_created' => [
            'label' => 'Pesanan Shopee Baru Dicatat',
            'severity' => 'INFO',
            'default_wa' => true,
            'default_url' => '/admin/orders?channel=shopee',
            'description' => 'Pesanan marketplace Shopee masuk via Panel Admin atau Admin API.',
        ],
        'provisioning.failed' => [
            'label' => 'Provisioning / Lifecycle Task Gagal Permanen',
            'severity' => 'CRITICAL',
            'default_wa' => true,
            'default_url' => '/admin/monitoring?tab=tasks&status=failed',
            'description' => 'Provision, Install Stack, Reinstall, Upgrade, Renew, atau Destroy gagal setelah retry habis.',
        ],
        'provisioning.stuck' => [
            'label' => 'Task Provisioning Stuck / Order Lunas Tertahan',
            'severity' => 'CRITICAL',
            'default_wa' => true,
            'default_url' => '/admin/monitoring?tab=tasks',
            'description' => 'Task pending/running > 20 menit atau order paid tanpa task > 15 menit.',
        ],
        'upstream.balance_low' => [
            'label' => 'Saldo Deposit Upstream Rendah',
            'severity' => 'CRITICAL',
            'default_wa' => true,
            'default_url' => '/admin/monitoring?tab=upstream',
            'description' => 'Saldo akun cloud upstream di bawah ambang atau di bawah HPP order pending.',
        ],
        'upstream.quota_hold' => [
            'label' => 'Kuota Pool Region Penuh / Hampir Penuh',
            'severity' => 'CRITICAL',
            'default_wa' => true,
            'default_url' => '/admin/monitoring?tab=upstream',
            'description' => 'Pool kuota region penuh saat reserveHard() atau pemakaian mencapai >= 80-85%.',
        ],
        'upstream.reconcile_anomaly' => [
            'label' => 'Anomali Rekonsiliasi Upstream (Orphan / Drift)',
            'severity' => 'ACTION',
            'default_wa' => true,
            'default_url' => '/admin/monitoring?tab=upstream',
            'description' => 'Instance yatim (crava-*) di upstream atau selisih masa aktif > batas toleransi.',
        ],
        'ticket.created' => [
            'label' => 'Tiket Bantuan Baru dari Pelanggan',
            'severity' => 'ACTION',
            'default_wa' => true,
            'default_url' => '/admin/tickets',
            'description' => 'Tiket baru, permintaan reinstall OS, atau laporan server tidak dapat diakses.',
        ],
        'ticket.replied' => [
            'label' => 'Balasan Pelanggan pada Tiket Aktif',
            'severity' => 'ACTION',
            'default_wa' => true,
            'default_url' => '/admin/tickets',
            'description' => 'Pelanggan membalas tiket yang sedang ditangani (cooldown 5 menit per tiket).',
        ],
        'ticket.sla_breach' => [
            'label' => 'Pelanggaran SLA Respons Tiket',
            'severity' => 'CRITICAL',
            'default_wa' => true,
            'default_url' => '/admin/tickets',
            'description' => 'Tiket open/in_progress melewati batas sla_due_at tanpa respons admin.',
        ],
        'billing.refund_or_abuse' => [
            'label' => 'Pengajuan Refund / Kasus Abuse Berat',
            'severity' => 'ACTION',
            'default_wa' => true,
            'default_url' => '/admin/billing?tab=refunds',
            'description' => 'Pengajuan refund baru atau laporan pelanggaran tingkat high/critical.',
        ],
        'billing.daily_digest' => [
            'label' => 'Ringkasan Operasional & Billing Harian',
            'severity' => 'INFO',
            'default_wa' => false,
            'default_url' => '/admin',
            'description' => 'Digest harian jatuh tempo, layanan disuspend/dihapus, & ringkasan operasional.',
        ],
    ];

    public function __construct(private SettingsService $settings)
    {
    }

    public function adminEmails(): array
    {
        return MailIdentity::adminRecipients();
    }

    public function adminEmail(): ?string
    {
        $emails = $this->adminEmails();

        return $emails[0] ?? null;
    }

    /**
     * @return array<int, string> Nomor WA admin yang sudah dinormalisasi & unik.
     */
    public function adminWhatsAppNumbers(): array
    {
        $raw = (string) $this->settings->get('admin_notification_whatsapp', '');

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn ($phone) => WhatsAppGateway::normalize(trim((string) $phone)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Map boolean 15 event key => aktif/tidak untuk kanal WhatsApp Admin.
     *
     * @return array<string, bool>
     */
    public function eventWaSettings(): array
    {
        $stored = $this->settings->get('admin_alert_wa_events', []);
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($stored)) {
            $stored = [];
        }

        $result = [];
        foreach (self::EVENTS as $key => $meta) {
            $result[$key] = array_key_exists($key, $stored)
                ? (bool) $stored[$key]
                : (bool) $meta['default_wa'];
        }

        return $result;
    }

    public function isWhatsAppEnabledFor(string $eventKey): bool
    {
        $map = $this->eventWaSettings();

        if (array_key_exists($eventKey, $map)) {
            return $map[$eventKey];
        }

        return true;
    }

    /**
     * Ring-buffer 30 alert terakhir untuk audit cepat di Pengaturan Admin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentAlerts(): array
    {
        $items = Cache::get(self::RECENT_CACHE_KEY, []);

        return is_array($items) ? $items : [];
    }

    /**
     * Router tunggal untuk seluruh notifikasi operasional admin.
     *
     * @param  array{
     *   title?: string,
     *   intro?: string,
     *   body?: string,
     *   details?: array<string, mixed>,
     *   severity?: string,
     *   action_url?: string,
     *   action_label?: string,
     *   whatsapp_text?: string,
     *   send_email?: bool,
     *   send_whatsapp?: bool,
     *   email_sent_override?: bool
     * }  $context
     * @return array{email: bool, whatsapp: bool, skipped_dedupe: bool}
     */
    public function alert(
        string $eventKey,
        array $context,
        ?string $dedupeKey = null,
        ?int $cooldownSeconds = null
    ): array {
        if ($dedupeKey !== null && $dedupeKey !== '') {
            $cacheKey = 'admin_alert:dedupe:' . sha1($eventKey . '|' . $dedupeKey);
            $ttl = max(1, $cooldownSeconds ?? 3600);
            if (! Cache::add($cacheKey, true, $ttl)) {
                return ['email' => false, 'whatsapp' => false, 'skipped_dedupe' => true];
            }
        }

        $meta = self::EVENTS[$eventKey] ?? [
            'label' => $eventKey,
            'severity' => 'INFO',
            'default_wa' => true,
            'default_url' => '/admin',
        ];

        $severity = strtoupper((string) ($context['severity'] ?? $meta['severity']));
        $title = (string) ($context['title'] ?? $meta['label']);
        $actionPathOrUrl = (string) ($context['action_url'] ?? $meta['default_url']);
        $actionUrl = str_starts_with($actionPathOrUrl, 'http')
            ? $actionPathOrUrl
            : $this->url($actionPathOrUrl);

        $emailSent = (bool) ($context['email_sent_override'] ?? false);
        $lastError = null;

        if (($context['send_email'] ?? true) !== false) {
            $emailSent = $this->sendHtmlAlertEmail(
                eventKey: $eventKey,
                severity: $severity,
                title: $title,
                intro: $context['intro'] ?? null,
                details: is_array($context['details'] ?? null) ? $context['details'] : [],
                bodyText: isset($context['body']) ? (string) $context['body'] : null,
                actionUrl: $actionUrl,
                actionLabel: (string) ($context['action_label'] ?? 'Buka Admin Console'),
                orderId: isset($context['order_id']) ? (int) $context['order_id'] : null,
            );
        }

        $waSent = false;
        $waNumbersCount = 0;

        if (($context['send_whatsapp'] ?? true) !== false && $this->isWhatsAppEnabledFor($eventKey)) {
            $waMessage = isset($context['whatsapp_text']) && trim((string) $context['whatsapp_text']) !== ''
                ? (string) $context['whatsapp_text']
                : $this->formatWhatsAppMessage(
                    severity: $severity,
                    title: $title,
                    details: is_array($context['details'] ?? null) ? $context['details'] : [],
                    bodyText: isset($context['body']) ? (string) $context['body'] : null,
                    actionUrl: $actionUrl,
                );

            [$waSent, $waNumbersCount, $lastError] = $this->deliverWhatsApp(
                $waMessage,
                $eventKey,
                isset($context['order_id']) ? (int) $context['order_id'] : null,
            );
        }

        $this->recordRecentAlert(
            eventKey: $eventKey,
            severity: $severity,
            title: $title,
            emailSent: $emailSent,
            waSent: $waSent,
            waCount: $waNumbersCount,
            error: $lastError,
        );

        return [
            'email' => $emailSent,
            'whatsapp' => $waSent,
            'skipped_dedupe' => false,
        ];
    }

    /**
     * Order baru lunas atau perpanjangan lunas.
     */
    public function orderPaid(Order $order): void
    {
        $order->loadMissing(['customer', 'vpsSpec']);

        $mailOk = $this->sendMail(
            fn (string $email) => Notification::route('mail', $email)
                ->notify(new AdminNewPaidOrderNotification($order)),
            'order_paid',
            $order->id
        );

        if ($order->isRenewal()) {
            $instance = $order->resolved_instance;
            if ($instance && $instance->provider === 'tencent') {
                $eventKey = 'payment.paid_auto';
                $waText = $this->autoRenewalPaidMessage($order, $instance);
            } else {
                $coverage = $instance
                    ? app(SupplierCoverageService::class)->rowFor($instance->loadMissing('supplierPurchases'))
                    : null;
                $covered = $coverage && $coverage['status'] === SupplierCoverageService::STATUS_COVERED;

                $eventKey = $covered ? 'payment.paid_auto' : 'payment.paid_manual';
                $waText = $this->renewalPaidMessage($order, $instance, $coverage, $covered);
            }
            $title = 'Perpanjangan Lunas #' . $order->id;
        } elseif ($order->isUpgrade()) {
            $spec = $order->vpsSpec;
            $instance = $order->resolved_instance;
            $isAuto = $instance && $instance->provider === 'tencent' && $spec?->hasUpstreamMapping();
            $eventKey = $isAuto ? 'payment.paid_auto' : 'payment.paid_manual';
            $waText = $this->upgradePaidMessage($order, $isAuto);
            $title = 'Upgrade Lunas #' . $order->id;
        } else {
            $spec = $order->vpsSpec;
            $gate = app(\App\Services\Provisioning\ProvisioningGate::class);
            $isAuto = $spec
                && $spec->hasUpstreamMapping()
                && $gate->allows($spec)
                && empty($order->metadata['manual_fulfillment']);

            $eventKey = $isAuto ? 'payment.paid_auto' : 'payment.paid_manual';
            $waText = $this->newPaidMessage($order, $isAuto);
            $title = 'Order Baru Lunas #' . $order->id;
        }

        $this->alert(
            $eventKey,
            [
                'title' => $title,
                'order_id' => $order->id,
                'send_email' => false,
                'email_sent_override' => $mailOk,
                'whatsapp_text' => $waText,
            ],
            dedupeKey: 'order-' . $order->id,
            cooldownSeconds: 86400,
        );
    }

    /**
     * Tiket baru / balasan pelanggan. Email tiket dikirim terpisah; di sini alert WA Admin.
     */
    public function ticket(SupportTicket $ticket, string $message, string $eventType): void
    {
        try {
            $ticket->loadMissing('customer');
            $eventKey = $eventType === 'created' ? 'ticket.created' : 'ticket.replied';
            $code = '#TK-' . str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);
            $title = $eventType === 'created' ? '🎫 *TIKET BARU*' : '💬 *BALASAN TIKET DARI PELANGGAN*';

            $waText = implode("\n", [
                $title,
                '',
                "• *Tiket:* {$code}",
                '• *Subjek:* ' . ($ticket->subject ?? '-'),
                '• *Pelanggan:* ' . ($ticket->customer?->full_name ?? '-'),
                '',
                Str::limit(trim($message), 500),
                '',
                'Balas di: ' . $this->url("/admin/tickets/{$ticket->id}"),
            ]);

            $this->alert(
                $eventKey,
                [
                    'title' => ($eventType === 'created' ? 'Tiket Baru ' : 'Balasan Tiket ') . $code,
                    'send_email' => false,
                    'whatsapp_text' => $waText,
                    'action_url' => $this->url("/admin/tickets/{$ticket->id}"),
                ],
                dedupeKey: ($eventType === 'replied' ? 'ticket-reply-' : 'ticket-new-') . $ticket->id,
                cooldownSeconds: $eventType === 'replied' ? 300 : 3600,
            );
        } catch (\Throwable $e) {
            Log::error('admin_alert.ticket_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }

    public function whatsAppOnly(string $text, string $context): bool
    {
        [$waOk, $count, $error] = $this->deliverWhatsApp($text, $context);
        $this->recordRecentAlert($context, 'INFO', Str::limit($text, 70), false, $waOk, $count, $error);

        return $waOk;
    }

    /**
     * Ringkasan / alert umum (HTML email via emails.admin-alert + WA bila aktif).
     */
    public function digest(string $subject, string $text, string $eventKey = 'billing.daily_digest'): bool
    {
        $res = $this->alert($eventKey, [
            'title' => $subject,
            'body' => $text,
            'whatsapp_text' => $text,
        ]);

        return $res['email'] || $res['whatsapp'];
    }

    /**
     * Pesan uji dari halaman pengaturan.
     *
     * @return array{email: bool, whatsapp: bool}
     */
    public function test(): array
    {
        $email = $this->sendHtmlAlertEmail(
            eventKey: 'system.test',
            severity: 'INFO',
            title: 'Tes Notifikasi Admin CravaCloud',
            intro: 'Ini adalah pesan uji sistem notifikasi Admin Operasional CravaCloud.',
            details: [
                'Status Sistem' => 'Email HTML (emails.admin-alert) aktif',
                'Waktu Uji' => $this->jakarta(now()),
            ],
            bodyText: 'Jika email ini masuk ke inbox admin, seluruh alert operasional (pembayaran gagal, provisioning gagal, saldo upstream, & tiket SLA) siap diterima.',
            actionUrl: $this->url('/admin/settings?tab=notification'),
            actionLabel: 'Buka Pengaturan Notifikasi',
        );

        [$wa, $count, $error] = $this->deliverWhatsApp(
            "✅ *Tes Notifikasi Admin CravaCloud*\n\nJika pesan ini masuk, Admin Operations Alert Router aktif dan siap mengirim alert ke nomor ini.",
            'test'
        );

        $this->recordRecentAlert('system.test', 'INFO', 'Tes Notifikasi Admin CravaCloud', $email, $wa, $count, $error);

        return ['email' => $email, 'whatsapp' => $wa];
    }

    private function sendHtmlAlertEmail(
        string $eventKey,
        string $severity,
        string $title,
        ?string $intro,
        array $details,
        ?string $bodyText,
        string $actionUrl,
        string $actionLabel,
        ?int $orderId = null,
    ): bool {
        $noreply = MailIdentity::noreply();
        $replyTo = MailIdentity::replyTo();
        $subject = "[{$severity}] {$title} — CravaCloud Admin";

        return $this->sendMail(
            fn (string $email) => Mail::send(
                'emails.admin-alert',
                [
                    'subjectLine' => $subject,
                    'badgeText' => "{$severity} ALERT",
                    'severity' => $severity,
                    'title' => $title,
                    'intro' => $intro,
                    'details' => $details,
                    'bodyText' => $bodyText,
                    'actionUrl' => $actionUrl,
                    'actionLabel' => $actionLabel,
                ],
                fn ($message) => $message->to($email)
                    ->from($noreply['address'], $noreply['name'])
                    ->replyTo($replyTo['address'], $replyTo['name'])
                    ->subject($subject)
            ),
            $eventKey,
            $orderId
        );
    }

    private function formatWhatsAppMessage(
        string $severity,
        string $title,
        array $details,
        ?string $bodyText,
        string $actionUrl,
    ): string {
        $icon = match ($severity) {
            'CRITICAL' => '🔴',
            'ACTION' => '🟡',
            default => '🔵',
        };

        $lines = [
            "{$icon} *[{$severity}] " . Str::upper($title) . '*',
        ];

        foreach (array_slice($details, 0, 5, true) as $label => $value) {
            $lines[] = "• *{$label}:* {$value}";
        }

        if ($bodyText !== null && trim($bodyText) !== '') {
            $lines[] = '• *Info:* ' . Str::limit(preg_replace('/\s+/', ' ', trim($bodyText)), 240);
        }

        $lines[] = '🔗 ' . $actionUrl;

        return implode("\n", $lines);
    }

    private function newPaidMessage(Order $order, bool $isAuto): string
    {
        return implode("\n", array_filter([
            $isAuto
                ? '🔵 *ORDER BARU LUNAS — PROVISIONING OTOMATIS*'
                : '🔔 *ORDER BARU LUNAS — PERLU SETUP*',
            '',
            "• *Order:* #{$order->id}",
            '• *Paket:* ' . ($order->vpsSpec?->name ?? 'VPS'),
            '• *Pelanggan:* ' . ($order->customer?->full_name ?? '-') . ($order->customer?->phone ? " ({$order->customer->phone})" : ''),
            '• *Nominal:* Rp ' . number_format((float) $order->amount, 0, ',', '.'),
            '• *Metode:* ' . strtoupper((string) $order->payment_method),
            $order->hostname ? "• *Hostname:* `{$order->hostname}`" : null,
            '• *Lokasi / OS:* ' . ucfirst((string) $order->datacenter_location) . ' / ' . ($order->os ?? '-'),
            '• *Dibayar:* ' . $this->jakarta($order->paid_at),
            '',
            $isAuto
                ? 'Diproses otomatis oleh sistem. Pantau task:'
                : 'Segera proses order di Admin Panel:',
            $this->url($isAuto ? '/admin/monitoring?tab=tasks' : '/admin/orders?status=needs_provision'),
        ], fn ($line) => $line !== null));
    }

    private function autoRenewalPaidMessage(Order $order, $instance): string
    {
        return implode("\n", array_filter([
            '🔵 *PERPANJANGAN OTOMATIS LUNAS*',
            '',
            "• *Order:* #{$order->id}",
            '• *Layanan:* ' . ($instance?->hostname ?? $order->hostname ?? '-'),
            '• *Pelanggan:* ' . ($order->customer?->full_name ?? '-'),
            '• *Nominal:* Rp ' . number_format((float) $order->amount, 0, ',', '.'),
            $instance?->expires_at ? '• *Aktif hingga:* ' . $this->jakarta($instance->expires_at) : null,
            '🔗 ' . $this->url('/admin/orders'),
        ], fn ($line) => $line !== null));
    }

    private function renewalPaidMessage(Order $order, $instance, ?array $coverage, bool $covered): string
    {
        $applied = (bool) $order->renewal_applied_at;

        return implode("\n", array_filter([
            $covered ? '🔁 *PERPANJANGAN LUNAS*' : '🔁 *PERPANJANGAN LUNAS — PERLU TINDAKAN*',
            '',
            "• *Order:* #{$order->id}",
            '• *Layanan:* ' . ($instance?->hostname ?? $order->hostname ?? '-'),
            '• *Pelanggan:* ' . ($order->customer?->full_name ?? '-'),
            '• *Nominal:* Rp ' . number_format((float) $order->amount, 0, ',', '.'),
            '• *Metode:* ' . strtoupper((string) $order->payment_method),
            $applied && $instance?->expires_at ? '• *Aktif (pelanggan) hingga:* ' . $this->jakarta($instance->expires_at) : null,
            $coverage ? '• *Aktif di Supplier hingga:* ' . ($coverage['supplier_expires_at'] ? $this->jakarta($coverage['supplier_expires_at']) : 'belum dicatat') : null,
            '',
            $applied ? null : '⚠️ *Masa aktif pelanggan BELUM berhasil diperpanjang otomatis. Cek manual:* ' . $this->url('/admin/orders'),
            $covered
                ? '_Masa aktif di Supplier sudah menutup periode ini. Tidak perlu tindakan._'
                : "✅ *Tugas:* perpanjang VPS ini di Supplier, lalu catat pembeliannya:\n" . $this->url('/admin/supplier-purchases'),
        ], fn ($line) => $line !== null));
    }

    private function upgradePaidMessage(Order $order, bool $isAuto): string
    {
        return implode("\n", [
            $isAuto ? '🔵 *UPGRADE VPS OTOMATIS LUNAS*' : '🟡 *UPGRADE VPS LUNAS — PERLU TINDAKAN*',
            '',
            "• *Order:* #{$order->id}",
            '• *Target Paket:* ' . ($order->vpsSpec?->name ?? '-'),
            '• *Pelanggan:* ' . ($order->customer?->full_name ?? '-'),
            '• *Nominal:* Rp ' . number_format((float) $order->amount, 0, ',', '.'),
            '🔗 ' . $this->url('/admin/orders'),
        ]);
    }

    private function sendMail(callable $send, string $context, ?int $orderId = null): bool
    {
        $emails = $this->adminEmails();

        if (empty($emails)) {
            Log::warning('admin_alert.email_missing', ['context' => $context, 'order_id' => $orderId]);

            return false;
        }

        $anySuccess = false;

        foreach ($emails as $email) {
            try {
                $send($email);
                $anySuccess = true;
            } catch (\Throwable $e) {
                Log::error('admin_alert.email_failed', [
                    'context' => $context,
                    'order_id' => $orderId,
                    'recipient' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $anySuccess;
    }

    private function sendWhatsApp(string $message, string $context, ?int $orderId = null): bool
    {
        [$sent] = $this->deliverWhatsApp($message, $context, $orderId);

        return $sent;
    }

    /**
     * @return array{0: bool, 1: int, 2: ?string} [anySent, numberCount, lastError]
     */
    private function deliverWhatsApp(string $message, string $context, ?int $orderId = null): array
    {
        $numbers = $this->adminWhatsAppNumbers();

        if ($numbers === []) {
            Log::warning('admin_alert.whatsapp_missing', ['context' => $context, 'order_id' => $orderId]);

            return [false, 0, 'Nomor WA admin belum diisi'];
        }

        $sent = false;
        $lastError = null;

        try {
            $sent = count($numbers) > 1
                ? WhatsAppGateway::broadcast($numbers, $message)
                : WhatsAppGateway::send($numbers[0], $message);

            if (! $sent) {
                $lastError = 'Gateway menolak/nonaktif atau gagal kirim.';
            }
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
            Log::error('admin_alert.whatsapp_failed', ['context' => $context, 'order_id' => $orderId, 'error' => $lastError]);
        }

        return [$sent, count($numbers), $sent ? null : $lastError];
    }

    private function recordRecentAlert(
        string $eventKey,
        string $severity,
        string $title,
        bool $emailSent,
        bool $waSent,
        int $waCount,
        ?string $error = null,
    ): void {
        try {
            $items = Cache::get(self::RECENT_CACHE_KEY, []);
            if (! is_array($items)) {
                $items = [];
            }

            array_unshift($items, [
                'event_key' => $eventKey,
                'severity' => $severity,
                'title' => $title,
                'email_sent' => $emailSent,
                'wa_sent' => $waSent,
                'wa_count' => $waCount,
                'error' => $error,
                'sent_at' => now()->toIso8601String(),
            ]);

            Cache::put(self::RECENT_CACHE_KEY, array_slice($items, 0, 30), now()->addDays(7));
        } catch (\Throwable) {
            // Never let audit cache errors break alert delivery
        }
    }

    private function jakarta($date): string
    {
        return $date ? $date->copy()->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-';
    }

    private function url(string $path): string
    {
        return rtrim((string) config('app.url', 'https://cravacloud.id'), '/') . $path;
    }
}
