<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;

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

        // Lynk adalah satu-satunya jalur pembayaran yang berjalan. Mematikannya
        // menutup checkout lynk, jadi hanya boleh bila masih ada gateway lain aktif.
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
}
