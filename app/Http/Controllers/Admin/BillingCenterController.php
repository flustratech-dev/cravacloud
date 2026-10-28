<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\BillingService;
use App\Services\MarginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * PHASE 6 - Billing Center.
 *
 * Membuka enam model billing yang sudah ada di basis data, ditambah laporan
 * margin riil. Perubahan status order TIDAK dilakukan di sini; untuk menandai
 * lunas dipakai alur mark-paid yang sudah ada di AdminController.
 */
class BillingCenterController extends Controller
{
    use LogsAdminAudit;

    public const TABS = ['invoices', 'subscriptions', 'refunds', 'coupons', 'taxes', 'credits', 'margin'];

    public function __construct(
        protected BillingService $billing,
        protected MarginService $margin
    ) {
    }

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'invoices';

        $data = [
            'tab' => $tab,
            'summary' => $this->summary(),
        ];

        $data += match ($tab) {
            'invoices' => $this->invoicesData($request),
            'subscriptions' => $this->subscriptionsData($request),
            'refunds' => $this->refundsData(),
            'coupons' => ['coupons' => Coupon::withCount('redemptions')->latest()->paginate(20)->withQueryString()],
            'taxes' => ['taxRates' => TaxRate::orderByDesc('is_active')->orderBy('code')->get()],
            'credits' => $this->creditsData($request),
            'margin' => [
                'monthly' => $this->margin->monthly(6),
                'perOrder' => $this->margin->perOrder(60),
                'missingCost' => $this->margin->missingCostCount(60),
            ],
        };

