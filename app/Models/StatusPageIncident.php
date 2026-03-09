<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatusPageIncident extends Model
{
    use HasFactory;

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_IDENTIFIED = 'identified';

    public const STATUS_MONITORING = 'monitoring';

    public const STATUS_RESOLVED = 'resolved';

    public const IMPACT_MINOR = 'minor';

    public const IMPACT_MAJOR = 'major';

    public const IMPACT_CRITICAL = 'critical';

    protected $fillable = [
        'status_page_id',
        'user_id',
        'title',
        'message',
        'status',
        'impact',
        'started_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function statusPage(): BelongsTo
    {
        return $this->belongsTo(StatusPage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'status_page_incident_monitor')->withTimestamps();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(StatusPageIncidentUpdate::class)->latest();
    }
}
