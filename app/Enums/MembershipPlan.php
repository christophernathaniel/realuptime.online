<?php

namespace App\Enums;

enum MembershipPlan: string
{
    case FREE = 'free';
    case PREMIUM = 'premium';
    case ULTRA = 'ultra';

    public function label(): string
    {
        return (string) config("membership.plans.{$this->value}.label", ucfirst($this->value));
    }

    public function monitorLimit(): int
    {
        return (int) config("membership.plans.{$this->value}.monitor_limit", 10);
    }

    public function minimumIntervalSeconds(): int
    {
        return (int) config("membership.plans.{$this->value}.minimum_interval_seconds", 300);
    }

    public function allowsAdvancedWorkspaceFeatures(): bool
    {
        return (bool) config("membership.plans.{$this->value}.advanced_workspace_features", false);
    }

    public function supportsDowntimeWebhooks(): bool
    {
        return (bool) config("membership.plans.{$this->value}.downtime_webhooks", false);
    }

    public function monthlyPricePence(): int
    {
        return (int) config("membership.plans.{$this->value}.monthly_price_pence", 0);
    }

    public function priceLabel(): string
    {
        $pricePence = $this->monthlyPricePence();

        if ($pricePence === 0) {
            return 'Free';
        }

        return sprintf('£%0.2f / month', $pricePence / 100);
    }

    public function stripePriceId(): ?string
    {
        return config("membership.plans.{$this->value}.stripe_price_id");
    }

    public static function fromStripePriceId(?string $priceId): ?self
    {
        if (! $priceId) {
            return null;
        }

        return collect(self::cases())->first(
            fn (self $plan) => $plan->stripePriceId() === $priceId
        );
    }

    /**
     * @return array<int, self>
     */
    public static function paidCases(): array
    {
        return [self::PREMIUM, self::ULTRA];
    }
}
