<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    /** Tiket bantuan biasa dari halaman Support. */
    public const TYPE_GENERAL = 'general';

    /** Permintaan install ulang OS, dikerjakan admin di dashboard supplier. */
    public const TYPE_REINSTALL = 'reinstall';

    /** Laporan server tidak bisa diakses, admin cek dan reboot dari supplier. */
    public const TYPE_UNREACHABLE = 'unreachable';

    /** Status tiket yang masih menunggu atau sedang dikerjakan admin. */
    public const OPEN_STATUSES = ['open', 'in_progress'];

    /**
     * Batas waktu pengerjaan permintaan layanan yang dijanjikan ke pelanggan,
     * dihitung dalam jam kerja (lihat setting support_hours).
     */
    public const SERVICE_REQUEST_SLA_WORKING_HOURS = 6;

    protected $fillable = [
        'customer_id',
        'organization_id',
        'assigned_to',
        'vps_instance_id',
        'subject',
        'type',
        'request_data',
        'status',
        'priority',
        'sla_due_at',
        'first_response_at',
        'last_customer_reply_at',
        'resolved_at',
    ];

    protected $attributes = [
        'type' => self::TYPE_GENERAL,
    ];

    protected $casts = [
        'request_data' => 'array',
        'sla_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_customer_reply_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public static function serviceRequestTypes(): array
    {
        return [self::TYPE_REINSTALL, self::TYPE_UNREACHABLE];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_GENERAL => 'Bantuan Umum',
            self::TYPE_REINSTALL => 'Reinstall OS',
            self::TYPE_UNREACHABLE => 'Server Tidak Bisa Diakses',
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? self::typeLabels()[self::TYPE_GENERAL];
    }

    public function isServiceRequest(): bool
    {
        return in_array($this->type, self::serviceRequestTypes(), true);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Permintaan yang benar-benar masih menunggu tim: terbuka dan belum pernah
     * diselesaikan (atau dibuka kembali oleh admin, yang mengosongkan resolved_at).
     *
     * Balasan pelanggan pada tiket yang sudah selesai juga membuka tiket lagi,
     * tetapi itu hanya percakapan lanjutan, bukan permintaan baru. Tanpa pembeda
     * ini, dashboard akan menampilkan reinstall "sedang diproses" padahal sudah selesai.
     */
    public function isAwaitingTeam(): bool
    {
        return $this->isOpen() && $this->resolved_at === null;
    }

    /** Tiket yang masih menunggu atau sedang dikerjakan. */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /** Lihat isAwaitingTeam(). */
    public function scopeAwaitingTeam($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES)->whereNull('resolved_at');
    }

    /** Hanya permintaan layanan server (reinstall, server tidak bisa diakses). */
    public function scopeServiceRequests($query)
    {
        return $query->whereIn('type', self::serviceRequestTypes());
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vpsInstance()
    {
        return $this->belongsTo(VpsInstance::class, 'vps_instance_id');
    }

    public function organization() { return $this->belongsTo(Organization::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->orderBy('created_at', 'asc');
    }

    public function customerMessages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->where('is_internal', false)->orderBy('created_at', 'asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(TicketMessage::class, 'ticket_id')->latestOfMany();
    }

    public function latestCustomerMessage()
    {
        return $this->hasOne(TicketMessage::class, 'ticket_id')->where('is_internal', false)->latestOfMany();
    }
}
