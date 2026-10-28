<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Template surel yang dapat disunting admin tanpa deploy.
 *
 * Placeholder ditulis dengan kurung kurawal ganda, mis. {{nama}}.
 * Isi template di-escape saat dirender, sehingga aman dari penyisipan HTML.
 */
class NotificationTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'subject',
        'body',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    // Kode template yang dipakai sistem. Jangan diubah tanpa menyesuaikan pemanggilnya.
    public const ABUSE_SUSPENSION = 'abuse_suspension';
    public const INFRA_INCIDENT = 'infra_incident';
    public const INFRA_RECOVERED = 'infra_recovered';
    public const MAINTENANCE_SCHEDULED = 'maintenance_scheduled';
    public const RENEWAL_REMINDER = 'renewal_reminder';
    public const FULFILLMENT_HANDOVER = 'fulfillment_handover';

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Ganti placeholder {{key}} dengan nilai dari $data.
     * Placeholder yang tidak dikenal dibiarkan apa adanya agar kesalahan terlihat.
     */
    public function render(string $field, array $data = []): string
    {
        $text = (string) ($this->{$field} ?? '');

        foreach ($data as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }

        return $text;
    }

    public function renderSubject(array $data = []): string
    {
        return $this->render('subject', $data);
    }

    public function renderBody(array $data = []): string
    {
        return $this->render('body', $data);
    }
}
