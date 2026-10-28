<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'rate', 'country', 'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /** Rate default: cari PPN aktif untuk country tertentu. */
    public static function defaultForCountry(string $country = 'ID'): ?self
    {
        return static::where('is_active', true)
            ->where('country', $country)
            ->orderBy('id', 'desc')
            ->first();
    }
}
