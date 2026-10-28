<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\VpsSpec;
use Illuminate\Support\Facades\DB;

/**
 * Engine billing SaaS: hitung harga, generate invoice dengan line items
 * immutable, kelola coupon, credit balance, prorate, dan renewal.
 *
 * Semua perhitungan pakai float dengan round 2 decimal. Semua write
 * ke database dibungkus DB::transaction.
 */
class BillingService
{
    public const CYCLE_CONFIG = [
        'monthly'     => ['months' => 1,  'discount_percent' => 0],
        'quarterly'   => ['months' => 3,  'discount_percent' => 5],
        'semi_annual' => ['months' => 6,  'discount_percent' => 10],
        'annual'      => ['months' => 12, 'discount_percent' => 15],
    ];

    /**
     * Hitung breakdown harga berdasarkan spec + cycle + coupon + credit.
     *
     * @return array{
     *   base_monthly: float, months: int, cycle_discount_percent: int,
     *   gross: float, cycle_discount: float, coupon_discount: float,
     *   subtotal_after_discount: float, tax_rate: float, tax_amount: float,
     *   credit_available: float, credit_applied: float,
     *   total: float, effective_monthly: float,
     *   coupon: array|null, tax: array|null, currency: string,
     *   items: array<int, array>
     * }
     */
    public function calculatePrice(
        VpsSpec $spec,
        string $cycle,
        ?string $couponCode = null,
        bool $applyCredit = false,
        ?User $customer = null,
        ?TaxRate $taxRate = null
    ): array {
        $cycleConf = self::CYCLE_CONFIG[$cycle] ?? self::CYCLE_CONFIG['monthly'];
        $baseMonthly = (float) $spec->sell_price;
        $months = $cycleConf['months'];
        $cycleDiscountPercent = $cycleConf['discount_percent'];

        $gross = $this->round($baseMonthly * $months);
        $cycleDiscount = $this->round($gross * ($cycleDiscountPercent / 100));
        $subtotalAfterCycle = $this->round($gross - $cycleDiscount);

        // Coupon
        $couponInfo = null;
        $couponDiscount = 0.0;
        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if ($coupon) {
                $check = $coupon->canBeRedeemedBy($customer, $subtotalAfterCycle, $spec->id, $cycle);
                if ($check['ok']) {
                    $couponDiscount = $coupon->calculateDiscount($subtotalAfterCycle);
                    $couponInfo = [
                        'id' => $coupon->id,
                        'code' => $coupon->code,
                        'name' => $coupon->name,
                        'type' => $coupon->type,
                        'value' => $coupon->value,
                    ];
                } else {
                    $couponInfo = [
                        'error' => $check['reason'],
                        'code' => $coupon->code,
                    ];
                }
            } else {
                $couponInfo = ['error' => 'Kupon tidak ditemukan.', 'code' => $couponCode];
            }
        }

        $subtotalAfterCoupon = $this->round($subtotalAfterCycle - $couponDiscount);

        // Tax
        $taxRateValue = 0.0;
        $taxAmount = 0.0;
        $taxInfo = null;
        if ($taxRate && $taxRate->is_active) {
            $taxRateValue = (float) $taxRate->rate;
            $taxAmount = $this->round($subtotalAfterCoupon * $taxRateValue);
            $taxInfo = [
                'id' => $taxRate->id,
                'code' => $taxRate->code,
                'name' => $taxRate->name,
                'rate' => $taxRateValue,
            ];
        }

        $totalBeforeCredit = $this->round($subtotalAfterCoupon + $taxAmount);

        // Credit
        $creditAvailable = $customer ? $customer->creditBalance() : 0.0;
        $creditApplied = 0.0;
        if ($applyCredit && $creditAvailable > 0) {
            $creditApplied = min($creditAvailable, $totalBeforeCredit);
            $creditApplied = $this->round($creditApplied);
        }

        $total = max(0, $this->round($totalBeforeCredit - $creditApplied));
        $effectiveMonthly = $months > 0 ? $this->round($total / $months) : $total;

        // Line items breakdown untuk generateInvoice.
        $items = [
            [
                'type' => 'service',
                'description' => "{$spec->name} — {$months} bulan",
                'quantity' => $months,
                'unit_price' => $baseMonthly,
                'subtotal' => $gross,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total' => $gross,
                'vps_spec_id' => $spec->id,
                'metadata' => ['cycle' => $cycle],
            ],
        ];

        if ($cycleDiscount > 0) {
            $items[] = [
                'type' => 'discount',
                'description' => "Diskon siklus {$cycleDiscountPercent}%",
                'quantity' => 1,
                'unit_price' => -$cycleDiscount,
                'subtotal' => -$cycleDiscount,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total' => -$cycleDiscount,
                'metadata' => ['kind' => 'cycle_discount'],
            ];
        }

