<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Komponen sistem yang statusnya tampil di halaman status publik.
 *
 * Status maintenance tidak disimpan manual: command maintenance:sync
 * menyetelnya otomatis saat jendela maintenance berjalan.
 */
class SystemComponent extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'status_note',
        'uptime_percent',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'uptime_percent' => 'float',
    ];

    public const STATUSES = ['operational', 'degraded', 'maintenance', 'outage'];

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'operational' => 'Beroperasi Normal',
            'degraded' => 'Performa Menurun',
            'maintenance' => 'Sedang Maintenance',
            'outage' => 'Gangguan',
            default => ucfirst($this->status),
        };
    }

    /**
     * Kelas warna mengikuti palet lencana yang sudah dipakai di panel.
     * Ditulis utuh karena Tailwind tidak memakai safelist.
     */
    public function getStatusClassesAttribute(): string
    {
        return match ($this->status) {
            'operational' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'degraded' => 'bg-amber-50 text-amber-800 border-amber-200',
            'maintenance' => 'bg-sky-50 text-sky-800 border-sky-200',
            'outage' => 'bg-red-50 text-red-800 border-red-200',
            default => 'bg-slate-50 text-slate-700 border-slate-200',
        };
    }

    public function getStatusDotClassAttribute(): string
    {
        return match ($this->status) {
            'operational' => 'bg-emerald-500',
            'degraded' => 'bg-amber-500',
            'maintenance' => 'bg-sky-500',
            'outage' => 'bg-red-500',
            default => 'bg-slate-400',
        };
    }
}
