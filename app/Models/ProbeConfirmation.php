<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProbeConfirmation extends Model
{
    use HasFactory;

    public const KIND_RECOVERY = 'recovery';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'monitor_id',
        'incident_id',
        'kind',
        'status',
        'primary_region',
        'confirmation_regions',
        'primary_check_result_id',
        'results',
        'requested_at',
        'completed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'confirmation_regions' => 'array',
            'results' => 'array',
            'meta' => 'array',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function primaryCheckResult(): BelongsTo
    {
        return $this->belongsTo(CheckResult::class, 'primary_check_result_id');
    }
}
