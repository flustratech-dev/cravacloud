<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\SupplierPurchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * PHASE 6 - Perhitungan margin riil.
 *
 * Biaya diambil dari supplier_purchases (dicatat di papan fulfillment),
 * bukan dari vps_specs.cost_price yang hanya harga rencana. Dengan margin
 * tipis, laporan berbasis harga rencana dapat menyesatkan.
 *
 * Pengelompokan per bulan dilakukan di PHP agar kueri tetap portabel
 * antara MySQL dan SQLite.
 */
class MarginService
{
    /**
     * Ringkasan per bulan: pendapatan (faktur lunas) vs biaya (pembelian Supplier).
     *
     * @return Collection<int, array{month:string,label:string,revenue:float,cost:float,margin:float,margin_percent:?float}>
     */
    public function monthly(int $months = 6): Collection
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $revenue = Invoice::where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn (Invoice $i) => $i->paid_at->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $cost = SupplierPurchase::where('purchased_at', '>=', $start)
            ->get(['actual_cost', 'purchased_at'])
            ->groupBy(fn (SupplierPurchase $p) => $p->purchased_at->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('actual_cost'));

        return collect(range(0, $months - 1))->map(function (int $offset) use ($start, $revenue, $cost) {
            $month = $start->copy()->addMonths($offset);
            $key = $month->format('Y-m');
            $rev = $revenue->get($key, 0.0);
            $cst = $cost->get($key, 0.0);

            return [
                'month' => $key,
                'label' => $month->translatedFormat('M Y'),
                'revenue' => $rev,
                'cost' => $cst,
                'margin' => $rev - $cst,
                'margin_percent' => $rev > 0 ? round(($rev - $cst) / $rev * 100, 1) : null,
            ];
        });
    }

    /**
     * Margin per order yang sudah dibayar dalam N hari terakhir.
     * Order tanpa catatan pembelian ditandai agar kelengkapan data terlihat.
     */
    public function perOrder(int $days = 60): Collection
    {
        return Order::with(['customer', 'vpsSpec', 'supplierPurchases'])
            ->whereIn('status', ['paid', 'provisioning', 'active', 'grace_period', 'suspended', 'expired', 'terminated'])
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays($days))
            ->orderByDesc('paid_at')
            ->get()
            ->map(function (Order $order) {
                $revenue = (float) $order->amount;
                $hasCost = $order->supplierPurchases->isNotEmpty();
                $cost = (float) $order->supplierPurchases->sum('actual_cost');
                $plannedCost = (float) ($order->vpsSpec?->cost_price ?? 0);

                return [
                    'order' => $order,
                    'revenue' => $revenue,
                    'cost' => $hasCost ? $cost : null,
                    'planned_cost' => $plannedCost,
                    'margin' => $hasCost ? $revenue - $cost : null,
                    'margin_percent' => ($hasCost && $revenue > 0) ? round(($revenue - $cost) / $revenue * 100, 1) : null,
                    'deviation' => $hasCost ? $cost - $plannedCost : null,
                ];
            });
    }

    /**
     * Jumlah order lunas yang belum punya catatan biaya riil.
     */
    public function missingCostCount(int $days = 60): int
    {
        return Order::whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subDays($days))
            ->whereIn('status', ['paid', 'provisioning', 'active', 'grace_period', 'suspended', 'expired', 'terminated'])
            ->whereDoesntHave('supplierPurchases')
            ->count();
    }
}
