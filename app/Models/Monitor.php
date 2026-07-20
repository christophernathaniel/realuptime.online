<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Monitor extends Model
{
    use HasFactory;

    public const TYPE_HTTP = 'http';

    public const TYPE_PING = 'ping';

    public const TYPE_PORT = 'port';

    public const TYPE_KEYWORD = 'keyword';

    public const TYPE_SSL = 'ssl';

    public const TYPE_HEARTBEAT = 'heartbeat';

    public const TYPE_SYNTHETIC = 'synthetic';

    public const STATUS_UP = 'up';

    public const STATUS_DOWN = 'down';

    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'status',
        'target',
        'request_method',
        'interval_seconds',
        'admin_interval_override',
        'timeout_seconds',
        'retry_limit',
        'follow_redirects',
        'custom_headers',
        'auth_username',
        'auth_password',
        'expected_status_code',
        'accepted_http_statuses',
        'expected_keyword',
        'keyword_match_type',
        'packet_count',
        'synthetic_steps',
        'latency_threshold_ms',
        'degraded_consecutive_checks',
        'critical_alert_after_minutes',
        'downtime_webhook_urls',
        'ssl_threshold_days',
        'domain_threshold_days',
        'heartbeat_grace_seconds',
        'region',
        'last_probe_region',
        'heartbeat_token',
        'last_checked_at',
        'last_result_stored_at',
        'next_check_at',
        'last_dispatched_at',
        'check_claimed_at',
        'check_claim_token',
        'last_status_changed_at',
        'last_heartbeat_at',
        'last_response_time_ms',
        'last_queue_lag_ms',
        'last_http_status',
        'last_error_type',
        'last_error_message',
        'ssl_expires_at',
        'domain_expires_at',
        'domain_registrar',
        'domain_checked_at',
        'ssl_issuer',
        'ssl_checked_at',
    ];

    protected $hidden = [
        'auth_password',
        'check_claim_token',
        'custom_headers',
        'downtime_webhook_urls',
        'heartbeat_token',
        'synthetic_steps',
    ];

    protected function casts(): array
    {
        return [
            'follow_redirects' => 'boolean',
            'admin_interval_override' => 'boolean',
            'custom_headers' => 'encrypted:array',
            'synthetic_steps' => 'encrypted:array',
            'downtime_webhook_urls' => 'encrypted:array',
            'last_checked_at' => 'datetime',
            'last_result_stored_at' => 'datetime',
            'next_check_at' => 'datetime',
            'last_dispatched_at' => 'datetime',
            'check_claimed_at' => 'datetime',
            'last_status_changed_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
            'domain_expires_at' => 'datetime',
            'domain_checked_at' => 'datetime',
            'ssl_checked_at' => 'datetime',
            'auth_password' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Monitor $monitor): void {
            $monitor->interval_seconds = max(
                (int) config('realuptime.dispatch.minimum_interval_seconds', 60),
                (int) $monitor->interval_seconds,
            );
        });

        static::creating(function (Monitor $monitor): void {
            if (empty($monitor->public_id)) {
                $monitor->public_id = (string) Str::ulid();
            }

            if ($monitor->type === self::TYPE_HEARTBEAT && empty($monitor->heartbeat_token)) {
                $monitor->heartbeat_token = (string) Str::ulid();
            }

            if ($monitor->status !== self::STATUS_PAUSED && $monitor->next_check_at === null) {
                $monitor->next_check_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificationContacts(): BelongsToMany
    {
        return $this->belongsToMany(NotificationContact::class)->withTimestamps();
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(CheckResult::class);
    }

    public function checkResultRollups(): HasMany
    {
        return $this->hasMany(CheckResultRollup::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function activeIncident(): HasOne
    {
        return $this->hasOne(Incident::class)
            ->where('type', Incident::TYPE_DOWNTIME)
            ->whereNull('resolved_at')
            ->latestOfMany('started_at');
    }

    public function openIncidents(): HasMany
    {
        return $this->hasMany(Incident::class)->whereNull('resolved_at');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function heartbeatEvents(): HasMany
    {
        return $this->hasMany(HeartbeatEvent::class);
    }

    public function probeConfirmations(): HasMany
    {
        return $this->hasMany(ProbeConfirmation::class);
    }

    public function statusPages(): BelongsToMany
    {
        return $this->belongsToMany(StatusPage::class, 'status_page_monitor')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function maintenanceWindows(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceWindow::class)->withTimestamps();
    }

    public function capabilities(): BelongsToMany
    {
        return $this->belongsToMany(Capability::class)->withTimestamps();
    }

    public function statusPageIncidents(): BelongsToMany
    {
        return $this->belongsToMany(StatusPageIncident::class, 'status_page_incident_monitor')->withTimestamps();
    }
}
