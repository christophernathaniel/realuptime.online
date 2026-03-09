<?php

return [
    'plans' => [
        'free' => [
            'label' => 'Free',
            'monthly_price_pence' => 0,
            'monitor_limit' => 10,
            'minimum_interval_seconds' => 300,
            'advanced_workspace_features' => false,
            'downtime_webhooks' => false,
            'stripe_price_id' => null,
        ],
        'premium' => [
            'label' => 'Premium',
            'monthly_price_pence' => 599,
            'monitor_limit' => 50,
            'minimum_interval_seconds' => 30,
            'advanced_workspace_features' => true,
            'downtime_webhooks' => true,
            'stripe_price_id' => env('STRIPE_PREMIUM_PRICE_ID'),
        ],
        'ultra' => [
            'label' => 'Ultra',
            'monthly_price_pence' => 1599,
            'monitor_limit' => 200,
            'minimum_interval_seconds' => 30,
            'advanced_workspace_features' => true,
            'downtime_webhooks' => true,
            'stripe_price_id' => env('STRIPE_ULTRA_PRICE_ID'),
        ],
    ],
];
