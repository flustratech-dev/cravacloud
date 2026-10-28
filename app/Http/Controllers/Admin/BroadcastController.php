<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\BroadcastLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\TemplatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PHASE 2 - Broadcast darurat ke pelanggan.
 *
 * Template yang dipakai di sini TIDAK PERNAH menuduh penerima melanggar.
 * Dari sekian pelanggan yang menerima, umumnya hanya satu yang bermasalah,
 * sehingga tuduhan massal justru membuka risiko hukum baru.
 *
 * Ekspor kontak adalah pengaman lapis kedua: bila panel bermasalah,
 * pengiriman masih dapat dilakukan manual lewat dasbor penyedia surel.
 */
class BroadcastController extends Controller
{
    use LogsAdminAudit;

    public function index()
    {
        return view('admin.broadcast', [
            'templates' => NotificationTemplate::active()
                ->whereIn('code', [
                    NotificationTemplate::INFRA_INCIDENT,
                    NotificationTemplate::INFRA_RECOVERED,
                ])
                ->get(),
            'logs' => BroadcastLog::with('sender')->latest()->paginate(15),
            'audiences' => BroadcastLog::audiences(),
            'recipientCounts' => [
                'all' => $this->recipientQuery('all')->count(),
                'active_customers' => $this->recipientQuery('active_customers')->count(),
                'affected_instances' => $this->recipientQuery('affected_instances')->count(),
            ],
            'lastSent' => BroadcastLog::latest('sent_at')->first(),
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'audience' => ['required', 'in:' . implode(',', array_keys(BroadcastLog::audiences()))],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'template_code' => ['nullable', 'string', 'max:60'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Centang konfirmasi terlebih dahulu sebelum mengirim broadcast.',
        ]);

        $recipients = $this->recipientQuery($validated['audience'])->get();

        $log = BroadcastLog::create([
            'template_code' => $validated['template_code'] ?? null,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'audience' => $validated['audience'],
            'recipient_count' => $recipients->count(),
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $user) {
            // Placeholder {{nama}} diisi per penerima agar surel tetap personal.
            $body = str_replace('{{nama}}', $user->name ?? 'Pelanggan', $validated['body']);

            try {
                $user->notify(new TemplatedNotification(
                    $validated['subject'],
                    $body,
                    'PEMBERITAHUAN LAYANAN'
                ));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('broadcast.send_failed', [
                    'broadcast_log_id' => $log->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $log->update(['sent_count' => $sent, 'failed_count' => $failed]);

        $this->audit(
            'broadcast.sent',
            "Mengirim broadcast \"{$log->subject}\" ke {$sent} penerima ({$failed} gagal).",
            $log
        );

        $message = "Broadcast terkirim ke {$sent} pelanggan.";
        if ($failed > 0) {
            $message .= " {$failed} pengiriman gagal, periksa log untuk rinciannya.";
        }

        return back()->with('success', $message);
    }

    /**
     * Ekspor kontak pelanggan ke CSV sebagai pengaman darurat.
     */
    public function exportContacts(): StreamedResponse
    {
        $this->audit('broadcast.contacts_exported', 'Mengekspor kontak pelanggan ke CSV.');

        $filename = 'kontak-pelanggan-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // BOM agar karakter non-ASCII terbaca benar di Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Nama', 'Email', 'Telepon', 'Jumlah Instance', 'Terdaftar Sejak']);

            User::where('is_admin', false)
                ->withCount('vpsInstances')
                ->chunkById(500, function ($users) use ($handle) {
                    foreach ($users as $user) {
                        fputcsv($handle, [
                            $user->name,
                            $user->email,
                            $user->phone ?? '',
                            $user->vps_instances_count,
                            $user->created_at?->format('Y-m-d'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Kueri penerima sesuai segmen yang dipilih.
     */
    protected function recipientQuery(string $audience)
    {
        $query = User::where('is_admin', false)->whereNotNull('email');

        return match ($audience) {
            'active_customers' => $query->whereHas('vpsInstances', function ($q) {
                $q->whereIn('status', ['running', 'stopped', 'provisioning']);
            }),
            'affected_instances' => $query->whereHas('vpsInstances', function ($q) {
                $q->whereIn('status', ['error', 'suspended']);
            }),
            default => $query,
        };
    }
}
