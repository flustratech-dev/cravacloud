<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\ImpersonationLog;
use App\Models\LoginActivity;
use Illuminate\Http\Request;

/**
 * PHASE 7 - Penampil jejak audit.
 *
 * Meskipun admin hanya satu orang, jejak ini tetap diperlukan: sebagai bukti
 * saat pelanggan menyanggah, dan untuk menjawab mengapa suatu layanan
 * disuspend atau diubah beberapa bulan sebelumnya.
 */
class AuditLogController extends Controller
{
    public const TABS = ['admin', 'login', 'impersonation'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'admin';
        $filters = $request->only(['action', 'q', 'from', 'to', 'outcome']);

        $data = ['tab' => $tab, 'filters' => $filters];

        if ($tab === 'admin') {
            $query = AdminAuditLog::with('admin')->latest('id');

            if (!empty($filters['action'])) {
                // Awalan grup aksi, mis. "billing" mencakup billing.refund_created.
                $query->where('action', 'like', $filters['action'] . '%');
            }

            if (!empty($filters['q'])) {
                $query->where('description', 'like', '%' . $filters['q'] . '%');
            }

            $this->applyDateRange($query, $filters);

            $data['logs'] = $query->paginate(30)->withQueryString();
            $data['actionGroups'] = AdminAuditLog::query()
                ->select('action')
                ->distinct()
                ->pluck('action')
                ->map(fn ($a) => explode('.', (string) $a)[0])
                ->unique()
                ->sort()
                ->values();
        }

        if ($tab === 'login') {
            $query = LoginActivity::with('user')->latest('id');

            if (!empty($filters['q'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('email', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('ip_address', 'like', '%' . $filters['q'] . '%');
                });
            }

            if (!empty($filters['outcome'])) {
                $query->where('outcome', $filters['outcome']);
            }

            $this->applyDateRange($query, $filters);

            $data['activities'] = $query->paginate(30)->withQueryString();
            $data['failedToday'] = LoginActivity::where('outcome', '!=', 'success')
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
        }

        if ($tab === 'impersonation') {
            $query = ImpersonationLog::with(['admin', 'impersonated'])->latest('id');
            $this->applyDateRange($query, $filters, 'started_at');

            $data['sessions'] = $query->paginate(30)->withQueryString();
        }

        return view('admin.audit-logs', $data);
    }

    protected function applyDateRange($query, array $filters, string $column = 'created_at'): void
    {
        if (!empty($filters['from'])) {
            $query->whereDate($column, '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate($column, '<=', $filters['to']);
        }
    }
}
