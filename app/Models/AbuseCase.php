<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan kasus pelanggaran Acceptable Use Policy.
 *
 * Dipakai sebagai rujukan saat pelanggan menyanggah, dan sebagai dasar
 * penjelasan bila Supplier meminta keterangan atas kejadian di akun CravaCloud.
 */
class AbuseCase extends Model
{
    protected $fillable = [
        'vps_instance_id',
        'user_id',
        'type',
        'evidence',
        'severity',
        'status',
        'notified_at',
        'resolved_at',
        'resolution',
        'handled_by',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const STATUSES = ['open', 'investigating', 'notified', 'resolved', 'terminated'];
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * Jenis pelanggaran mengikuti daftar larangan di AUP.
     * Empat terakhir adalah larangan Supplier yang wajib ikut ditegakkan.
     */
    public static function types(): array
    {
        return [
            'ddos' => 'Serangan Siber & DDoS',
            'spam_phishing' => 'Spamming & Email Phishing',
            'crypto_mining' => 'Penambangan Kripto',
            'illegal_content' => 'Konten Ilegal & Judi Online',
            'port_scanning' => 'Pemindaian Port Agresif',
            'vpn_proxy' => 'Layanan VPN & Proxy',
            'scraping' => 'Scraping & Crawling',
            'torrent' => 'Agregator Torrent',
            'automated_bots' => 'Bot & Skrip Otomatis',
            'other' => 'Lainnya',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::types()[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function getSeverityLabelAttribute(): string
    {
        return match ($this->severity) {
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'critical' => 'Kritis',
            default => ucfirst($this->severity),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Baru',
            'investigating' => 'Sedang Diperiksa',
            'notified' => 'Sudah Diberitahu',
            'resolved' => 'Selesai',
            'terminated' => 'Diterminasi',
            default => ucfirst($this->status),
        };
    }

    public function vpsInstance()
    {
        return $this->belongsTo(VpsInstance::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereIn('status', ['open', 'investigating', 'notified']);
    }
}
