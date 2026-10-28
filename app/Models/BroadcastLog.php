<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat broadcast darurat ke pelanggan.
 *
 * Menjadi bukti kapan pemberitahuan insiden dikirim dan ke berapa penerima.
 */
class BroadcastLog extends Model
{
    protected $fillable = [
        'template_code',
        'subject',
        'body',
        'audience',
        'recipient_count',
        'sent_count',
        'failed_count',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'recipient_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public static function audiences(): array
    {
        return [
            'all' => 'Seluruh Pelanggan',
            'active_customers' => 'Pelanggan dengan Layanan Aktif',
            'affected_instances' => 'Pelanggan dengan Instance Terdampak',
        ];
    }

    public function getAudienceLabelAttribute(): string
    {
        return self::audiences()[$this->audience] ?? ucfirst((string) $this->audience);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
