<?php

use App\Services\Security\HostResolver;
use App\Services\Security\PublicNetworkGuard;

function networkGuardResolvingTo(array $addresses): PublicNetworkGuard
{
    return new PublicNetworkGuard(new class($addresses) implements HostResolver
    {
        public function __construct(private array $addresses) {}

        public function resolve(string $host): array
        {
            return $this->addresses;
        }
    });
}

it('resolves and accepts a public http destination', function () {
    $target = networkGuardResolvingTo(['2606:4700:4700::1111', '1.1.1.1'])
        ->resolveHttpUrl('https://status.example.com:8443/health');

    expect($target->host)->toBe('status.example.com')
        ->and($target->port)->toBe(8443)
        ->and($target->address)->toBe('1.1.1.1')
        ->and($target->curlResolveEntry())->toBe('status.example.com:8443:1.1.1.1');
});

it('rejects private reserved and local destinations', function (string $url) {
    networkGuardResolvingTo(['1.1.1.1'])->validateHttpUrl($url);
})->with([
    'loopback' => 'http://127.0.0.1/admin',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data',
    'private ipv4' => 'http://10.20.30.40/',
    'carrier grade nat' => 'http://100.64.0.1/',
    'documentation range' => 'http://198.51.100.10/',
    'multicast range' => 'http://224.0.0.1/',
    'private ipv6' => 'http://[::1]/',
    'ipv4 mapped ipv6' => 'http://[::ffff:127.0.0.1]/',
    'local hostname' => 'http://service.internal/health',
])->throws(InvalidArgumentException::class);

it('rejects non-http schemes and embedded credentials', function (string $url) {
    networkGuardResolvingTo(['1.1.1.1'])->validateHttpUrl($url);
})->with([
    'file scheme' => 'file:///etc/passwd',
    'ftp scheme' => 'ftp://example.com/file',
    'embedded credentials' => 'https://user:secret@example.com/health',
])->throws(InvalidArgumentException::class);

it('rejects a hostname when any resolved address is private', function () {
    networkGuardResolvingTo(['1.1.1.1', '10.0.0.5'])
        ->resolveHost('rebound.example.com');
})->throws(InvalidArgumentException::class, 'private or reserved');
