<?php

$monitorCheckQueue = env('REALUPTIME_MONITOR_QUEUE', 'monitor-checks');
$monitorCheckShards = array_values(array_filter(array_map(
    static fn (string $queue): string => trim($queue),
    explode(',', (string) env('REALUPTIME_MONITOR_QUEUE_SHARDS', ''))
)));
$httpMonitorCheckQueue = env('REALUPTIME_HTTP_MONITOR_QUEUE', $monitorCheckQueue);
$httpMonitorCheckShards = array_values(array_filter(array_map(
    static fn (string $queue): string => trim($queue),
    explode(',', (string) env('REALUPTIME_HTTP_MONITOR_QUEUE_SHARDS', implode(',', $monitorCheckShards)))
)));
$networkMonitorCheckQueue = env('REALUPTIME_NETWORK_MONITOR_QUEUE', $monitorCheckQueue);
$networkMonitorCheckShards = array_values(array_filter(array_map(
    static fn (string $queue): string => trim($queue),
    explode(',', (string) env('REALUPTIME_NETWORK_MONITOR_QUEUE_SHARDS', ''))
)));
$syntheticMonitorCheckQueue = env('REALUPTIME_SYNTHETIC_MONITOR_QUEUE', $monitorCheckQueue);
$syntheticMonitorCheckShards = array_values(array_filter(array_map(
    static fn (string $queue): string => trim($queue),
    explode(',', (string) env('REALUPTIME_SYNTHETIC_MONITOR_QUEUE_SHARDS', ''))
)));
$probeRegions = [
    'North America' => [
        'queue_token' => env('REALUPTIME_NORTH_AMERICA_QUEUE_TOKEN', 'na'),
        'confirmation_regions' => array_values(array_filter(array_map(
            static fn (string $region): string => trim($region),
            explode(',', (string) env('REALUPTIME_NORTH_AMERICA_CONFIRMATION_REGIONS', 'Europe'))
        ))),
    ],
    'Europe' => [
        'queue_token' => env('REALUPTIME_EUROPE_QUEUE_TOKEN', 'eu'),
        'confirmation_regions' => array_values(array_filter(array_map(
            static fn (string $region): string => trim($region),
            explode(',', (string) env('REALUPTIME_EUROPE_CONFIRMATION_REGIONS', 'North America'))
        ))),
    ],
    'Asia Pacific' => [
        'queue_token' => env('REALUPTIME_ASIA_PACIFIC_QUEUE_TOKEN', 'apac'),
        'confirmation_regions' => array_values(array_filter(array_map(
            static fn (string $region): string => trim($region),
            explode(',', (string) env('REALUPTIME_ASIA_PACIFIC_CONFIRMATION_REGIONS', 'Europe'))
        ))),
    ],
];
$rawCheckResultRetentionDays = min(30, max(1, (int) env('REALUPTIME_RAW_CHECK_RESULT_RETENTION_DAYS', 30)));
$fineRollupRetentionDays = min(180, max(
    $rawCheckResultRetentionDays + 1,
    (int) env('REALUPTIME_FINE_ROLLUP_RETENTION_DAYS', 180),
));
$checkHistoryRetentionDays = min(730, max(
    $fineRollupRetentionDays + 1,
    (int) env('REALUPTIME_CHECK_HISTORY_RETENTION_DAYS', 730),
));

