<?php

namespace App\Services\Security;

interface HostResolver
{
    /**
     * @return array<int, string>
     */
    public function resolve(string $host): array;
}
