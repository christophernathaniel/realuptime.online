<?php

namespace App\Services\Security;

use InvalidArgumentException;

class PublicNetworkGuard
{
    public function __construct(protected HostResolver $resolver) {}

    public function validateHttpUrl(string $url): void
    {
        $this->parseHttpUrl($url);
    }

    public function resolveHttpUrl(string $url): ResolvedNetworkTarget
    {
        $parts = $this->parseHttpUrl($url);
        $address = $this->resolveHost($parts['host']);

        return new ResolvedNetworkTarget(
            url: $url,
            scheme: $parts['scheme'],
            host: $parts['host'],
            port: $parts['port'],
            address: $address,
        );
    }

    public function validateHost(string $host): void
    {
        $this->normalizeAndValidateHost($host);
    }

    public function resolveHost(string $host): string
    {
        $host = $this->normalizeAndValidateHost($host);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new InvalidArgumentException('The destination hostname could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicIp($address)) {
                throw new InvalidArgumentException('The destination resolves to a private or reserved network address.');
            }
        }

        usort($addresses, fn (string $left, string $right): int => $this->addressPreference($left) <=> $this->addressPreference($right));

        return $addresses[0];
    }

    public function validateSlackWebhookUrl(string $url): void
    {
        $parts = $this->parseHttpUrl($url);
        $allowedHosts = array_map('strtolower', config('realuptime.security.slack_webhook_hosts', []));

        if ($parts['scheme'] !== 'https' || ! in_array($parts['host'], $allowedHosts, true)) {
            throw new InvalidArgumentException('Slack integrations require an official HTTPS Slack webhook URL.');
        }
    }

    /**
     * @return array{scheme: string, host: string, port: int}
     */
    protected function parseHttpUrl(string $url): array
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new InvalidArgumentException('Enter a valid public HTTP or HTTPS URL.');
        }

        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);
        $parts = $validatedUrl ? parse_url($url) : false;

        if (! is_array($parts)) {
            throw new InvalidArgumentException('Enter a valid public HTTP or HTTPS URL.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('Only public HTTP and HTTPS destinations are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Credentials must not be embedded in a destination URL.');
        }

        $host = $this->normalizeAndValidateHost($host);
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('The destination URL contains an invalid port.');
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
        ];
    }

    protected function normalizeAndValidateHost(string $host): string
    {
        $host = strtolower(trim($host));

        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        $host = rtrim($host, '.');

        if ($host === '' || str_contains($host, '%')) {
            throw new InvalidArgumentException('Enter a valid public hostname or IP address.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! $this->isPublicIp($host)) {
                throw new InvalidArgumentException('Private and reserved network addresses are not allowed.');
            }

            return $host;
        }

        if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('Enter a valid public hostname or IP address.');
        }

        foreach (['localhost', '.localhost', '.local', '.internal', '.home.arpa'] as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                throw new InvalidArgumentException('Local and internal hostnames are not allowed.');
            }
        }

        return $host;
    }

    protected function isPublicIp(string $address): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        $nonPublicRanges = [
            '0.0.0.0/8',
            '10.0.0.0/8',
            '100.64.0.0/10',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '172.16.0.0/12',
            '192.0.0.0/24',
            '192.0.2.0/24',
            '192.88.99.0/24',
            '192.168.0.0/16',
            '198.18.0.0/15',
            '198.51.100.0/24',
            '203.0.113.0/24',
            '224.0.0.0/4',
            '240.0.0.0/4',
            '::/128',
            '::1/128',
            '::ffff:0:0/96',
            '64:ff9b::/96',
            '64:ff9b:1::/48',
            '100::/64',
            '2001::/23',
            '2001:db8::/32',
            '2002::/16',
            'fc00::/7',
            'fe80::/10',
            'ff00::/8',
        ];

        foreach ($nonPublicRanges as $range) {
            if ($this->addressIsInRange($address, $range)) {
                return false;
            }
        }

        return true;
    }

    protected function addressIsInRange(string $address, string $range): bool
    {
        [$network, $prefixLength] = explode('/', $range, 2);
        $addressBytes = inet_pton($address);
        $networkBytes = inet_pton($network);

        if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
            return false;
        }

        $prefixLength = (int) $prefixLength;
        $wholeBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if (substr($addressBytes, 0, $wholeBytes) !== substr($networkBytes, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($addressBytes[$wholeBytes]) & $mask) === (ord($networkBytes[$wholeBytes]) & $mask);
    }

    protected function addressPreference(string $address): int
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 0 : 1;
    }
}
