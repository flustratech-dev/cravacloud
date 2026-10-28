<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'email', 'outcome', 'ip_address', 'user_agent',
        'device_label', 'country', 'city', 'reason', 'session_id',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public static function record(array $attrs): self
    {
        return static::create($attrs);
    }
}
