<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'max_vps', 'max_members', 'max_api_keys', 'per_datacenter',
    ];

    protected $casts = [
        'per_datacenter' => 'array',
        'max_vps' => 'integer',
        'max_members' => 'integer',
        'max_api_keys' => 'integer',
    ];

    public function organization() { return $this->belongsTo(Organization::class); }

    public function canAddVps(): bool
    {
        if ($this->max_vps === null) return true;
        return $this->organization->vpsInstances()
            ->whereNotIn('status', ['terminated'])
            ->count() < $this->max_vps;
    }

    public function canAddMember(): bool
    {
        if ($this->max_members === null) return true;
        return $this->organization->members()->count() < $this->max_members;
    }
}
