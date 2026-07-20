<?php

namespace Tests;

use App\Services\Security\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new class implements HostResolver
        {
            public function resolve(string $host): array
            {
                return ['1.1.1.1'];
            }
        });
    }
}
