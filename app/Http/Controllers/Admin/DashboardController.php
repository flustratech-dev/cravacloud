<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\Provisioning\Tencent\BalanceGuard;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. KPI: Perlu Tindakan Admin (failed + paid belum ada VPS + renew_pending + open tickets)
        $ordersFailed = Order::where('status', 'failed')->count();
        $ordersNeedsManual = Order::where('status', 'paid')
            ->whereDoesntHave('vpsInstance')
            ->count();
        $instancesRenewPending = VpsInstance::where('provider_meta->renew_pending', true)->count();
        $openTicketsCount = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
        $actionNeededTotal = $ordersFailed + $ordersNeedsManual + $instancesRenewPending + $openTicketsCount;

        // 2. KPI: VPS Running (Auto vs Manual)
        $vpsRunningTotal = VpsInstance::where('status', 'running')->count();
        $vpsRunningAuto = VpsInstance::where('status', 'running')->where('provider', 'tencent')->count();
        $vpsRunningManual = VpsInstance::where('status', 'running')->where('provider', '!=', 'tencent')->count();

        // 3. KPI: Saldo Tencent & Kuota Region
        $rawBalance = Cache::get('tencent:balance');
        $balanceDivisor = max(1, (int) config('services.tencent.balance_divisor', 100));
        $balanceUsd = $rawBalance !== null
            ? round((float) $rawBalance / $balanceDivisor, 2)
            : (float) Cache::get('tencent:balance:last', 0.0);
        $balanceCheckedAt = Cache::get('tencent:balance:checked_at');

        $jktPools = \App\Models\UpstreamQuotaPool::where('region_code', 'ap-jakarta')->get();
        $sgpPools = \App\Models\UpstreamQuotaPool::where('region_code', 'ap-singapore')->get();
        $jktQuota = $jktPools->isNotEmpty()
            ? ($jktPools->sum('used') + $jktPools->sum('reserved')) . '/' . $jktPools->sum('capacity')
            : '-';
        $sgpQuota = $sgpPools->isNotEmpty()
            ? ($sgpPools->sum('used') + $sgpPools->sum('reserved')) . '/' . $sgpPools->sum('capacity')
            : '-';

        // 4. KPI: Omzet & Margin Riil Bulan Ini
        $startOfMonth = Carbon::now()->startOfMonth();
        $monthlyRevenue = (float) Order::whereIn('status', ['active', 'provisioning', 'paid'])
            ->where('created_at', '>=', $startOfMonth)
            ->sum('amount');

        $rate = max(1, app(SettingsService::class)->int('tencent_usd_idr', 16500));
        $bundlePricesUsd = (array) config('services.tencent.bundle_prices_usd', []);

        // Prioritaskan HPP riil bulan ini dari SupplierPurchase; fallback ke estimasi bundle USD / cost_price.
        $actualHppIdr = (float) \App\Models\SupplierPurchase::where('purchased_at', '>=', $startOfMonth)->sum('actual_cost');
        if ($actualHppIdr > 0) {
            $monthlyHppIdr = $actualHppIdr;
        } else {
            $instancesRunning = VpsInstance::with('order.vpsSpec')->where('status', 'running')->get();
            $monthlyHppIdr = $instancesRunning->sum(function ($vps) use ($rate, $bundlePricesUsd) {
                $spec = $vps->order?->vpsSpec;
                if (! $spec) {
                    return 0.0;
                }
                if ($spec->upstream_bundle_id && isset($bundlePricesUsd[$spec->upstream_bundle_id])) {
                    return (float) $bundlePricesUsd[$spec->upstream_bundle_id] * $rate;
                }

                return (float) ($spec->cost_price ?? 0);
            });
        }
        $monthlyMarginPercent = $monthlyRevenue > 0
            ? round((($monthlyRevenue - $monthlyHppIdr) / $monthlyRevenue) * 100, 1)
            : 0.0;

        // Backward compatibility variables for existing views/tests
        $pending_orders = Order::where('status', 'pending')->count();
        $active_vps = $vpsRunningTotal;
        $total_customers = User::where('is_admin', false)->count();
        $total_revenue = Order::whereIn('status', ['active', 'provisioning'])->sum('amount');
        $recent_orders = Order::with(['customer', 'vpsSpec'])->latest()->take(6)->get();
        $open_tickets = SupportTicket::with(['customer'])->whereIn('status', ['open', 'in_progress'])->latest()->take(5)->get();
        $orders_web = Order::where('channel', 'website')->count();
        $orders_shopee = Order::where('channel', 'shopee')->count();
        $specs = VpsSpec::withCount('orders')
            ->where('category', 'vps')
            ->where(function ($q) {
                $q->where('is_active', true)->orHas('orders');
            })
            ->orderBy('sell_price')
            ->get();

        return view('admin.index', compact(
            'actionNeededTotal',
            'ordersFailed',
            'ordersNeedsManual',
            'instancesRenewPending',
            'openTicketsCount',
            'vpsRunningTotal',
            'vpsRunningAuto',
            'vpsRunningManual',
            'balanceUsd',
            'balanceCheckedAt',
            'jktQuota',
            'sgpQuota',
            'monthlyRevenue',
            'monthlyMarginPercent',
            'pending_orders',
            'active_vps',
            'total_customers',
            'total_revenue',
            'recent_orders',
            'open_tickets',
            'orders_web',
            'orders_shopee',
            'specs'
        ));
    }
}
