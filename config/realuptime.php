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
];
