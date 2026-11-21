<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastChunkJob;
use App\Models\BroadcastLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $userIds = $this->recipientQuery($validated['audience'])->pluck('id');

        $log = BroadcastLog::create([
            'template_code' => $validated['template_code'] ?? null,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'audience' => $validated['audience'],
            'recipient_count' => $userIds->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);

        foreach ($userIds->chunk(50) as $chunk) {
            SendBroadcastChunkJob::dispatch(
                $log->id,
                $chunk->values()->all(),
                $validated['subject'],
                $validated['body'],
                'PEMBERITAHUAN LAYANAN'
            );
        }

        $this->audit(
            'broadcast.sent',
            "Menjadwalkan broadcast \"{$log->subject}\" ke {$userIds->count()} penerima (batch 50/job).",
            $log
        );

        return back()->with('success', "Broadcast dijadwalkan untuk {$userIds->count()} pelanggan dan sedang diproses antrean.");
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
                            $user->full_name ?: ($user->username ?: ''),
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
