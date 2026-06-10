<?php

namespace App\Support;

use InvalidArgumentException;

class HttpStatusPolicy
{
    public const DEFAULT = '200-299';

    public static function normalize(?string $policy): string
    {
        $tokens = self::parseTokens($policy);

        if ($tokens === []) {
            return self::DEFAULT;
        }

        usort($tokens, function (array $left, array $right): int {
            if ($left['start'] === $right['start']) {
                return $left['end'] <=> $right['end'];
            }

            return $left['start'] <=> $right['start'];
        });

        $deduped = [];

        foreach ($tokens as $token) {
            $normalized = $token['start'] === $token['end']
                ? (string) $token['start']
                : sprintf('%d-%d', $token['start'], $token['end']);

            $deduped[$normalized] = true;
        }

        return implode(',', array_keys($deduped));
    }

    public static function matches(int $statusCode, ?string $policy): bool
    {
        foreach (self::parseTokens($policy) as $token) {
            if ($statusCode >= $token['start'] && $statusCode <= $token['end']) {
                return true;
            }
        }

        return false;
    }

    public static function isValid(?string $policy): bool
    {
        try {
            self::parseTokens($policy);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return array<int, array{start:int,end:int}>
     */
    protected static function parseTokens(?string $policy): array
    {
        $policy = trim((string) $policy);

        if ($policy === '') {
            $policy = self::DEFAULT;
        }

        $segments = array_values(array_filter(array_map(
            static fn (string $segment): string => trim($segment),
            explode(',', $policy),
        )));

        if ($segments === []) {
            throw new InvalidArgumentException('HTTP status policy cannot be empty.');
        }

        return array_map(function (string $segment): array {
            if (preg_match('/^\d{3}$/', $segment) === 1) {
                $code = (int) $segment;
                self::assertStatusCode($code);

                return ['start' => $code, 'end' => $code];
            }

            if (preg_match('/^(\d{3})\s*-\s*(\d{3})$/', $segment, $matches) === 1) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];

                self::assertStatusCode($start);
                self::assertStatusCode($end);

                if ($end < $start) {
                    throw new InvalidArgumentException('HTTP status ranges must be ascending.');
                }

                return ['start' => $start, 'end' => $end];
            }

            throw new InvalidArgumentException('HTTP status policies may only contain three-digit codes or ranges.');
        }, $segments);
    }

    protected static function assertStatusCode(int $code): void
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException('HTTP status codes must be between 100 and 599.');
        }
    }
}