return [
    'admin' => [
        'main_admin_email' => env('REALUPTIME_MAIN_ADMIN_EMAIL'),
    ],

    'queues' => [
        'monitor_checks' => $monitorCheckShards[0] ?? $monitorCheckQueue,
        'monitor_check_shards' => $monitorCheckShards !== [] ? $monitorCheckShards : [$monitorCheckQueue],
        'http_monitor_checks' => $httpMonitorCheckShards[0] ?? $httpMonitorCheckQueue,
        'http_monitor_check_shards' => $httpMonitorCheckShards !== [] ? $httpMonitorCheckShards : [],
        'network_monitor_checks' => $networkMonitorCheckShards[0] ?? $networkMonitorCheckQueue,
        'network_monitor_check_shards' => $networkMonitorCheckShards !== [] ? $networkMonitorCheckShards : [],
        'synthetic_monitor_checks' => $syntheticMonitorCheckShards[0] ?? $syntheticMonitorCheckQueue,
        'synthetic_monitor_check_shards' => $syntheticMonitorCheckShards !== [] ? $syntheticMonitorCheckShards : [],
        'monitor_metadata' => env('REALUPTIME_METADATA_QUEUE', 'monitor-metadata'),
        'notifications' => env('REALUPTIME_NOTIFICATION_QUEUE', 'notifications'),
    ],

    'dispatch' => [
        'batch_size' => (int) env('REALUPTIME_DISPATCH_BATCH_SIZE', 250),
        'max_batches' => (int) env('REALUPTIME_DISPATCH_MAX_BATCHES', 12),
        'claim_ttl_seconds' => (int) env('REALUPTIME_CHECK_CLAIM_TTL_SECONDS', 180),
        'minimum_interval_seconds' => max(60, (int) env('REALUPTIME_MINIMUM_MONITOR_INTERVAL_SECONDS', 60)),
    ],

    'probe_regions' => [
        'use_region_queues' => (bool) env('REALUPTIME_USE_REGION_QUEUES', false),
        'regions' => $probeRegions,
    ],

    'confirmations' => [
        'recovery_enabled' => (bool) env('REALUPTIME_REGION_RECOVERY_CONFIRMATION', true),
        'required_successes' => (int) env('REALUPTIME_REGION_CONFIRMATION_REQUIRED_SUCCESSES', 1),
    ],

    'session_tracking' => [
        'verify_seconds' => (int) env('REALUPTIME_SESSION_VERIFY_SECONDS', 60),
        'refresh_seconds' => (int) env('REALUPTIME_SESSION_REFRESH_SECONDS', 300),
    ],

    'public_status' => [
        'cache_seconds' => (int) env('REALUPTIME_PUBLIC_STATUS_CACHE_SECONDS', 15),
    ],

    'invitations' => [
        'expires_after_days' => (int) env('REALUPTIME_INVITATION_EXPIRES_AFTER_DAYS', 7),
    ],

    'ping' => [
        'healthy_result_sample_seconds' => (int) env('REALUPTIME_PING_HEALTHY_RESULT_SAMPLE_SECONDS', 300),
    ],

    'security' => [
        'max_outbound_response_bytes' => (int) env('REALUPTIME_MAX_OUTBOUND_RESPONSE_BYTES', 2 * 1024 * 1024),
        'max_outbound_redirects' => (int) env('REALUPTIME_MAX_OUTBOUND_REDIRECTS', 5),
        'api_rate_limit_per_minute' => (int) env('REALUPTIME_API_RATE_LIMIT_PER_MINUTE', 120),
        'api_ip_rate_limit_per_minute' => (int) env('REALUPTIME_API_IP_RATE_LIMIT_PER_MINUTE', 600),
        'heartbeat_rate_limit_per_minute' => (int) env('REALUPTIME_HEARTBEAT_RATE_LIMIT_PER_MINUTE', 12),
        'heartbeat_ip_rate_limit_per_minute' => (int) env('REALUPTIME_HEARTBEAT_IP_RATE_LIMIT_PER_MINUTE', 600),
        'slack_webhook_hosts' => [
            'hooks.slack.com',
            'hooks.slack-gov.com',
        ],
        'blocked_outbound_headers' => [
            'connection',
            'content-length',
            'expect',
            'host',
            'keep-alive',
            'proxy-authenticate',
            'proxy-authorization',
            'proxy-connection',
            'te',
            'trailer',
            'transfer-encoding',
            'upgrade',
        ],
    ],

    'guardrails' => [
        'max_timeout_seconds' => (int) env('REALUPTIME_MAX_TIMEOUT_SECONDS', 15),
        'max_retry_limit' => (int) env('REALUPTIME_MAX_RETRY_LIMIT', 2),
        'max_contacts_per_monitor' => (int) env('REALUPTIME_MAX_CONTACTS_PER_MONITOR', 5),
        'max_downtime_webhook_urls' => (int) env('REALUPTIME_MAX_DOWNTIME_WEBHOOK_URLS', 2),
        'max_target_length' => (int) env('REALUPTIME_MAX_MONITOR_TARGET_LENGTH', 1024),
        'max_custom_header_count' => (int) env('REALUPTIME_MAX_CUSTOM_HEADER_COUNT', 8),
        'max_custom_header_name_length' => (int) env('REALUPTIME_MAX_CUSTOM_HEADER_NAME_LENGTH', 64),
        'max_custom_header_value_length' => (int) env('REALUPTIME_MAX_CUSTOM_HEADER_VALUE_LENGTH', 256),
        'max_custom_headers_payload_length' => (int) env('REALUPTIME_MAX_CUSTOM_HEADERS_PAYLOAD_LENGTH', 4096),
        'max_webhook_url_length' => (int) env('REALUPTIME_MAX_WEBHOOK_URL_LENGTH', 2048),
    ],

    'retention' => [
        'notification_logs_days' => (int) env('REALUPTIME_NOTIFICATION_LOG_RETENTION_DAYS', 30),
        'raw_check_results_days' => $rawCheckResultRetentionDays,
        'fine_rollup_days' => $fineRollupRetentionDays,
        'check_history_days' => $checkHistoryRetentionDays,
        'prune_chunk_size' => (int) env('REALUPTIME_PRUNE_CHUNK_SIZE', 1000),
        'automatic_pruning_enabled' => (bool) env('REALUPTIME_AUTOMATIC_PRUNING_ENABLED', true),
        'prune_at' => env('REALUPTIME_PRUNE_AT', '03:15'),
    ],
];
