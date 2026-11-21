<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\AbuseCase;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\VpsInstance;
use App\Notifications\TemplatedNotification;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 2 - Catatan kasus pelanggaran Acceptable Use Policy.
 *
 * Notifikasi dari sini memakai template abuse_suspension yang menyebut
 * pelanggaran secara spesifik. Template ini TIDAK boleh dipakai untuk
 * pemberitahuan massal: untuk itu gunakan BroadcastController.
 */
class AbuseController extends Controller
{
    use LogsAdminAudit;

    public function __construct(
        protected SettingsService $settings
    ) {
    }

    public function index(Request $request)
    {
        $query = AbuseCase::with(['user', 'vpsInstance', 'handler'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        return view('admin.abuse', [
            'cases' => $query->paginate(20)->withQueryString(),
            'types' => AbuseCase::types(),
            'statuses' => AbuseCase::STATUSES,
            'severities' => AbuseCase::SEVERITIES,
            'openCount' => AbuseCase::unresolved()->count(),
            'criticalCount' => AbuseCase::unresolved()->where('severity', 'critical')->count(),
            'totalCount' => AbuseCase::count(),
            'terminatedCount' => AbuseCase::where('status', 'terminated')->count(),
            'instances' => VpsInstance::with('customer')->latest()->limit(200)->get(),
            'filterStatus' => $request->query('status'),
            'filterSeverity' => $request->query('severity'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vps_instance_id' => ['nullable', 'integer', 'exists:vps_instances,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'in:' . implode(',', array_keys(AbuseCase::types()))],
            'severity' => ['required', 'in:' . implode(',', AbuseCase::SEVERITIES)],
            'evidence' => ['nullable', 'string', 'max:2000'],
        ]);

        $case = AbuseCase::create($validated + [
            'status' => 'open',
            'handled_by' => Auth::id(),
        ]);

        if (in_array($case->severity, ['high', 'critical'], true)) {
            try {
                $case->loadMissing(['user', 'vpsInstance']);
                app(\App\Services\AdminAlertService::class)->alert('billing.refund_or_abuse', [
                    'title' => 'Kasus Pelanggaran ' . strtoupper((string) $case->severity),
                    'severity' => $case->severity === 'critical' ? 'CRITICAL' : 'ACTION',
                    'details' => [
                        'Kasus' => "#{$case->id} ({$case->type_label})",
                        'Pelanggan' => $case->user?->full_name ?: ($case->user?->username ?: '-'),
                        'Server' => $case->vpsInstance?->hostname ?? '-',
                        'Bukti' => \Illuminate\Support\Str::limit((string) ($case->evidence ?? '-'), 180),
                    ],
                    'action_url' => url('/admin/abuse'),
                    'action_label' => 'Tinjau Kasus Pelanggaran',
                ], dedupeKey: "abuse:{$case->id}", cooldownSeconds: 3600);
            } catch (\Throwable) {
                // ignore
            }
        }

        $this->audit(
            'abuse.case_created',
            "Mencatat kasus pelanggaran {$case->type_label} untuk pengguna #{$case->user_id}.",
            $case
        );

        return back()->with('success', 'Kasus pelanggaran berhasil dicatat.');
    }

    /**
     * Kirim pemberitahuan suspensi ke pelanggan yang bersangkutan saja.
     */
    public function notify(Request $request, int $id)
    {
        $case = AbuseCase::with(['user', 'vpsInstance'])->findOrFail($id);

        $request->validate([
            'deadline_days' => ['required', 'integer', 'min:1', 'max:30'],
            'suspend_upstream' => ['nullable', 'boolean'],
        ]);

        $template = NotificationTemplate::active()
            ->where('code', NotificationTemplate::ABUSE_SUSPENSION)
            ->first();

        if (!$template) {
            return back()->with('error', 'Template surel suspensi tidak ditemukan atau sedang nonaktif.');
        }

        $deadline = now()->addDays((int) $request->input('deadline_days'));

        $data = [
            'nama' => $case->user->full_name ?: ($case->user->username ?: 'Pelanggan'),
            'layanan' => $case->vpsInstance?->hostname ?? 'Layanan Anda',
            'jenis_pelanggaran' => $case->type_label,
            'waktu' => $case->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB',
            'keterangan' => $case->evidence ?: 'Tidak ada keterangan tambahan.',
            'batas_waktu' => $deadline->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB',
        ];

        try {
            $case->user->notify(new TemplatedNotification(
                $template->renderSubject($data),
                $template->renderBody($data),
                'PELANGGARAN KETENTUAN',
                'support'
            ));
        } catch (\Throwable $e) {
            Log::error('abuse.notify_failed', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Pemberitahuan gagal dikirim: ' . $e->getMessage());
        }

        $upstreamWarn = null;
        $suspendedUpstream = false;
        if ($request->boolean('suspend_upstream') && $case->vpsInstance) {
            try {
                $dispatcher = app(\App\Services\Provisioning\VpsLifecycleDispatcher::class);
                if ($dispatcher->supports($case->vpsInstance)) {
                    $dispatcher->suspend($case->vpsInstance, "Suspensi kasus abuse #{$case->id}");
                    $suspendedUpstream = true;
                }
            } catch (\Throwable $e) {
                $upstreamWarn = 'Notifikasi terkirim, namun suspend upstream gagal: ' . $e->getMessage();
            }
        }

        $case->update([
            'status' => 'notified',
            'notified_at' => now(),
        ]);

        $this->audit(
            'abuse.customer_notified',
            "Mengirim pemberitahuan pelanggaran kasus #{$case->id} ke {$case->user->email}.",
            $case,
            ['suspend_upstream' => $suspendedUpstream]
        );

        return $upstreamWarn
            ? back()->with('warning', $upstreamWarn)
            : back()->with('success', 'Pemberitahuan pelanggaran terkirim ke pelanggan.' . ($suspendedUpstream ? ' Suspend upstream sedang diproses.' : ''));
    }

    public function resolve(Request $request, int $id)
    {
        $case = AbuseCase::with('vpsInstance')->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:resolved,terminated'],
            'resolution' => ['required', 'string', 'max:1000'],
            'terminate_upstream' => ['nullable', 'boolean'],
        ]);

        $terminatedUpstream = false;
        $upstreamWarn = null;
        if ($validated['status'] === 'terminated' && $request->boolean('terminate_upstream') && $case->vpsInstance) {
            try {
                $dispatcher = app(\App\Services\Provisioning\VpsLifecycleDispatcher::class);
                if ($dispatcher->supports($case->vpsInstance)) {
                    $dispatcher->destroy($case->vpsInstance, "Terminasi kasus abuse #{$case->id}: {$validated['resolution']}");
                    $terminatedUpstream = true;
                }
            } catch (\Throwable $e) {
                $upstreamWarn = 'Kasus ditutup, namun terminate upstream gagal: ' . $e->getMessage();
            }
        }

        $case->update([
            'status' => $validated['status'],
            'resolution' => $validated['resolution'],
            'resolved_at' => now(),
            'handled_by' => Auth::id(),
        ]);

        $this->audit(
            'abuse.case_resolved',
            "Menutup kasus pelanggaran #{$case->id} dengan status {$case->status_label}.",
            $case,
            ['status' => $validated['status'], 'terminate_upstream' => $terminatedUpstream]
        );

        return $upstreamWarn
            ? back()->with('warning', $upstreamWarn)
            : back()->with('success', 'Kasus pelanggaran berhasil ditutup.' . ($terminatedUpstream ? ' Terminasi upstream sedang diproses.' : ''));
    }
}
