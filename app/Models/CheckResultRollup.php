<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckResultRollup extends Model
{
    public const GRANULARITY_FIFTEEN_MINUTES = 900;

    public const GRANULARITY_DAY = 86400;

    protected $fillable = [
        'monitor_id',
        'granularity_seconds',
        'bucket_started_at',
        'bucket_ended_at',
        'total_checks',
        'up_checks',
        'down_checks',
        'slow_checks',
        'response_time_samples',
        'response_time_sum_ms',
        'response_time_min_ms',
        'response_time_max_ms',
        'first_checked_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'granularity_seconds' => 'integer',
            'bucket_started_at' => 'immutable_datetime',
            'bucket_ended_at' => 'immutable_datetime',
            'total_checks' => 'integer',
            'up_checks' => 'integer',
            'down_checks' => 'integer',
            'slow_checks' => 'integer',
            'response_time_samples' => 'integer',
            'response_time_sum_ms' => 'integer',
            'response_time_min_ms' => 'integer',
            'response_time_max_ms' => 'integer',
            'first_checked_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