        if ($couponDiscount > 0) {
            $items[] = [
                'type' => 'discount',
                'description' => "Kupon {$couponInfo['code']}",
                'quantity' => 1,
                'unit_price' => -$couponDiscount,
                'subtotal' => -$couponDiscount,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total' => -$couponDiscount,
                'metadata' => ['kind' => 'coupon', 'coupon_id' => $couponInfo['id'] ?? null],
            ];
        }

        if ($taxAmount > 0) {
            $items[] = [
                'type' => 'tax',
                'description' => $taxInfo['name'],
                'quantity' => 1,
                'unit_price' => $taxAmount,
                'subtotal' => $taxAmount,
                'tax_rate' => $taxRateValue,
                'tax_amount' => $taxAmount,
                'total' => $taxAmount,
                'metadata' => ['kind' => 'tax'],
            ];
        }

        if ($creditApplied > 0) {
            $items[] = [
                'type' => 'credit_apply',
                'description' => 'Saldo credit dipakai',
                'quantity' => 1,
                'unit_price' => -$creditApplied,
                'subtotal' => -$creditApplied,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total' => -$creditApplied,
                'metadata' => ['kind' => 'credit_apply'],
            ];
        }

        return [
            'base_monthly' => $baseMonthly,
            'months' => $months,
            'cycle_discount_percent' => $cycleDiscountPercent,
            'gross' => $gross,
            'cycle_discount' => $cycleDiscount,
            'coupon_discount' => $couponDiscount,
            'subtotal_after_discount' => $subtotalAfterCoupon,
            'tax_rate' => $taxRateValue,
            'tax_amount' => $taxAmount,
            'credit_available' => $this->round($creditAvailable),
            'credit_applied' => $creditApplied,
            'total' => $total,
            'effective_monthly' => $effectiveMonthly,
            'coupon' => $couponInfo,
            'tax' => $taxInfo,
            'currency' => 'IDR',
            'items' => $items,
        ];
    }

    /**
     * Generate invoice + immutable InvoiceItems + apply coupon redemption + credit deduction.
     * Dibungkus DB::transaction.
     */
    public function generateInvoice(
        Order $order,
        array $breakdown,
        array $options = []
    ): Invoice {
        $isRenewal = $options['is_renewal'] ?? false;
        $periodStart = $options['period_start'] ?? null;
        $periodEnd = $options['period_end'] ?? null;
        $subscriptionId = $options['subscription_id'] ?? $order->subscription_id;

        return DB::transaction(function () use ($order, $breakdown, $isRenewal, $periodStart, $periodEnd, $subscriptionId) {
            $prefix = $isRenewal ? 'INV-RN-' : 'INV-';
            $invoiceNumber = $prefix . date('Ym') . '-' . str_pad((string) ($order->id + random_int(100, 999)), 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'subscription_id' => $subscriptionId,
                'tax_rate_id' => $breakdown['tax']['id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'amount' => $breakdown['total'],
                'subtotal' => $breakdown['gross'],
                'discount_total' => $breakdown['cycle_discount'] + $breakdown['coupon_discount'],
                'tax_total' => $breakdown['tax_amount'],
                'credit_applied' => $breakdown['credit_applied'],
                'currency' => $breakdown['currency'],
                'status' => $breakdown['total'] > 0 ? 'sent' : 'paid',
                'issued_at' => now(),
                'due_at' => now()->addDays(1),
                'paid_at' => $breakdown['total'] > 0 ? null : now(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'is_renewal' => $isRenewal,
            ]);

            foreach ($breakdown['items'] as $item) {
                InvoiceItem::create(array_merge($item, [
                    'invoice_id' => $invoice->id,
                    'created_at' => now(),
                ]));
            }

            // Kalau coupon dipakai, catat redemption + increment counter.
            if (!empty($breakdown['coupon']['id']) && $breakdown['coupon_discount'] > 0) {
                CouponRedemption::create([
                    'coupon_id' => $breakdown['coupon']['id'],
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $order->customer_id,
                    'discount_amount' => $breakdown['coupon_discount'],
                ]);
                Coupon::where('id', $breakdown['coupon']['id'])->increment('redeemed_count');
            }

            // Kalau credit dipakai, tulis ledger (negative).
            if ($breakdown['credit_applied'] > 0) {
                $this->deductCredit(
                    User::findOrFail($order->customer_id),
                    (float) $breakdown['credit_applied'],
                    "Applied to invoice {$invoice->invoice_number}",
                    $invoice
                );
            }

            return $invoice->fresh(['items']);
        });
    }

    /**
     * Tambah credit ke customer (topup / refund).
     */
    public function addCredit(User $customer, float $amount, string $reason, array $refs = []): CreditTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($customer, $amount, $reason, $refs) {
            $newBalance = CreditTransaction::balanceFor($customer->id) + $amount;
            return CreditTransaction::create([
                'customer_id' => $customer->id,
                'type' => $refs['type'] ?? 'topup',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'order_id' => $refs['order_id'] ?? null,
                'invoice_id' => $refs['invoice_id'] ?? null,
                'refund_id' => $refs['refund_id'] ?? null,
                'actor_user_id' => $refs['actor_user_id'] ?? null,
            ]);
        });
    }

    /**
     * Kurangi credit customer (apply / adjustment negative).
     */
    public function deductCredit(User $customer, float $amount, string $reason, ?Invoice $invoice = null): CreditTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Deduct amount must be positive.');
        }
        $currentBalance = CreditTransaction::balanceFor($customer->id);
        if ($currentBalance < $amount) {
            throw new \RuntimeException("Saldo credit tidak cukup (butuh {$amount}, ada {$currentBalance}).");
        }

        return CreditTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'apply',
            'amount' => -$amount,
            'balance_after' => $currentBalance - $amount,
            'reason' => $reason,
            'invoice_id' => $invoice?->id,
        ]);
    }

    /**
     * PHASE 6 - Penyesuaian saldo manual oleh admin (tambah atau kurang).
     *
     * Baris user dikunci selama transaksi agar dua penyesuaian bersamaan
     * tidak membaca saldo yang sama. Saldo tidak boleh menjadi negatif.
     */
    public function adjustCredit(User $customer, float $signedAmount, string $reason, ?int $actorId = null): CreditTransaction
    {
        if (round($signedAmount, 2) == 0.0) {
            throw new \InvalidArgumentException('Nominal penyesuaian tidak boleh nol.');
        }

        return DB::transaction(function () use ($customer, $signedAmount, $reason, $actorId) {
            User::whereKey($customer->id)->lockForUpdate()->first();

            $balance = CreditTransaction::balanceFor($customer->id);
            $newBalance = round($balance + $signedAmount, 2);

            if ($newBalance < 0) {
                throw new \RuntimeException(
                    'Saldo tidak cukup untuk dikurangi. Saldo saat ini Rp ' . number_format($balance, 0, ',', '.') . '.'
                );
            }

            return CreditTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'adjustment',
                'amount' => round($signedAmount, 2),
                'balance_after' => $newBalance,
                'reason' => $reason,
                'actor_user_id' => $actorId,
            ]);
        });
    }

    /**
     * Renewal: buat invoice periode berikutnya untuk subscription.
     * Panggil ini dari scheduler harian. Return Invoice atau null kalau
     * subscription tidak eligible untuk renew.
     */
    public function renewSubscription(Subscription $subscription): ?Invoice
    {
        if (!$subscription->auto_renew) return null;
        if (!in_array($subscription->status, ['active', 'past_due', 'grace_period'], true)) return null;
        if (!$subscription->vpsSpec) return null;

        return DB::transaction(function () use ($subscription) {
            $spec = $subscription->vpsSpec;
            $cycle = $subscription->billing_cycle;
            $customer = $subscription->customer;
            $taxRate = TaxRate::defaultForCountry($customer->country ?? 'ID');

            $breakdown = $this->calculatePrice($spec, $cycle, null, false, $customer, $taxRate);

            // Buat order renewal (order baru sebagai wrapper transaksi).
            $renewalOrder = Order::create([
                'customer_id' => $subscription->customer_id,
                'vps_spec_id' => $spec->id,
                'subscription_id' => $subscription->id,
                'control_panel' => $subscription->vpsInstance?->control_panel ?? 'coolify',
                'datacenter_location' => $subscription->vpsInstance?->datacenter_location ?? 'indonesia',
                'os' => $subscription->vpsInstance?->os ?? 'ubuntu2404',
                'billing_cycle' => $cycle,
                'status' => 'pending',
                'channel' => 'website',
                'payment_method' => 'renewal',
                'amount' => $breakdown['total'],
                'currency' => 'IDR',
                'setup_fee' => 0,
                'last_status_change_at' => now(),
            ]);

            $months = self::CYCLE_CONFIG[$cycle]['months'] ?? 1;
            $periodStart = $subscription->current_period_end ?? now();
            $periodEnd = $periodStart->copy()->addMonths($months);

            $invoice = $this->generateInvoice($renewalOrder, $breakdown, [
                'is_renewal' => true,
                'subscription_id' => $subscription->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            $subscriptionUpdates = [
                'last_renewal_attempt_at' => now(),
            ];
            if ($invoice->status === 'paid') {
                $subscriptionUpdates['next_billing_at'] = $periodEnd;
                $subscriptionUpdates['current_period_end'] = $periodEnd;
            }
            $subscription->update($subscriptionUpdates);

            return $invoice;
        });
    }

    protected function round(float $value): float
    {
        return round($value, 2);
    }
}
