<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal maintenance terencana (Maintenance Tingkat 3).
 *
 * Status bergerak: scheduled -> in_progress -> completed.
 * Pembatalan dari scheduled maupun in_progress menuju cancelled.
 * Perpindahan dijalankan oleh command maintenance:sync.
 */
class MaintenanceWindow extends Model
{
    protected $fillable = [
        'title',
        'description',
        'scopes',
        'spec_ids',
        'region_ids',
        'starts_at',
        'ends_at',
        'status',
        'notice_days',
        'notify_customers',
        'customers_notified_at',
        'created_by',
    ];

    protected $casts = [
        'scopes' => 'array',
        'spec_ids' => 'array',
        'region_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'customers_notified_at' => 'datetime',
        'notify_customers' => 'boolean',
        'notice_days' => 'integer',
    ];

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Jendela yang sedang berlangsung dan menutup layanan saat ini.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Jendela terjadwal yang belum berjalan.
     *
     * Penyaringan H-notice_days dilakukan di PHP lewat isAnnounceable(),
     * bukan di SQL, agar tetap berjalan di MySQL maupun SQLite.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')
            ->where('starts_at', '>', now());
    }

    /**
     * Apakah jendela ini sudah waktunya diumumkan lewat spanduk.
     */
    public function isAnnounceable(): bool
    {
        return $this->status === 'scheduled'
            && $this->starts_at->isFuture()
            && now()->greaterThanOrEqualTo($this->starts_at->copy()->subDays($this->notice_days));
    }

    public function isRunning(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Apakah jendela ini menutup cakupan tertentu.
     */
    public function coversScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    /**
     * Jendela tanpa pembatas paket berlaku untuk semua paket.
     */
    public function coversSpec(?int $specId): bool
    {
        $limits = $this->spec_ids ?? [];
        if (empty($limits)) {
            return true;
        }

        return $specId !== null && in_array($specId, $limits, false);
    }

    /**
     * Jendela tanpa pembatas region berlaku untuk semua region.
     */
    public function coversRegion(?int $regionId): bool
    {
        $limits = $this->region_ids ?? [];
        if (empty($limits)) {
            return true;
        }

        return $regionId !== null && in_array($regionId, $limits, false);
    }

    public function getDurationMinutesAttribute(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'Terjadwal',
            'in_progress' => 'Sedang Berlangsung',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }
}
