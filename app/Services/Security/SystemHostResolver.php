<?php

namespace App\Services\Security;

class SystemHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $addresses = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;

                if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP)) {
                    $addresses[] = $address;
                }
            }
        }

        if ($addresses === []) {
            $ipv4Addresses = @gethostbynamel($host);

            if (is_array($ipv4Addresses)) {
                $addresses = $ipv4Addresses;
            }
        }

        return array_values(array_unique($addresses));
    }
}
