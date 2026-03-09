<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'incident_id',
        'notification_contact_id',
        'channel',
        'type',
        'subject',
        'status',
        'sent_at',
        'failure_message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function notificationContact(): BelongsTo
    {
        return $this->belongsTo(NotificationContact::class);
    }
}
