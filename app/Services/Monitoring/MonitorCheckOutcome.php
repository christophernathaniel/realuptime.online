<?php

namespace App\Services\Monitoring;

use Carbon\CarbonImmutable;

class MonitorCheckOutcome
{
    public function __construct(
        public readonly string $status,
        public readonly CarbonImmutable $checkedAt,
        public readonly int $attempts = 1,
        public readonly ?int $responseTimeMs = null,
        public readonly ?int $httpStatusCode = null,
        public readonly ?string $errorType = null,
        public readonly ?string $errorMessage = null,
        public readonly ?bool $keywordMatch = null,
        public readonly array $meta = [],
    ) {}

    public static function up(
        CarbonImmutable $checkedAt,
        int $attempts = 1,
        ?int $responseTimeMs = null,
        ?int $httpStatusCode = null,
        ?bool $keywordMatch = null,
        array $meta = [],
    ): self {
        return new self('up', $checkedAt, $attempts, $responseTimeMs, $httpStatusCode, null, null, $keywordMatch, $meta);
    }

    public static function down(
        CarbonImmutable $checkedAt,
        int $attempts = 1,
        ?string $errorType = null,
        ?string $errorMessage = null,
        ?int $responseTimeMs = null,
        ?int $httpStatusCode = null,
        ?bool $keywordMatch = null,
        array $meta = [],
    ): self {
        return new self('down', $checkedAt, $attempts, $responseTimeMs, $httpStatusCode, $errorType, $errorMessage, $keywordMatch, $meta);
    }

    public function isUp(): bool
    {
        return $this->status === 'up';
    }
}
