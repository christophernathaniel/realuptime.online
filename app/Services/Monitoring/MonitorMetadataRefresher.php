<?php

namespace App\Services\Monitoring;

use App\Models\Monitor;
use Carbon\CarbonImmutable;

class MonitorMetadataRefresher
{
    public function __construct(
        protected DomainMetadataResolver $domainMetadata,
        protected TlsMetadataResolver $tlsMetadata,
    ) {}

    public function needsRefresh(Monitor $monitor, CarbonImmutable $checkedAt): bool
    {
        return $this->shouldRefreshDomainMetadata($monitor, $checkedAt)
            || $this->shouldRefreshSslMetadata($monitor, $checkedAt);
    }

    public function refresh(Monitor $monitor, CarbonImmutable $checkedAt): void
    {
        if ($this->shouldRefreshDomainMetadata($monitor, $checkedAt)) {
            $this->refreshDomainMetadata($monitor, $checkedAt);
        }

        if ($this->shouldRefreshSslMetadata($monitor, $checkedAt)) {
            $this->refreshSslMetadata($monitor, $checkedAt);
        }
    }

    public function refreshDomainMetadata(Monitor $monitor, CarbonImmutable $checkedAt): void
    {
        $host = $this->monitorHost($monitor);

        if (! $host) {
            return;
        }

        $domain = $this->domainMetadata->resolve($host, $monitor->timeout_seconds);

        $monitor->forceFill([
            'domain_expires_at' => $domain['expires_at'] ?? $monitor->domain_expires_at,
            'domain_registrar' => $domain['registrar'] ?? $monitor->domain_registrar,
            'domain_checked_at' => $checkedAt,
        ])->save();
    }

    public function refreshSslMetadata(Monitor $monitor, CarbonImmutable $checkedAt): void
    {
        $host = $this->monitorHost($monitor);

        if (! $host) {
            return;
        }

        $ssl = $this->tlsMetadata->resolve($host, $monitor->timeout_seconds);

        if (! $ssl) {
            return;
        }

        $monitor->forceFill([
            'ssl_expires_at' => $ssl['expires_at'],
            'ssl_issuer' => $ssl['issuer'],
            'ssl_checked_at' => $checkedAt,
        ])->save();
    }

    protected function shouldRefreshDomainMetadata(Monitor $monitor, CarbonImmutable $checkedAt): bool
    {
        if (! in_array($monitor->type, [Monitor::TYPE_HTTP, Monitor::TYPE_KEYWORD, Monitor::TYPE_SSL, Monitor::TYPE_SYNTHETIC], true)) {
            return false;
        }

        if ($monitor->domain_checked_at && $monitor->domain_checked_at->gt($checkedAt->subDay())) {
            return false;
        }

        return $this->monitorHost($monitor) !== null;
    }

    protected function shouldRefreshSslMetadata(Monitor $monitor, CarbonImmutable $checkedAt): bool
    {
        if (! in_array($monitor->type, [Monitor::TYPE_HTTP, Monitor::TYPE_KEYWORD, Monitor::TYPE_SYNTHETIC], true)) {
            return false;
        }

        if ($monitor->ssl_checked_at && $monitor->ssl_checked_at->gt($checkedAt->subHours(12))) {
            return false;
        }

        $target = (string) ($monitor->target ?? '');

        if (! str_starts_with(strtolower($target), 'https://')) {
            return false;
        }

        return $this->monitorHost($monitor) !== null;
    }

    protected function monitorHost(Monitor $monitor): ?string
    {
        if ($monitor->type === Monitor::TYPE_PORT) {
            $target = trim((string) ($monitor->target ?? ''));

            if ($target === '' || ! str_contains($target, ':')) {
                return null;
            }

            [$host] = explode(':', $target, 2);

            $host = trim($host);

            return $host !== '' ? $host : null;
        }

        $host = parse_url((string) ($monitor->target ?? ''), PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        $target = trim((string) ($monitor->target ?? ''));

        return $target !== '' ? $target : null;
    }
}
