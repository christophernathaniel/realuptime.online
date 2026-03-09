<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use HasFactory;

    public const TYPE_DOWNTIME = 'downtime';

    public const TYPE_DEGRADED_PERFORMANCE = 'degraded_performance';

    public const TYPE_SSL_EXPIRY = 'ssl_expiry';

    public const TYPE_DOMAIN_EXPIRY = 'domain_expiry';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_MAJOR = 'major';

    public const SEVERITY_CRITICAL = 'critical';

    protected $fillable = [
        'monitor_id',
        'first_check_result_id',
        'last_good_check_result_id',
        'latest_check_result_id',
        'started_at',
        'resolved_at',
        'duration_seconds',
        'type',
        'severity',
        'reason',
        'error_type',
        'http_status_code',
        'meta',
        'operator_notes',
        'root_cause_summary',
        'critical_alert_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'critical_alert_sent_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function latestCheckResult(): BelongsTo
    {
        return $this->belongsTo(CheckResult::class, 'latest_check_result_id');
    }

    public function firstCheckResult(): BelongsTo
    {
        return $this->belongsTo(CheckResult::class, 'first_check_result_id');
    }

    public function lastGoodCheckResult(): BelongsTo
    {
        return $this->belongsTo(CheckResult::class, 'last_good_check_result_id');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
