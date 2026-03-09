<?php

namespace App\Models;

use App\Enums\MembershipPlan;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use RuntimeException;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'public_status_key',
        'password',
        'password_login_enabled',
        'is_admin',
        'admin_plan_override',
        'admin_plan_assigned_by',
        'admin_plan_assigned_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_login_enabled' => 'boolean',
            'is_admin' => 'boolean',
            'admin_plan_assigned_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (blank($user->public_status_key)) {
                $user->public_status_key = static::generatePublicStatusKey();
            }
        });
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    public function notificationContacts(): HasMany
    {
        return $this->hasMany(NotificationContact::class);
    }

    public function statusPages(): HasMany
    {
        return $this->hasMany(StatusPage::class);
    }

    public function maintenanceWindows(): HasMany
    {
        return $this->hasMany(MaintenanceWindow::class);
    }

    public function connectedAccounts(): HasMany
    {
        return $this->hasMany(ConnectedAccount::class);
    }

    public function trackedSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function latestTrackedSession(): HasOne
    {
        return $this->hasOne(UserSession::class)
            ->whereNull('revoked_at')
            ->latestOfMany('last_active_at');
    }

    public function statusPageIncidents(): HasMany
    {
        return $this->hasMany(StatusPageIncident::class);
    }

    public function ownedWorkspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class, 'owner_user_id');
    }

    public function joinedWorkspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class, 'member_user_id');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'admin_plan_assigned_by');
    }

    public function adminPlanOverride(): ?MembershipPlan
    {
        return $this->admin_plan_override
            ? MembershipPlan::from($this->admin_plan_override)
            : null;
    }

    public function subscriptionPlan(): ?MembershipPlan
    {
        $subscription = $this->subscription('default');

        if (! $subscription || ! $subscription->valid()) {
            return null;
        }

        $priceId = $subscription->hasMultiplePrices()
            ? $subscription->items->first()?->stripe_price
            : $subscription->stripe_price;

        return MembershipPlan::fromStripePriceId($priceId);
    }

    public function membershipPlan(): MembershipPlan
    {
        return $this->adminPlanOverride()
            ?? $this->subscriptionPlan()
            ?? MembershipPlan::FREE;
    }

    public function membershipSource(): string
    {
        if ($this->adminPlanOverride() !== null) {
            return 'admin';
        }

        if ($this->subscriptionPlan() !== null) {
            return 'stripe';
        }

        return 'free';
    }

    public function allowsAdvancedWorkspaceFeatures(?self $actor = null): bool
    {
        return $this->membershipPlan()->allowsAdvancedWorkspaceFeatures();
    }

    public function supportsDowntimeWebhooks(?self $actor = null): bool
    {
        return $this->membershipPlan()->supportsDowntimeWebhooks();
    }

    public function monitorLimit(?self $actor = null): int
    {
        return $this->membershipPlan()->monitorLimit();
    }

    public function minimumMonitorIntervalSeconds(?self $actor = null): int
    {
        return $this->membershipPlan()->minimumIntervalSeconds();
    }

    public function hasReachedMonitorLimit(?self $actor = null): bool
    {
        return $this->monitors()->count() >= $this->monitorLimit();
    }

    protected static function generatePublicStatusKey(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $key = collect(range(1, 4))
                ->map(fn (): string => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT))
                ->implode('');

            if (! static::query()->where('public_status_key', $key)->exists()) {
                return $key;
            }
        }

        throw new RuntimeException('Unable to generate a unique public status key.');
    }
}
