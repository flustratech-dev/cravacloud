<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'phone',
        'company',
        'address',
        'country',
        'channel',
        'shopee_order_id',
        'google_id',
        'avatar',
        // Security columns (sensitive) — tidak di-mass-assign lewat form.
        // 'is_admin', 'two_factor_secret', 'two_factor_confirmed_at',
        // 'mfa_required', 'current_organization_id' -> use forceFill().
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_admin' => 'boolean',
        'mfa_required' => 'boolean',
    ];

    // ============================================================
    // Existing relations
    // ============================================================
    public function vpsInstances()
    {
        return $this->hasMany(VpsInstance::class, 'customer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'customer_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'customer_id');
    }

    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class, 'customer_id');
    }

    public function creditBalance(): float
    {
        return CreditTransaction::balanceFor($this->id);
    }

    public function getCreditBalanceAttribute(): float
    {
        return $this->creditBalance();
    }

    // ============================================================
    // Organizations (Poin 6)
    // ============================================================
    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot(['role_id', 'joined_at'])
            ->withTimestamps();
    }

    public function organizationMemberships()
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function ownedOrganizations()
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function currentOrganization()
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * Bikin Personal Organization untuk user ini. Auto-attach sebagai owner.
     */
    public function createPersonalOrganization(): Organization
    {
        $org = Organization::create([
            'name' => $this->full_name . ' (Personal)',
            'type' => 'personal',
            'owner_id' => $this->id,
            'billing_email' => $this->email,
            'billing_name' => $this->full_name,
            'country' => $this->country ?? 'ID',
        ]);

        $ownerRole = Role::bySlug('owner');
        if ($ownerRole) {
            $org->attachMember($this, $ownerRole);
        } else {
            // Fallback: buat member tanpa role (akan diisi setelah seeder jalan).
            OrganizationMember::firstOrCreate(
                ['organization_id' => $org->id, 'user_id' => $this->id],
                ['role_id' => 0, 'joined_at' => now()]
            );
        }

        $org->quota()->create([
            'max_vps' => null,
            'max_members' => $this->is_admin ? null : 5,
            'max_api_keys' => 10,
        ]);

        return $org;
    }

    /**
     * Switch current organization. Return true kalau berhasil.
     */
    public function switchToOrganization(Organization $org): bool
    {
        if (!$org->hasMember($this)) return false;
        $this->forceFill(['current_organization_id' => $org->id])->save();
        return true;
    }

    // ============================================================
    // RBAC (Poin 5)
    // ============================================================

    /**
     * Cek apakah user punya role dengan slug tertentu di currentOrganization.
     * is_admin (system admin) selalu return true.
     */
    public function hasRole(string $roleSlug): bool
    {
        if ($this->is_admin) return true;

        $org = $this->currentOrganization;
        if (!$org) return false;
        $role = $org->memberRole($this);
        return $role?->slug === $roleSlug;
    }

    /**
     * Cek permission granular. is_admin bypass semua.
     * Resolve dari role user di currentOrganization.
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->is_admin) return true;

        $org = $this->currentOrganization;
        if (!$org) return false;
        $role = $org->memberRole($this);
        if (!$role) return false;
        return $role->hasPermission($slug);
    }

    // ============================================================
    // Security (Poin 5)
    // ============================================================
    public function loginActivities()
    {
        return $this->hasMany(LoginActivity::class)->orderBy('created_at', 'desc');
    }

    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    public function sshKeys()
    {
        return $this->hasMany(SshKey::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification());
    }

    /**
     * Rute nomor tujuan untuk notifikasi WhatsApp.
     */
    public function routeNotificationForWhatsApp(): ?string
    {
        return $this->phone;
    }
}
