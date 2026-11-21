<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Services\AdminAlertService;
use App\Services\Provisioning\ProvisioningGate;
use Illuminate\Console\Command;

/**
 * Watchdog task provisioning stuck (> 20 menit) dan order otomatis lunas tertahan (> 15 menit).
 * Menggantikan jadwal cron fulfillment:remind-admin pada era auto-provisioning Tencent.
 */
class ProvisioningAlertStuckCommand extends Command
{
    protected $signature = 'provisioning:alert-stuck {--dry-run : Tampilkan saja tanpa kirim alert}';

    protected $description = 'Deteksi ProvisioningTask macet >20 menit dan order paid otomatis tertahan >15 menit.';

    public function handle(AdminAlertService $alerts, ProvisioningGate $gate): int
    {
        $dry = (bool) $this->option('dry-run');

        $stuckTasks = ProvisioningTask::with(['order', 'vpsInstance'])
            ->whereIn('status', ['pending', 'running'])
            ->where('updated_at', '<=', now()->subMinutes(20))
            ->get();

        $heldPaidOrders = Order::with(['vpsSpec', 'customer'])
            ->where('status', 'paid')
            ->where('order_type', 'new')
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', now()->subMinutes(15))
            ->whereDoesntHave('vpsInstance')
            ->whereDoesntHave('provisioningTasks', fn ($q) => $q->whereIn('status', ['pending', 'running', 'succeeded']))
            ->get()
            ->filter(function (Order $order) use ($gate) {
                $spec = $order->vpsSpec;

                return $spec
                    && $spec->hasUpstreamMapping()
                    && $gate->allows($spec)
                    && empty($order->metadata['manual_fulfillment']);
            });

        $total = $stuckTasks->count() + $heldPaidOrders->count();

        if ($total === 0) {
            $this->info('Tidak ada task stuck atau order tertahan.');

            return self::SUCCESS;
        }

        $lines = [];
        foreach ($stuckTasks as $task) {
            $lines[] = "• Task #{$task->id} ({$task->kind}) status {$task->status} sejak {$task->updated_at->diffForHumans()} — Order #" . ($task->order_id ?? '-');
        }
        foreach ($heldPaidOrders as $order) {
            $lines[] = "• Order #{$order->id} (" . ($order->vpsSpec?->name ?? 'VPS') . ") lunas {$order->paid_at->diffForHumans()} namun belum ada task/instance.";
        }

        $body = implode("\n", $lines);
        $this->warn($body);

        if ($dry) {
            return self::SUCCESS;
        }

        $dedupeId = $stuckTasks->pluck('id')->implode(',') . '|' . $heldPaidOrders->pluck('id')->implode(',');

        $alerts->alert(
            'provisioning.stuck',
            [
                'title' => "{$total} Task Provisioning Stuck / Order Tertahan",
                'details' => [
                    'Task Macet (>20m)' => $stuckTasks->count() . ' task',
                    'Order Tertahan (>15m)' => $heldPaidOrders->count() . ' order',
                ],
                'body' => $body,
                'action_url' => url('/admin/monitoring?tab=tasks'),
            ],
            dedupeKey: 'stuck-' . sha1($dedupeId),
            cooldownSeconds: 7200,
        );

        return self::SUCCESS;
    }
}
