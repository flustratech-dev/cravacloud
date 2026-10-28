<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Payments\LynkPaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * PHASE 4 - Riwayat webhook dan proses ulang.
 *
 * Proses ulang memakai LynkPaymentProcessor yang sama dengan webhook asli,
 * dari payload yang tersimpan, tanpa meminta Lynk mengirim ulang.
 *
 * Tiga pengaman:
 *   1. Hanya event bertanda tangan sah yang boleh diproses ulang
 *   2. Event yang sudah berhasil tidak diproses lagi (cek status + transaksi settled)
 *   3. Kunci per event mencegah dua klik bersamaan memproses event yang sama
 */
class WebhookLogController extends Controller
{
    use LogsAdminAudit;

    public function index(Request $request)
    {
        $query = WebhookEvent::with(['order', 'replayer'])->latest();

        if ($status = $request->query('status')) {
            $query->where('processing_status', $status);
        }

        if ($provider = $request->query('provider')) {
            $query->where('provider', $provider);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where('event_id', 'like', '%' . $search . '%');
        }

        return view('admin.webhooks', [
            'events' => $query->paginate(25)->withQueryString(),
            'statuses' => WebhookEvent::STATUSES,
            'providers' => WebhookEvent::query()->distinct()->orderBy('provider')->pluck('provider'),
            'counts' => [
                'failed' => WebhookEvent::where('processing_status', 'failed')->count(),
                'received' => WebhookEvent::where('processing_status', 'received')->count(),
                'processed' => WebhookEvent::where('processing_status', 'processed')->count(),
                'total' => WebhookEvent::count(),
            ],
            'filters' => $request->only(['status', 'provider', 'from', 'to', 'q']),
        ]);
    }

    public function replay(int $id, LynkPaymentProcessor $processor)
    {
        $event = WebhookEvent::findOrFail($id);

        if (!$event->signature_verified) {
            return back()->with('error', 'Event ini tidak memiliki signature yang terverifikasi, sehingga tidak boleh diproses ulang.');
        }

        if (!$event->isReplayable()) {
            return back()->with('error', "Event berstatus {$event->status_label} tidak dapat diproses ulang.");
        }

        $lock = Cache::lock('webhook-replay:' . $event->id, 30);

        if (!$lock->get()) {
            return back()->with('error', 'Event ini sedang diproses. Tunggu sebentar lalu muat ulang halaman.');
        }

        try {
            $event->update([
                'attempts' => $event->attempts + 1,
                'last_replayed_at' => now(),
                'replayed_by' => Auth::id(),
            ]);

            $result = $processor->process($event->fresh());
        } finally {
            $lock->release();
        }

        $event->refresh();

        $this->audit(
            'webhook.replayed',
            "Memproses ulang webhook {$event->provider} #{$event->event_id}: {$event->status_label} (HTTP {$result['http']}).",
            $event
        );

        if ($event->processing_status === 'processed') {
            return back()->with(
                'success',
                'Webhook berhasil diproses ulang.' . ($event->order_id ? " Order #{$event->order_id} sudah lunas." : '')
            );
        }

        return back()->with(
            'error',
            'Proses ulang belum berhasil: ' . ($event->processing_error ?: ($result['body']['message'] ?? 'alasan tidak diketahui'))
        );
    }
}
