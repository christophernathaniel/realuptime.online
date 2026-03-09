<?php

namespace App\Services\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DomainMetadataResolver
{
    /**
     * @return array{domain: string, expires_at: CarbonImmutable|null, registrar: string|null}|null
     */
    public function resolve(?string $host, int $timeoutSeconds = 10): ?array
    {
        $domain = $this->registrableDomain($host);

        if (! $domain) {
            return null;
        }

        $url = $this->providerUrl($domain, $timeoutSeconds);

        if (! $url) {
            return null;
        }

        try {
            $payload = Http::acceptJson()
                ->timeout($timeoutSeconds)
                ->get($url)
                ->throw()
                ->json();
        } catch (Throwable) {
            return null;
        }

        return [
            'domain' => $domain,
            'expires_at' => $this->expirationDate(is_array($payload) ? $payload : []),
            'registrar' => $this->registrarName(is_array($payload) ? $payload : []),
        ];
    }

    protected function providerUrl(string $domain, int $timeoutSeconds): ?string
    {
        $tld = Str::afterLast(Str::lower($domain), '.');

        if ($tld === '') {
            return null;
        }

        $urls = $this->bootstrapUrls($tld, $timeoutSeconds);

        if ($urls === []) {
            return null;
        }

        return rtrim($urls[0], '/').'/domain/'.rawurlencode(Str::lower($domain));
    }

    /**
     * @return array<int, string>
     */
    protected function bootstrapUrls(string $tld, int $timeoutSeconds): array
    {
        try {
            $services = Cache::remember('monitoring.rdap.bootstrap.v1', now()->addDay(), function () use ($timeoutSeconds): array {
                $payload = Http::acceptJson()
                    ->timeout($timeoutSeconds)
                    ->get('https://data.iana.org/rdap/dns.json')
                    ->throw()
                    ->json('services', []);

                return is_array($payload) ? $payload : [];
            });
        } catch (Throwable) {
            return [];
        }

        foreach ($services as $service) {
            $tlds = array_map('strtolower', Arr::get($service, '0', []));

            if (in_array(Str::lower($tld), $tlds, true)) {
                return Arr::get($service, '1', []);
            }
        }

        return [];
    }

    protected function registrableDomain(?string $host): ?string
    {
        $host = Str::of((string) $host)->trim()->trim('.')->lower()->value();

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $labels = array_values(array_filter(explode('.', $host)));

        if (count($labels) < 2) {
            return null;
        }

        if (count($labels) === 2) {
            return implode('.', $labels);
        }

        $tld = $labels[count($labels) - 1];
        $secondLevel = $labels[count($labels) - 2];
        $compoundPrefixes = ['ac', 'co', 'com', 'edu', 'gov', 'id', 'net', 'org'];

        if (strlen($tld) === 2 && in_array($secondLevel, $compoundPrefixes, true)) {
            return implode('.', array_slice($labels, -3));
        }

        return implode('.', array_slice($labels, -2));
    }

    protected function expirationDate(array $payload): ?CarbonImmutable
    {
        $event = collect(Arr::get($payload, 'events', []))->first(function ($event): bool {
            $action = Str::lower((string) Arr::get($event, 'eventAction'));

            return in_array($action, ['expiration', 'expiration date', 'expiry'], true);
        });

        $eventDate = Arr::get($event, 'eventDate');

        return $eventDate ? CarbonImmutable::parse($eventDate) : null;
    }

    protected function registrarName(array $payload): ?string
    {
        $entity = collect(Arr::get($payload, 'entities', []))->first(function ($entity): bool {
            return in_array('registrar', Arr::get($entity, 'roles', []), true);
        });

        foreach (Arr::get($entity, 'vcardArray.1', []) as $entry) {
            $field = Arr::get($entry, '0');
            $value = Arr::get($entry, '3');

            if (in_array($field, ['fn', 'org'], true) && is_string($value) && $value !== '') {
                return $value;
            }
        }

        $handle = Arr::get($entity, 'handle');

        return is_string($handle) && $handle !== '' ? $handle : null;
    }
}
