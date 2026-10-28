<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Role RBAC. Seeded: owner, admin, billing, operator, viewer.
 * priority tinggi = permission luas. Owner=100, admin=80, billing=60,
 * operator=40, viewer=20.
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'is_system', 'priority',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'priority' => 'integer',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function organizationMembers()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    /** Helper: cari role by slug (cache friendly). */
    public static function bySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }
}
