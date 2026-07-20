<?php

namespace App\Services\Security;

class MonitorSecretMasker
{
    /**
     * @param  array<string, string>|null  $headers
     * @return array<string, string>|null
     */
    public function maskHeaders(?array $headers): ?array
    {
        if ($headers === null) {
            return null;
        }

        foreach ($headers as $name => $value) {
            if ($this->isSensitiveHeader($name)) {
                $headers[$name] = '';
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, string>|null  $existingHeaders
     * @return array<string, mixed>
     */
    public function restoreHeaders(array $headers, ?array $existingHeaders): array
    {
        if ($existingHeaders === null) {
            return $headers;
        }

        foreach ($headers as $name => $value) {
            if (! $this->isSensitiveHeader((string) $name) || trim((string) $value) !== '') {
                continue;
            }

            foreach ($existingHeaders as $existingName => $existingValue) {
                if (strcasecmp((string) $name, (string) $existingName) === 0) {
                    $headers[$name] = $existingValue;

                    break;
                }
            }
        }

        return $headers;
    }

    /**
     * @param  array<int, string>|null  $urls
     * @return array<int, string>
     */
    public function maskWebhookUrls(?array $urls): array
    {
        return collect($urls ?? [])
            ->values()
            ->map(fn (string $url, int $index): string => $this->webhookPlaceholder($url, $index))
            ->all();
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<int, string>  $existingUrls
     * @return array<int, string>
     */
    public function restoreWebhookUrls(array $values, array $existingUrls): array
    {
        $placeholders = collect($existingUrls)
            ->values()
            ->mapWithKeys(fn (string $url, int $index): array => [
                $this->webhookPlaceholder($url, $index) => $url,
            ]);

        return collect($values)
            ->map(fn (string $value): string => (string) ($placeholders->get($value) ?? $value))
            ->all();
    }

    public function isSensitiveHeader(string $name): bool
    {
        $name = strtolower($name);

        if (in_array($name, ['authorization', 'cookie', 'proxy-authorization', 'x-api-key'], true)) {
            return true;
        }

        foreach (['api-key', 'apikey', 'secret', 'token'] as $marker) {
            if (str_contains($name, $marker)) {
                return true;
            }
        }

        return false;
    }

    protected function webhookPlaceholder(string $url, int $index): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return sprintf('[stored webhook %d at %s]', $index + 1, $host !== '' ? $host : 'configured host');
    }
}
