<?php

namespace App\Support;

class OAuthProviderCatalog
{
    /**
     * @return array<int, array{key: string, label: string, enabled: bool}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'google',
                'label' => 'Google',
                'enabled' => (bool) (config('services.google.client_id') && config('services.google.client_secret') && config('services.google.redirect')),
            ],
            [
                'key' => 'github',
                'label' => 'GitHub',
                'enabled' => (bool) (config('services.github.client_id') && config('services.github.client_secret') && config('services.github.redirect')),
            ],
        ];
    }

    public static function isSupported(string $provider): bool
    {
        return in_array($provider, array_column(self::all(), 'key'), true);
    }
}
