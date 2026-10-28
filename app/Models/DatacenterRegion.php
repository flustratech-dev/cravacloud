<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatacenterRegion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'country', 'city', 'provider', 'provider_region_id',
        'capacity', 'capacity_used', 'is_active', 'metadata',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'capacity_used' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function vpsInstances()
    {
        return $this->hasMany(VpsInstance::class);
    }

    public function hasCapacity(int $needed = 1): bool
    {
        if (!$this->capacity) return true; // unlimited
        return ($this->capacity - $this->capacity_used) >= $needed;
    }

    public function availableSlots(): ?int
    {
        return $this->capacity ? $this->capacity - $this->capacity_used : null;
    }
}
