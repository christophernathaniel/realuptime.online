<?php

return [
    'queues' => [
        'monitor_checks' => env('REALUPTIME_MONITOR_QUEUE', 'monitor-checks'),
        'notifications' => env('REALUPTIME_NOTIFICATION_QUEUE', 'notifications'),
    ],

    'dispatch' => [
        'batch_size' => (int) env('REALUPTIME_DISPATCH_BATCH_SIZE', 250),
        'max_batches' => (int) env('REALUPTIME_DISPATCH_MAX_BATCHES', 12),
        'claim_ttl_seconds' => (int) env('REALUPTIME_CHECK_CLAIM_TTL_SECONDS', 600),
    ],

    'ping' => [
        'healthy_result_sample_seconds' => (int) env('REALUPTIME_PING_HEALTHY_RESULT_SAMPLE_SECONDS', 300),
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
];