        return view('admin.billing', $data);
    }

    // ===================================================================
    // Faktur
    // ===================================================================

    public function resendInvoice(int $id)
    {
        $invoice = Invoice::with('order.customer')->findOrFail($id);
        $customer = $invoice->order?->customer;

        if (!$customer || !$customer->email) {
            return back()->with('error', 'Faktur ini tidak terhubung ke pelanggan dengan alamat surel.');
        }

        try {
            $customer->notify(new InvoiceCreatedNotification($invoice));
        } catch (\Throwable $e) {
            Log::error('billing.invoice_resend_failed', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Faktur gagal dikirim ulang: ' . $e->getMessage());
        }

        $this->audit('billing.invoice_resent', "Mengirim ulang faktur {$invoice->invoice_number} ke {$customer->email}.", $invoice);

        return back()->with('success', "Faktur {$invoice->invoice_number} dikirim ulang ke {$customer->email}.");
    }

    // ===================================================================
    // Langganan
    // ===================================================================

    public function toggleAutoRenew(int $id)
    {
        $subscription = Subscription::findOrFail($id);
        $subscription->update(['auto_renew' => !$subscription->auto_renew]);

        $this->audit(
            'billing.subscription_auto_renew',
            "Mengubah perpanjangan otomatis langganan #{$subscription->id} menjadi "
                . ($subscription->auto_renew ? 'aktif' : 'nonaktif') . '.',
            $subscription
        );

        return back()->with('success', 'Pengaturan perpanjangan otomatis diperbarui.');
    }

    // ===================================================================
    // Pengembalian dana
    // ===================================================================

    public function storeRefund(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', Rule::in(['credit_balance', 'manual_transfer'])],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $invoice = Invoice::with('order')->findOrFail($validated['invoice_id']);

        if ($invoice->status !== 'paid') {
            return back()->with('error', 'Pengembalian dana hanya untuk faktur yang sudah lunas.');
        }

        $customerId = $invoice->order?->customer_id;
        if (!$customerId) {
            return back()->with('error', 'Faktur ini tidak terhubung ke pelanggan.');
        }

        $refundable = $this->refundableAmount($invoice);
        if ((float) $validated['amount'] > $refundable) {
            return back()->withInput()->with(
                'error',
                'Nominal melebihi sisa yang dapat dikembalikan (Rp ' . number_format($refundable, 0, ',', '.') . ').'
            );
        }

        $refund = Refund::create([
            'invoice_id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'customer_id' => $customerId,
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'status' => 'pending',
            'reason' => $validated['reason'],
            'actor_user_id' => Auth::id(),
        ]);

        $this->audit(
            'billing.refund_created',
            "Mengajukan pengembalian dana Rp " . number_format((float) $refund->amount, 0, ',', '.')
                . " untuk faktur {$invoice->invoice_number}.",
            $refund
        );

        return back()->with('success', 'Pengajuan pengembalian dana tercatat. Setujui untuk memprosesnya.');
    }

    public function approveRefund(int $id)
    {
        try {
            $refund = DB::transaction(function () use ($id) {
                $refund = Refund::with(['invoice', 'customer'])->lockForUpdate()->findOrFail($id);

                if ($refund->status !== 'pending') {
                    throw new \RuntimeException('Pengembalian dana ini sudah diproses atau dibatalkan.');
                }

                if ($refund->method === 'credit_balance') {
                    $this->billing->addCredit($refund->customer, (float) $refund->amount, "Pengembalian dana faktur {$refund->invoice->invoice_number}", [
                        'type' => 'refund',
                        'refund_id' => $refund->id,
                        'invoice_id' => $refund->invoice_id,
                        'order_id' => $refund->order_id,
                        'actor_user_id' => Auth::id(),
                    ]);
                }

                $refund->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'actor_user_id' => Auth::id(),
                ]);

                return $refund;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit(
            'billing.refund_processed',
            "Memproses pengembalian dana #{$refund->id} via "
                . ($refund->method === 'credit_balance' ? 'saldo pelanggan' : 'transfer manual') . '.',
            $refund
        );

        return back()->with('success', $refund->method === 'credit_balance'
            ? 'Pengembalian dana diproses dan saldo pelanggan bertambah.'
            : 'Pengembalian dana ditandai selesai. Pastikan transfer manual sudah dilakukan.');
    }

    public function cancelRefund(int $id)
    {
        $refund = Refund::findOrFail($id);

        if ($refund->status !== 'pending') {
            return back()->with('error', 'Hanya pengajuan yang belum diproses yang dapat dibatalkan.');
        }

        $refund->update(['status' => 'cancelled', 'actor_user_id' => Auth::id()]);

        $this->audit('billing.refund_cancelled', "Membatalkan pengajuan pengembalian dana #{$refund->id}.", $refund);

        return back()->with('success', 'Pengajuan pengembalian dana dibatalkan.');
    }

    // ===================================================================
    // Kupon
    // ===================================================================

    public function storeCoupon(Request $request)
    {
        $validated = $this->validateCoupon($request);
        $validated['code'] = strtoupper(trim($validated['code']));

        // Keunikan diperiksa pada kode yang sudah dikapitalkan, agar "promo10"
        // dan "PROMO10" tidak tercatat sebagai dua kupon berbeda.
        if (Coupon::where('code', $validated['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode kupon sudah dipakai.']);
        }

        $coupon = Coupon::create($validated + ['redeemed_count' => 0]);

        $this->audit('billing.coupon_created', "Membuat kupon {$coupon->code}.", $coupon);

        return back()->with('success', "Kupon {$coupon->code} dibuat.");
    }

    public function updateCoupon(Request $request, int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $validated = $this->validateCoupon($request, $coupon);
        unset($validated['code']);

        $coupon->update($validated);

        $this->audit('billing.coupon_updated', "Memperbarui kupon {$coupon->code}.", $coupon);

        return back()->with('success', "Kupon {$coupon->code} diperbarui.");
    }

    protected function validateCoupon(Request $request, ?Coupon $coupon = null): array
    {
        $validated = $request->validate([
            'code' => [$coupon ? 'nullable' : 'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0.01'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'max_per_customer' => ['required', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable'],
        ], [
            'code.regex' => 'Kode kupon hanya boleh huruf, angka, garis bawah, dan tanda hubung.',
            'ends_at.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ]);

        if ($validated['type'] === 'percent' && (float) $validated['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'Diskon persen tidak boleh lebih dari 100.']);
        }

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    // ===================================================================
    // Pajak
    // ===================================================================

    public function storeTaxRate(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/', 'unique:tax_rates,code'],
            'name' => ['required', 'string', 'max:100'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'is_active' => ['nullable'],
        ], ['code.regex' => 'Kode pajak memakai huruf kapital, angka, dan garis bawah, contoh PPN_12.']);

        $tax = TaxRate::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'rate' => round((float) $validated['rate_percent'] / 100, 4),
            'country' => strtoupper($validated['country']),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit('billing.tax_created', "Membuat tarif pajak {$tax->code} ({$validated['rate_percent']}%).", $tax);

        return back()->with('success', "Tarif pajak {$tax->name} dibuat.");
    }

    public function updateTaxRate(Request $request, int $id)
    {
        $tax = TaxRate::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable'],
        ]);

        $tax->update([
            'name' => $validated['name'],
            'rate' => round((float) $validated['rate_percent'] / 100, 4),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit('billing.tax_updated', "Memperbarui tarif pajak {$tax->code} menjadi {$validated['rate_percent']}%.", $tax);

        return back()->with('success', "Tarif pajak {$tax->name} diperbarui.");
    }

    // ===================================================================
    // Saldo pelanggan
    // ===================================================================

    public function adjustCredit(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'direction' => ['required', Rule::in(['add', 'subtract'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $customer = User::findOrFail($validated['customer_id']);

        if ($customer->is_admin) {
            return back()->with('error', 'Saldo hanya untuk akun pelanggan.');
        }

        $signed = $validated['direction'] === 'add' ? (float) $validated['amount'] : -1 * (float) $validated['amount'];

        try {
            $tx = $this->billing->adjustCredit($customer, $signed, $validated['reason'], Auth::id());
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->audit(
            'billing.credit_adjusted',
            ($signed > 0 ? 'Menambah' : 'Mengurangi') . ' saldo ' . $customer->email . ' sebesar Rp '
                . number_format(abs($signed), 0, ',', '.') . '. Saldo akhir Rp '
                . number_format((float) $tx->balance_after, 0, ',', '.') . '.',
            $tx
        );

        return back()->with('success', 'Saldo pelanggan diperbarui.');
    }

    // ===================================================================
    // Data per tab
    // ===================================================================

    protected function summary(): array
    {
        return [
            'paid_this_month' => (float) Invoice::where('status', 'paid')
                ->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'unpaid_count' => Invoice::whereIn('status', ['sent', 'pending'])->count(),
            'pending_refunds' => Refund::where('status', 'pending')->count(),
            'active_subscriptions' => Subscription::whereIn('status', ['active', 'past_due', 'grace_period'])->count(),
        ];
    }

    protected function invoicesData(Request $request): array
    {
        $query = Invoice::with(['order.customer', 'order.vpsSpec'])->latest('issued_at')->latest('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('order.customer', function ($q) use ($search) {
                        $q->where('email', 'like', "%{$search}%")->orWhere('full_name', 'like', "%{$search}%");
                    });
            });
        }

        return [
            'invoices' => $query->paginate(20)->withQueryString(),
            'invoiceStatuses' => Invoice::query()->distinct()->orderBy('status')->pluck('status'),
            'filters' => $request->only(['status', 'q']),
        ];
    }

    protected function subscriptionsData(Request $request): array
    {
        $query = Subscription::with(['customer', 'vpsSpec', 'vpsInstance'])->withCount('invoices')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return [
            'subscriptions' => $query->paginate(20)->withQueryString(),
            'subscriptionStatuses' => Subscription::query()->distinct()->orderBy('status')->pluck('status'),
            'filters' => $request->only(['status']),
        ];
    }

    protected function refundsData(): array
    {
        return [
            'refunds' => Refund::with(['invoice', 'customer', 'actor'])->latest()->paginate(20),
            'refundableInvoices' => $this->refundableInvoices(),
        ];
    }

    protected function creditsData(Request $request): array
    {
        $balances = CreditTransaction::query()
            ->select('customer_id', DB::raw('SUM(amount) as balance'))
            ->groupBy('customer_id')
            ->havingRaw('SUM(amount) <> 0')
            ->orderByRaw('SUM(amount) DESC')
            ->with('customer')
            ->limit(50)
            ->get();

        return [
            'balances' => $balances,
            'creditTransactions' => CreditTransaction::with(['customer', 'invoice'])->latest('id')->paginate(25)->withQueryString(),
            'customers' => User::where('is_admin', false)->orderBy('full_name')->limit(500)->get(['id', 'full_name', 'email']),
        ];
    }

    /**
     * Faktur lunas yang masih punya sisa untuk dikembalikan.
     * Jumlah pengembalian diambil dalam satu kueri, bukan satu kueri per faktur.
     */
    protected function refundableInvoices()
    {
        $invoices = Invoice::with('order.customer')
            ->where('status', 'paid')
            ->latest('paid_at')
            ->limit(100)
            ->get();

        $taken = Refund::whereIn('invoice_id', $invoices->pluck('id'))
            ->whereIn('status', ['pending', 'processed'])
            ->groupBy('invoice_id')
            ->selectRaw('invoice_id, SUM(amount) as total')
            ->pluck('total', 'invoice_id');

        return $invoices
            ->map(fn (Invoice $i) => [
                'invoice' => $i,
                'refundable' => max(0.0, round((float) $i->amount - (float) ($taken[$i->id] ?? 0), 2)),
            ])
            ->filter(fn ($row) => $row['refundable'] > 0)
            ->values();
    }

    /**
     * Sisa nominal faktur yang masih dapat dikembalikan.
     */
    protected function refundableAmount(Invoice $invoice): float
    {
        $taken = (float) Refund::where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'processed'])
            ->sum('amount');

        return max(0.0, round((float) $invoice->amount - $taken, 2));
    }
}
