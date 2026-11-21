<?php

namespace App\Console\Commands;

use App\Models\RenewalReminder;
use App\Models\Subscription;
use App\Notifications\AdminRenewalDigestNotification;
use App\Notifications\BillingReminderNotification;
use App\Services\RenewalService;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Cek subscription yang mendekati jatuh tempo (H-7, H-3, H-1, H+0).
 * Kirim notifikasi reminder ke pelanggan (idempoten per siklus) + 1 digest harian ke admin.
 *
 * Jalankan harian: schedule di routes/console.php (08:00 WIB).
 */
class BillingCheckDueCommand extends Command
{
    protected $signature = 'billing:check-due
        {--days=7,3,1,0 : Comma-separated hari-hari untuk reminder}
        {--dry-run : Hanya tampilkan, tidak kirim notifikasi}';

    protected $description = 'Kirim reminder subscription yang mendekati jatuh tempo (H-7/H-3/H-1/H+0).';

    public function handle(RenewalService $renewal, SettingsService $settings): int
    {
        $days = array_map('intval', explode(',', $this->option('days')));
        $dry = (bool) $this->option('dry-run');

        $stageMap = [
            7 => RenewalReminder::STAGE_H7,
            3 => RenewalReminder::STAGE_H3,
            1 => RenewalReminder::STAGE_H1,
            0 => RenewalReminder::STAGE_H0,
        ];

        $digest = [
            'h_minus_7' => [],
            'h_minus_3' => [],
            'h_minus_1' => [],
            'h_zero' => [],
            'grace_ended' => [],
        ];

        $totalNotified = 0;
        foreach ($days as $d) {
            $start = now()->addDays($d)->startOfDay();
            $end = now()->addDays($d)->endOfDay();

            $subs = Subscription::whereIn('status', ['active', 'past_due', 'grace_period'])
                ->whereBetween('next_billing_at', [$start, $end])
                ->with(['customer', 'vpsSpec', 'vpsInstance'])
                ->get();

            $this->info("H-{$d}: {$subs->count()} subscription due.");

            foreach ($subs as $sub) {
                if ($dry) {
                    $this->line("  → [dry] would notify sub #{$sub->id} customer #{$sub->customer_id}");
                    continue;
                }

                $stage = $stageMap[$d] ?? "h_minus_{$d}";

                // Idempotensi per siklus jatuh tempo bila subscription terikat ke instance.
                if ($sub->vpsInstance && isset($stageMap[$d]) && $sub->next_billing_at) {
                    try {
                        RenewalReminder::create([
                            'vps_instance_id' => $sub->vpsInstance->id,
                            'stage' => $stage,
                            'period_expires_at' => $sub->vpsInstance->expires_at ?? $sub->next_billing_at,
                            'sent_at' => now(),
                        ]);
                    } catch (QueryException) {
                        continue; // Sudah dikirim untuk periode ini.
                    }
                }

                try {
                    if ($sub->customer) {
                        $sub->customer->notify(new BillingReminderNotification($sub, $d));
                        $totalNotified++;
                    }
                } catch (\Throwable $e) {
                    Log::error('billing.reminder_failed', [
                        'subscription_id' => $sub->id,
                        'days' => $d,
                        'error' => $e->getMessage(),
                    ]);
                }

                if (isset($digest[$stage]) && $sub->vpsInstance) {
                    $digest[$stage][] = [
                        'id' => $sub->vpsInstance->id,
                        'hostname' => $sub->vpsInstance->hostname ?? '-',
                        'customer' => $sub->customer?->full_name ?? ($sub->customer?->username ?? '-'),
                        'email' => $sub->customer?->email ?? '-',
                        'type' => $renewal->typeLabel($renewal->productType($sub->vpsInstance)),
                        'expires_at' => $sub->next_billing_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-',
                        'grace_ends_at' => $sub->grace_period_ends_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-',
                    ];
                }
            }
        }

        if (! $dry && $totalNotified > 0 && $settings->bool('digest_enabled', true)) {
            $hasRows = array_sum(array_map('count', $digest)) > 0;
            if ($hasRows) {
                $emailSent = false;
                foreach (\App\Support\MailIdentity::adminRecipients() as $email) {
                    try {
                        Notification::route('mail', $email)
                            ->notify(new AdminRenewalDigestNotification($digest));
                        $emailSent = true;
                    } catch (\Throwable $e) {
                        Log::error('billing.digest_failed', ['recipient' => $email, 'error' => $e->getMessage()]);
                    }
                }

                try {
                    app(\App\Services\AdminAlertService::class)->alert(
                        'billing.daily_digest',
                        [
                            'title' => 'Ringkasan Billing Jatuh Tempo Harian',
                            'send_email' => false,
                            'email_sent_override' => $emailSent,
                            'details' => [
                                'H-7' => count($digest['h_minus_7']) . ' layanan',
                                'H-3' => count($digest['h_minus_3']) . ' layanan',
                                'H-1' => count($digest['h_minus_1']) . ' layanan',
                                'Hari-H' => count($digest['h_zero']) . ' layanan',
                            ],
                            'action_url' => url('/admin/billing'),
                        ],
                        dedupeKey: 'daily-digest-' . now()->format('Ymd'),
                        cooldownSeconds: 86400
                    );
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        $this->info("Total reminder dikirim: {$totalNotified}");
        return self::SUCCESS;
    }
}
