<?php

namespace App\Services\Security;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OutboundHttpClient
{
    public function __construct(protected PublicNetworkGuard $network) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $options
     * @param  array{0: string, 1: string}|null  $basicAuth
     */
    public function send(
        string $method,
        string $url,
        int $timeoutSeconds = 10,
        array $headers = [],
        array $options = [],
        bool $followRedirects = false,
        ?array $basicAuth = null,
    ): Response {
        $this->assertSafeHeaders($headers);

        if (! defined('CURLOPT_RESOLVE')) {
            throw new RuntimeException('Secure outbound requests require the PHP cURL extension.');
        }

        $method = strtoupper($method);
        $currentUrl = $url;
        $currentOptions = $options;
        $redirects = 0;
        $maxRedirects = max(0, (int) config('realuptime.security.max_outbound_redirects', 5));
        $maxResponseBytes = max(1024, (int) config('realuptime.security.max_outbound_response_bytes', 2 * 1024 * 1024));
        $initialOrigin = null;
        $safeRedirectHeaders = ['accept', 'accept-encoding', 'content-type', 'user-agent'];
        $containsSensitiveHeaders = $basicAuth !== null
            || isset($options['cookies'])
            || collect(array_keys($headers))
                ->map(fn ($name) => strtolower((string) $name))
                ->diff($safeRedirectHeaders)
                ->isNotEmpty();

        while (true) {
            $resolved = $this->network->resolveHttpUrl($currentUrl);
            $initialOrigin ??= $resolved->origin();

            if ($containsSensitiveHeaders && $resolved->origin() !== $initialOrigin) {
                throw new RuntimeException('Refusing to forward credentials or custom headers to a different origin.');
            }

            $request = Http::timeout(max(1, $timeoutSeconds))
                ->withHeaders($headers)
                ->withOptions([
                    'allow_redirects' => false,
                    'proxy' => null,
                    'curl' => [
                        CURLOPT_RESOLVE => [$resolved->curlResolveEntry()],
                    ],
                    'progress' => static function ($downloadTotal, $downloaded) use ($maxResponseBytes): void {
                        if ($downloaded > $maxResponseBytes || $downloadTotal > $maxResponseBytes) {
                            throw new RuntimeException('The outbound response exceeded the configured size limit.');
                        }
                    },
                ]);

            if ($basicAuth !== null) {
                $request = $request->withBasicAuth($basicAuth[0], $basicAuth[1]);
            }

            try {
                $response = $request->send($method, $currentUrl, $currentOptions);
            } catch (ConnectionException) {
                throw new RuntimeException('The outbound HTTP connection failed before a response was received.');
            }

            if (strlen($response->body()) > $maxResponseBytes) {
                throw new RuntimeException('The outbound response exceeded the configured size limit.');
            }

            if (! $followRedirects || ! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return $response;
            }

            $location = trim((string) $response->header('Location'));

            if ($location === '') {
                return $response;
            }

            if ($redirects >= $maxRedirects) {
                throw new RuntimeException('The outbound request exceeded the configured redirect limit.');
            }

            $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
            $redirects++;

            if ($response->status() === 303 || (in_array($response->status(), [301, 302], true) && $method === 'POST')) {
                $method = 'GET';
                $currentOptions = array_diff_key($currentOptions, array_flip([
                    'body',
                    'form_params',
                    'json',
                    'multipart',
                ]));
            }
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function assertSafeHeaders(array $headers): void
    {
        $blocked = array_map('strtolower', config('realuptime.security.blocked_outbound_headers', []));

        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower((string) $name), $blocked, true)) {
                throw new RuntimeException(sprintf('The outbound header "%s" is not allowed.', $name));
            }
        }
    }
}
