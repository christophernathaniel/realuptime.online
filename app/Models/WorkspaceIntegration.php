<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceIntegration extends Model
{
    use HasFactory;

    public const PROVIDER_WEBHOOK = 'webhook';

    public const PROVIDER_SLACK = 'slack';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'user_id',
        'provider',
        'name',
        'status',
        'config',
        'scopes',
        'last_tested_at',
        'last_error_at',
        'last_error_message',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'scopes' => 'array',
            'last_tested_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'integration_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function supportsEvent(string $event): bool
    {
        $scopes = collect($this->scopes ?? [])
            ->filter(fn (mixed $scope) => is_string($scope) && $scope !== '')
            ->values();

        return $scopes->isEmpty() || $scopes->contains($event);
    }
}
