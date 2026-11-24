<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use App\Services\Payments\MidtransService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PHASE 4 - Registry payment gateway.
 *
 * Aturan kredensial:
 *   - Hanya field yang terdaftar di PaymentGateway::credentialFields() yang disimpan
 *   - Isian kosong berarti "biarkan nilai lama", agar nilai tersamar tidak terhapus
 *   - Penghapusan harus eksplisit lewat centang "hapus"
 *   - Nilai kredensial TIDAK PERNAH ditulis ke jejak audit, hanya nama field-nya
 */
class PaymentGatewayController extends Controller
{
    use LogsAdminAudit;

    public function index()
    {
        return view('admin.payment-gateways', [
            'gateways' => PaymentGateway::orderBy('sort_order')->get(),
            'activeMethods' => PaymentGateway::activeMethods(),
            'failedCount' => WebhookEvent::where('processing_status', 'failed')->count(),
            'processedToday' => WebhookEvent::where('processing_status', 'processed')
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $gateway = PaymentGateway::findOrFail($id);
        $fields = $gateway->fields();

        $rules = [
            'mode' => ['required', 'in:' . implode(',', PaymentGateway::MODES)],
            'is_active' => ['nullable'],
        ];
        foreach (array_keys($fields) as $field) {
            $rules['credentials.' . $field] = ['nullable', 'string', 'max:500'];
            $rules['clear.' . $field] = ['nullable'];
        }

        $request->validate($rules);

        $credentials = $gateway->decryptedCredentials();
        $changedFields = [];

        foreach (array_keys($fields) as $field) {
            if ($request->boolean('clear.' . $field)) {
                unset($credentials[$field]);
                $changedFields[] = $field . ' (dihapus)';
                continue;
            }

            $value = trim((string) $request->input('credentials.' . $field, ''));
            if ($value !== '') {
                $credentials[$field] = $value;
                $changedFields[] = $field;
            }
        }

        $wasActive = $gateway->is_active;
        $willBeActive = $request->boolean('is_active');

        // Midtrans adalah satu-satunya gateway pembayaran yang berjalan. Mematikannya
        // menutup seluruh checkout, jadi minimal satu gateway harus tetap aktif.
        if ($wasActive && !$willBeActive
            && PaymentGateway::active()->where('id', '!=', $gateway->id)->doesntExist()) {
            return back()->with('error', 'Minimal satu gateway harus tetap aktif agar pelanggan masih bisa membayar.');
        }

        $gateway->update([
            'mode' => $request->input('mode'),
            'is_active' => $willBeActive,
            'credentials' => $credentials ?: null,
        ]);

        $this->audit(
            'payment_gateway.updated',
            "Memperbarui gateway {$gateway->name}: status "
                . ($willBeActive ? 'aktif' : 'nonaktif') . ", mode {$gateway->mode_label}"
                . ($changedFields ? ', kredensial diubah: ' . implode(', ', $changedFields) : '') . '.',
            $gateway
        );

        return back()->with('success', "Gateway {$gateway->name} berhasil diperbarui.");
    }

    /**
     * Uji koneksi read-only ke API Midtrans menggunakan kredensial aktif.
     */
    public function testConnection(int $id)
    {
        $gateway = PaymentGateway::findOrFail($id);

        if ($gateway->code !== 'midtrans') {
            return back()->with('error', "Uji koneksi tidak tersedia untuk gateway {$gateway->name}.");
        }

        $result = MidtransService::testConnection();

        $this->audit(
            'payment_gateway.tested',
            "Menguji koneksi gateway {$gateway->name} ({$result['mode']}): "
                . ($result['ok'] ? 'BERHASIL' : 'GAGAL') . " — {$result['message']}",
            $gateway
        );

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Ekspor CSV transaksi Midtrans CravaCloud (settled) untuk rekonsiliasi bulanan
     * dengan akun Midtrans bersama (CRV-* vs PSV-*).
     */
    public function exportReconciliation(Request $request): StreamedResponse
    {
        $monthParam = trim((string) $request->query('month', now('Asia/Jakarta')->format('Y-m')));
        $start = null;
        $end = null;

        if ($monthParam !== 'all') {
            if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $monthParam)) {
                abort(422, 'Format bulan tidak valid (gunakan YYYY-MM atau all).');
            }

            $parsed = Carbon::createFromFormat('!Y-m', $monthParam, 'Asia/Jakarta');
            $start = $parsed->copy()->startOfMonth()->utc();
            $end = $parsed->copy()->endOfMonth()->utc();
        }

        $query = PaymentTransaction::with(['order.customer', 'order.invoice', 'invoice'])
            ->where('provider', 'midtrans')
            ->where('status', 'settled');

        if ($start && $end) {
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('settled_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('settled_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });
        }

        $transactions = $query->orderByRaw('COALESCE(settled_at, created_at) ASC')->get();

        $this->audit(
            'payment_gateway.reconciliation_exported',
            "Mengekspor CSV rekonsiliasi Midtrans periode {$monthParam} ({$transactions->count()} transaksi)."
        );

        $filename = 'rekonsiliasi-midtrans-cravacloud-' . $monthParam . '.csv';

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Waktu Settlement',
                'Order ID',
                'Referensi Midtrans (CRV-)',
                'Transaction ID Midtrans',
                'Nomor Invoice',
                'Pelanggan',
                'Email',
                'Metode Bayar',
                'Nominal (IDR)',
                'Status Order',
            ]);

            $totalAmount = 0.0;
            $count = 0;

            foreach ($transactions as $tx) {
                $settledTime = $tx->settled_at ?? $tx->created_at;
                $order = $tx->order;
                $customer = $order?->customer;
                $amount = (float) $tx->amount;
                $totalAmount += $amount;
                $count++;

                fputcsv($handle, [
                    $settledTime ? $settledTime->copy()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') . ' WIB' : '',
                    $tx->order_id ? '#' . $tx->order_id : '',
                    $this->safeCsvCell($tx->provider_order_ref),
                    $this->safeCsvCell($tx->provider_transaction_id),
                    $this->safeCsvCell($tx->invoice?->invoice_number ?? $order?->invoice?->invoice_number ?? ''),
                    $this->safeCsvCell($customer?->full_name ?: ($customer?->username ?: '')),
                    $this->safeCsvCell($customer?->email ?? ''),
                    $this->safeCsvCell($tx->payment_method ?? ''),
                    number_format($amount, 2, '.', ''),
                    $this->safeCsvCell($order?->status ?? ''),
                ]);
            }

            fputcsv($handle, [
                'TOTAL',
                $count . ' transaksi',
                '',
                '',
                '',
                '',
                '',
                '',
                number_format($totalAmount, 2, '.', ''),
                '',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function safeCsvCell(?string $value): string
    {
        $val = (string) ($value ?? '');
        if ($val !== '' && preg_match('/^[=+\-@\t\r]/', $val) === 1) {
            return "'" . $val;
        }

        return $val;
    }
}
