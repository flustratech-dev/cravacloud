<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\SupplierPurchase;
use App\Models\VpsInstance;
use App\Services\MarginService;
use App\Services\SupplierCoverageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * PHASE 8 - Catatan pembelian di Supplier.
 *
 * Pembelian pertama dicatat dari papan fulfillment (Phase 5). Halaman ini
 * menampilkan seluruh catatan, membandingkan dua tanggal masa aktif, dan
 * menerima catatan pembelian ulang tiap bulan untuk instance yang sudah berjalan.
 */
class SupplierPurchaseController extends Controller
{
    use LogsAdminAudit;

    public function __construct(
        protected SupplierCoverageService $coverage,
        protected MarginService $margin
    ) {
    }

    public function index(Request $request)
    {
        $rows = $this->coverage->rows();

        $filter = $request->query('status');
        $visibleRows = in_array($filter, [
            SupplierCoverageService::STATUS_URGENT,
            SupplierCoverageService::STATUS_GAP,
            SupplierCoverageService::STATUS_MISSING,
            SupplierCoverageService::STATUS_COVERED,
        ], true) ? $rows->where('status', $filter)->values() : $rows;

        return view('admin.supplier-purchases', [
            'rows' => $visibleRows,
            'counts' => [
                SupplierCoverageService::STATUS_URGENT => $rows->where('status', SupplierCoverageService::STATUS_URGENT)->count(),
                SupplierCoverageService::STATUS_GAP => $rows->where('status', SupplierCoverageService::STATUS_GAP)->count(),
                SupplierCoverageService::STATUS_MISSING => $rows->where('status', SupplierCoverageService::STATUS_MISSING)->count(),
                SupplierCoverageService::STATUS_COVERED => $rows->where('status', SupplierCoverageService::STATUS_COVERED)->count(),
            ],
            'filter' => $filter,
            'purchases' => SupplierPurchase::with(['vpsInstance.customer', 'order.customer', 'creator'])
                ->latest('purchased_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'monthly' => $this->margin->monthly(6),
            'instances' => VpsInstance::with('customer')
                ->whereIn('status', SupplierCoverageService::LIVE_STATUSES)
                ->orderBy('hostname')
                ->get(),
        ]);
    }

    /**
     * Catat pembelian ulang (perpanjangan bulanan) untuk instance yang berjalan.
     */
    public function store(Request $request)
    {
        $validated = $this->validatePurchase($request, true);
        $instance = VpsInstance::findOrFail($validated['vps_instance_id']);

        $purchase = SupplierPurchase::create($validated + [
            'order_id' => $instance->order_id,
            'supplier' => 'supplier',
            'created_by' => Auth::id(),
        ]);

        $this->audit(
            'supplier.purchase_recorded',
            "Mencatat pembelian Supplier {$purchase->supplier_order_no} untuk {$instance->hostname} senilai Rp "
                . number_format((float) $purchase->actual_cost, 0, ',', '.') . '.',
            $purchase
        );

        return back()->with('success', "Pembelian {$purchase->supplier_order_no} tercatat.");
    }

    public function update(Request $request, int $id)
    {
        $purchase = SupplierPurchase::findOrFail($id);
        $before = $purchase->only(['supplier_order_no', 'actual_cost', 'purchased_at', 'supplier_expires_at']);

        $purchase->update($this->validatePurchase($request, false));

        $this->audit(
            'supplier.purchase_updated',
            "Memperbaiki catatan pembelian Supplier {$purchase->supplier_order_no}.",
            $purchase,
            ['sebelum' => $before, 'sesudah' => $purchase->only(array_keys($before))]
        );

        return back()->with('success', 'Catatan pembelian diperbarui.');
    }

    public function destroy(int $id)
    {
        $purchase = SupplierPurchase::findOrFail($id);

        $this->audit(
            'supplier.purchase_deleted',
            "Menghapus catatan pembelian Supplier {$purchase->supplier_order_no} senilai Rp "
                . number_format((float) $purchase->actual_cost, 0, ',', '.') . '.',
            $purchase,
            ['dihapus' => $purchase->only(['supplier_order_no', 'actual_cost', 'purchased_at', 'supplier_expires_at', 'order_id', 'vps_instance_id'])]
        );

        $purchase->delete();

        return back()->with('success', 'Catatan pembelian dihapus.');
    }

    protected function validatePurchase(Request $request, bool $creating): array
    {
        $rules = [
            'supplier_order_no' => ['required', 'string', 'max:120'],
            'actual_cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'purchased_at' => ['required', 'date'],
            'supplier_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($creating) {
            $rules['vps_instance_id'] = ['required', 'integer', 'exists:vps_instances,id'];
        }

        $validated = $request->validate($rules);

        $purchasedAt = Carbon::parse($validated['purchased_at']);
        // Pembelian di Supplier bersifat prepaid satu bulan.
        $expiresAt = !empty($validated['supplier_expires_at'])
            ? Carbon::parse($validated['supplier_expires_at'])
            : $purchasedAt->copy()->addMonth();

        if ($expiresAt->lessThanOrEqualTo($purchasedAt)) {
            throw ValidationException::withMessages([
                'supplier_expires_at' => 'Masa aktif di Supplier harus setelah tanggal pembelian.',
            ]);
        }

        $validated['purchased_at'] = $purchasedAt;
        $validated['supplier_expires_at'] = $expiresAt;

        return $validated;
    }
}
