<?php

namespace App\Services\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class TlsMetadataResolver
{
    /**
     * @return array{expires_at: CarbonImmutable, issuer: string|null}|null
     */
    public function resolve(string $host, int $timeoutSeconds = 10): ?array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $client = @stream_socket_client(
                sprintf('ssl://%s:443', $host),
                $errorNumber,
                $errorMessage,
                $timeoutSeconds,
                STREAM_CLIENT_CONNECT,
                $context,
            );

            if (! $client) {
                return null;
            }

            $params = stream_context_get_params($client);
            $certificate = Arr::get($params, 'options.ssl.peer_certificate');
            $parsed = $certificate ? openssl_x509_parse($certificate) : false;

            fclose($client);

            if (! is_array($parsed) || ! isset($parsed['validTo_time_t'])) {
                return null;
            }

            return [
                'expires_at' => CarbonImmutable::createFromTimestampUTC((int) $parsed['validTo_time_t']),
                'issuer' => Arr::get($parsed, 'issuer.O', Arr::get($parsed, 'issuer.CN')),
            ];
        } catch (Throwable) {
            return null;
        }
    }
}
