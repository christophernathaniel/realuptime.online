<?php

namespace App\Services\Monitoring;

use App\Services\Security\PublicNetworkGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

class TlsMetadataResolver
{
    public function __construct(protected ?PublicNetworkGuard $network = null) {}

    /**
     * @return array{expires_at: CarbonImmutable, issuer: string|null}|null
     */
    public function resolve(string $host, int $timeoutSeconds = 10): ?array
    {
        $network = $this->network ?? app(PublicNetworkGuard::class);

        try {
            $address = $network->resolveHost($host);
        } catch (Throwable) {
            return null;
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
                'SNI_server_name' => $host,
            ],
        ]);

        $socketAddress = str_contains($address, ':') ? '['.$address.']' : $address;

        try {
            $client = @stream_socket_client(
                sprintf('ssl://%s:443', $socketAddress),
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
