<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'status',
        'checked_at',
        'attempts',
        'response_time_ms',
        'http_status_code',
        'error_type',
        'error_message',
        'keyword_match',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'keyword_match' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
