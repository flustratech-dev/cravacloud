<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\FulfillmentChecklist;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\SupplierPurchase;
use App\Services\FulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * PHASE 5 - Papan kerja fulfillment.
 *
 * Alur manual: Terima -> Beli di Supplier -> Setup -> Kirim kredensial.
 *
 * Controller ini hanya mengubah orders.fulfillment_stage dan daftar periksa.
 * Status order (paid, active, dll.) tetap diubah lewat alur yang sudah ada:
 * tandai lunas, provisioning, dan batal di AdminController.
 */
class FulfillmentController extends Controller
{
    use LogsAdminAudit;

    /** Order berstatus ini sedang dikerjakan (belum diserahkan). */
    protected const WORKING_STATUSES = ['paid', 'failed', 'provisioning'];

    /** Berapa hari order terkirim tetap tampil di kolom Terkirim. */
    protected const DELIVERED_WINDOW_DAYS = 7;

    public function __construct(
        protected FulfillmentService $fulfillment
    ) {
    }

    public function index()
    {
        $since = now()->subDays(self::DELIVERED_WINDOW_DAYS);

        $orders = Order::with(['customer', 'vpsSpec', 'vpsInstance', 'fulfillmentChecklists', 'supplierPurchases'])
            ->where(function ($q) use ($since) {
                $q->whereIn('status', array_merge(['pending'], self::WORKING_STATUSES))
                    ->orWhere(function ($q) use ($since) {
                        $q->where('status', 'active')
                            ->where(function ($q) use ($since) {
                                $q->where('delivered_at', '>=', $since)
                                    ->orWhere(function ($q) use ($since) {
                                        $q->whereNull('delivered_at')->where('starts_at', '>=', $since);
                                    });
                            });
                    });
            })
            ->get();

        $board = collect(FulfillmentService::columns())->map(fn () => collect())->all();

        foreach ($orders as $order) {
            $column = $this->fulfillment->columnFor($order);
            if ($column !== null) {
                $board[$column]->push($order);
            }
        }

        // Yang melewati ambang waktu tanggap naik ke atas, lalu yang paling lama menunggu.
        foreach ($board as $column => $items) {
            $board[$column] = $items->sortBy([
                fn ($a, $b) => (int) $this->fulfillment->isOverdue($b) <=> (int) $this->fulfillment->isOverdue($a),
                fn ($a, $b) => ($a->paid_at ?? $a->created_at) <=> ($b->paid_at ?? $b->created_at),
            ])->values();
        }

        $working = $orders->filter(fn (Order $o) => in_array($o->status, self::WORKING_STATUSES, true));

        return view('admin.fulfillment', [
            'board' => $board,
            'columns' => FulfillmentService::columns(),
            'fulfillment' => $this->fulfillment,
            'slaMinutes' => $this->fulfillment->slaMinutes(),
            'overdueCount' => $working->filter(fn (Order $o) => $this->fulfillment->isOverdue($o))->count(),
            'workingCount' => $working->count(),
            'deliveredTodayCount' => Order::where('delivered_at', '>=', now()->startOfDay())->count(),
            'handoverTemplate' => NotificationTemplate::where('code', NotificationTemplate::FULFILLMENT_HANDOVER)->first(),
        ]);
    }

    /**
     * Langkah "Beli di Supplier": catat nomor pesanan dan biaya riil.
     */
    public function recordPurchase(Request $request, int $id)
    {
        $order = Order::findOrFail($id);

        if (!in_array($order->status, self::WORKING_STATUSES, true)) {
            return back()->with('error', "Order #{$order->id} berstatus {$order->status}; pembelian hanya dicatat untuk order yang sudah dibayar.");
        }

        $validated = $request->validate([
            'supplier_order_no' => ['required', 'string', 'max:120'],
            'actual_cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'purchased_at' => ['nullable', 'date'],
            'supplier_expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'supplier_order_no.required' => 'Nomor pesanan Supplier wajib diisi agar pembelian dapat ditelusuri.',
            'actual_cost.required' => 'Biaya riil wajib diisi untuk perhitungan margin.',
        ]);

        $purchasedAt = Carbon::parse($validated['purchased_at'] ?? now());
        // Pembelian di Supplier bersifat prepaid satu bulan.
        $expiresAt = !empty($validated['supplier_expires_at'])
            ? Carbon::parse($validated['supplier_expires_at'])
            : $purchasedAt->copy()->addMonth();

        if ($expiresAt->lessThanOrEqualTo($purchasedAt)) {
            return back()->withErrors(['supplier_expires_at' => 'Masa aktif di Supplier harus setelah tanggal pembelian.'])->withInput();
        }

        DB::transaction(function () use ($order, $validated, $purchasedAt, $expiresAt) {
            SupplierPurchase::create([
                'order_id' => $order->id,
                'vps_instance_id' => $order->vpsInstance?->id,
                'supplier' => 'supplier',
                'supplier_order_no' => $validated['supplier_order_no'],
                'actual_cost' => $validated['actual_cost'],
                'purchased_at' => $purchasedAt,
                'supplier_expires_at' => $expiresAt,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $order->update(['fulfillment_stage' => 'purchased']);
        });

        $this->audit(
            'fulfillment.purchase_recorded',
            "Mencatat pembelian Supplier {$validated['supplier_order_no']} untuk order #{$order->id} senilai Rp "
                . number_format((float) $validated['actual_cost'], 0, ',', '.') . '.',
            $order
        );

        return back()->with('success', "Pembelian Supplier untuk order #{$order->id} tercatat.");
    }

    /**
     * Pindah tahap kerja: purchased <-> setup.
     */
    public function moveStage(Request $request, int $id)
    {
        $order = Order::findOrFail($id);

        $request->validate(['stage' => ['required', 'in:purchased,setup']]);
        $to = $request->input('stage');

        if (!in_array($order->status, self::WORKING_STATUSES, true)) {
            return back()->with('error', "Order #{$order->id} tidak sedang dikerjakan.");
        }

        $allowed = [
            'purchased' => ['setup'],
            'setup' => ['purchased'],
        ];

        if (!in_array($to, $allowed[$order->fulfillment_stage] ?? [], true)) {
            return back()->with('error', 'Perpindahan tahap tidak valid. Catat pembelian Supplier terlebih dahulu.');
        }

        $order->update(['fulfillment_stage' => $to]);

        $this->audit(
            'fulfillment.stage_changed',
            "Memindahkan order #{$order->id} ke tahap " . ($to === 'setup' ? 'Sedang Setup' : 'Dibeli di Supplier') . '.',
            $order
        );

        return back()->with('success', "Order #{$order->id} dipindahkan.");
    }

    /**
     * Centang atau batalkan satu butir daftar periksa.
     */
    public function toggleStep(Request $request, int $id, string $step)
    {
        $order = Order::with('vpsSpec')->findOrFail($id);

        if (!array_key_exists($step, $this->fulfillment->stepsFor($order))) {
            abort(404);
        }

        $row = FulfillmentChecklist::firstOrNew(['order_id' => $order->id, 'step_key' => $step]);
        $row->is_done = !$row->is_done;
        $row->completed_at = $row->is_done ? now() : null;
        $row->completed_by = $row->is_done ? Auth::id() : null;
        $row->save();

        return back()->with('success', $row->is_done ? 'Langkah ditandai selesai.' : 'Tanda selesai dibatalkan.');
    }
}
