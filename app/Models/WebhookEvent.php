<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotency store untuk webhook payment gateway.
 * Unique (provider, event_id) memastikan webhook duplikat tidak diproses 2x.
 *
 * processing_status: received | processed | failed | ignored
 */
class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'signature',
        'signature_verified',
        'ip_address',
        'payload',
        'processing_status',
        'processing_error',
        'http_status',
        'attempts',
        'processed_at',
        'last_replayed_at',
        'replayed_by',
        'order_id',
        'payment_transaction_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'last_replayed_at' => 'datetime',
        'signature_verified' => 'boolean',
        'attempts' => 'integer',
        'http_status' => 'integer',
    ];

    public const STATUSES = ['received', 'processed', 'failed', 'ignored'];

    /**
     * Hanya event bertanda tangan sah yang boleh diproses ulang. Memproses ulang
     * payload yang tidak terverifikasi sama dengan melewati pengecekan signature.
     */
    public function isReplayable(): bool
    {
        return $this->provider === 'lynk'
            && $this->signature_verified
            && in_array($this->processing_status, ['received', 'failed'], true);
    }

    public function replayer()
    {
        return $this->belongsTo(User::class, 'replayed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->processing_status) {
            'received' => 'Diterima',
            'processed' => 'Berhasil',
            'failed' => 'Gagal',
            'ignored' => 'Diabaikan',
            default => ucfirst((string) $this->processing_status),
        };
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
