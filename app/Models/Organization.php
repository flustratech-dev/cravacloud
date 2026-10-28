<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Organization = workspace/team/company. Setiap user punya minimal 1 org
 * (Personal). Resource (VPS, order, invoice) di-scope ke organization.
 *
 * type: personal | company
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'type', 'owner_id',
        'billing_email', 'billing_name', 'billing_address', 'tax_id',
        'country', 'currency',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $org) {
            if (empty($org->slug)) {
                $org->slug = static::generateUniqueSlug($org->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return Str::limit($slug, 60, '');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_members')
            ->withPivot(['role_id', 'joined_at'])
            ->withTimestamps();
    }

    public function invitations()
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function quota()
    {
        return $this->hasOne(OrganizationQuota::class);
    }

    public function orders() { return $this->hasMany(Order::class); }
    public function vpsInstances() { return $this->hasMany(VpsInstance::class); }
    public function subscriptions() { return $this->hasMany(Subscription::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }

    /** Cek apakah user tertentu punya role di org ini. */
    public function memberRole(User $user): ?Role
    {
        $member = $this->members()->where('user_id', $user->id)->with('role')->first();
        return $member?->role;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /** Attach user ke org dengan role. Idempotent. */
    public function attachMember(User $user, Role $role, ?User $invitedBy = null): OrganizationMember
    {
        return OrganizationMember::firstOrCreate(
            ['organization_id' => $this->id, 'user_id' => $user->id],
            ['role_id' => $role->id, 'joined_at' => now(), 'invited_by' => $invitedBy?->id]
        );
    }
}
