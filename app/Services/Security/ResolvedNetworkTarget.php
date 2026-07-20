<?php

namespace App\Services\Security;

class ResolvedNetworkTarget
{
    public function __construct(
        public readonly string $url,
        public readonly string $scheme,
        public readonly string $host,
        public readonly int $port,
        public readonly string $address,
    ) {}

    public function curlResolveEntry(): string
    {
        $address = str_contains($this->address, ':')
            ? '['.$this->address.']'
            : $this->address;

        return sprintf('%s:%d:%s', $this->host, $this->port, $address);
    }

    public function origin(): string
    {
        return sprintf('%s://%s:%d', $this->scheme, $this->host, $this->port);
    }
}
