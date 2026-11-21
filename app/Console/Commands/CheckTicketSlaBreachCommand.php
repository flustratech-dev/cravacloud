<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AdminSlaBreachNotification;
use App\Services\AdminAlertService;
use App\Support\MailIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckTicketSlaBreachCommand extends Command
{
    protected $signature = 'tickets:check-sla';

    protected $description = 'Periksa tiket bantuan yang melewati batas waktu SLA dan kirim notifikasi ke Admin';

    public function handle(AdminAlertService $alerts): int
    {
        $adminEmails = MailIdentity::adminRecipients();
        $fallbackAdmin = empty($adminEmails) ? User::where('is_admin', true)->first() : null;

        if (empty($adminEmails) && ! $fallbackAdmin) {
            $this->warn('Penerima email admin tidak ditemukan.');

            return self::SUCCESS;
        }

        // Cari tiket open / in_progress yang belum direspons oleh admin (first_response_at null) dan sudah lewat sla_due_at
        $breachedTickets = SupportTicket::with(['customer'])
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNull('first_response_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->get();

        if ($breachedTickets->isEmpty()) {
            $this->info('Tidak ada tiket yang melanggar SLA.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$breachedTickets->count()} tiket melewati batas SLA.");

        foreach ($breachedTickets as $ticket) {
            $dedupeKey = "sla_breach:ticket:{$ticket->id}";
            if (! Cache::add($dedupeKey, true, now()->addDay())) {
                continue;
            }

            try {
                $emailSent = false;
                foreach ($adminEmails as $email) {
                    Notification::route('mail', $email)
                        ->notify(new AdminSlaBreachNotification($ticket));
                    $emailSent = true;
                }

                if (! $emailSent && $fallbackAdmin) {
                    $fallbackAdmin->notify(new AdminSlaBreachNotification($ticket));
                    $emailSent = true;
                }

                $alerts->alert(
                    'ticket.sla_breach',
                    [
                        'title' => "SLA Breach Tiket #{$ticket->id}",
                        'send_email' => false,
                        'email_sent_override' => $emailSent,
                        'details' => [
                            'Tiket' => '#TK-' . str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT) . ' · Prioritas: ' . strtoupper((string) ($ticket->priority ?? 'normal')),
                            'Pelanggan' => $ticket->customer?->full_name ?: ($ticket->customer?->username ?: '-'),
                            'Subjek' => $ticket->subject ?? '-',
                        ],
                        'action_url' => url("/admin/tickets/{$ticket->id}"),
                    ],
                    dedupeKey: 'alert-sla-' . $ticket->id,
                    cooldownSeconds: 86400
                );

                $this->info("Notifikasi SLA breach terkirim untuk tiket #{$ticket->id}.");
            } catch (\Throwable $e) {
                Cache::forget($dedupeKey);
                $this->error("Gagal mengirim notifikasi untuk tiket #{$ticket->id}: " . $e->getMessage());
                Log::error('tickets.sla_breach_notify_failed', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
