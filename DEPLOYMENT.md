# RealUptime Deployment

## Required services

- PHP 8.2+
- Node.js 20+
- A relational database supported by Laravel
- Redis for production queue, cache, and session workloads
- A working outbound mail provider

## Environment checklist

Set these before production traffic:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.example`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `SESSION_STORE=redis`
- `SESSION_CONNECTION=session`
- `SESSION_ENCRYPT=true`
- `SESSION_SECURE_COOKIE=true`
- `MAIL_MAILER`, `MAIL_FROM_ADDRESS`
- `RESEND_API_KEY` if using the official Resend driver
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` if using SMTP instead
- `REDIS_QUEUE_CONNECTION=queue`
- `REDIS_CACHE_CONNECTION=cache`
- `REDIS_SESSION_CONNECTION=session`
- `REALUPTIME_MONITOR_QUEUE=monitor-checks`
- `REALUPTIME_NOTIFICATION_QUEUE=notifications`
- `REALUPTIME_METADATA_QUEUE=monitor-metadata`
- `REALUPTIME_DISPATCH_BATCH_SIZE=250`
- `REALUPTIME_DISPATCH_MAX_BATCHES=12`
- `REALUPTIME_CHECK_CLAIM_TTL_SECONDS=600`
- `GOOGLE_*` and `GITHUB_*` if OAuth sign-in is enabled

Do not enable `REALUPTIME_DEMO_DATA` in production.

Redis is optional in local development. The app still defaults to database-backed queue, cache, and session drivers unless you explicitly switch the environment variables above.

## Redis security

- Keep Redis on a private network. Do not expose it publicly to the internet.
- Require authentication and prefer a managed Redis endpoint with TLS. Use `REDIS_URL=rediss://...` when your provider supports it.
- Use separate Redis logical databases or endpoints for cache, sessions, and queues. RealUptime supports dedicated `cache`, `session`, and `queue` Redis connections out of the box.
- Provide either the `phpredis` PHP extension or a compatible Laravel Redis client in the deployment environment.
- Moving sessions to Redis does not move authentication state into the browser. The browser still only stores the session cookie identifier. Revocation and device/session auditing continue to use the `user_sessions` table.
- Keep `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, and `SESSION_SECURE_COOKIE=true` in production.

## First deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Mail providers

RealUptime supports Laravel mail transports, including the official Resend driver.

### Resend

Use this when you want API-backed transactional mail without managing SMTP credentials:

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=no-reply@realuptime.online
MAIL_FROM_NAME="RealUptime"
RESEND_API_KEY=re_xxxxxxxxx
```

You must also verify the sending domain in Resend and publish the DNS records it provides.

### SMTP

If you prefer SMTP instead:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-user
MAIL_PASSWORD=your-password
MAIL_FROM_ADDRESS=no-reply@realuptime.online
MAIL_FROM_NAME="RealUptime"
```

## Long-running processes

RealUptime needs both the scheduler and queue workers running continuously.

### Scheduler

```bash
php artisan schedule:work
```

### Queue workers

```bash
php artisan queue:work --queue=monitor-checks,monitor-metadata,notifications,default --sleep=1 --timeout=120 --tries=1
```

If you need more monitor throughput on Redis, shard the monitor queue:

```env
REALUPTIME_MONITOR_QUEUE_SHARDS=monitor-checks-a,monitor-checks-b,monitor-checks-c,monitor-checks-d
REALUPTIME_DISPATCH_BATCH_SIZE=500
REALUPTIME_DISPATCH_MAX_BATCHES=24
```

Then run dedicated workers against those shards:

```bash
php artisan queue:work --queue=monitor-checks-a,monitor-checks-b,monitor-checks-c,monitor-checks-d,monitor-metadata --sleep=1 --timeout=120 --tries=1
php artisan queue:work --queue=notifications,default --sleep=1 --timeout=120 --tries=3
```

For multi-node deployments, run multiple queue workers and keep the scheduler on one node or use Laravel's `onOneServer()` support with a shared cache backend.

## Scaling guidance

- Use Redis, not the database queue, once you have real traffic.
- Put sessions on Redis when you have multiple web nodes so logins, revocations, and session continuity stay consistent across the fleet.
- Scale `monitor-checks` workers independently from `notifications` workers if email bursts start to compete with check execution.
- Use `REALUPTIME_MONITOR_QUEUE_SHARDS` once a single `monitor-checks` queue becomes a hot spot.
- Keep `monitor-checks` workers stateless and horizontally scalable.
- Watch queue lag, stale claims, and failed jobs from the `Integrations & API` page and your infrastructure monitoring.
- If throughput outgrows Redis, move queue transport to a managed system such as SQS while keeping the same job boundaries.

## Post-deploy checks

- Log in and create a monitor.
- Trigger `Run check` on a monitor detail page.
- Trigger `Test Notification` and confirm a log entry moves from `Pending` to `Sent`.
- Generate an API token from `Integrations & API` and call `/api/v1/workspace`.
- Confirm `/status/{user_id}/{slug}` is reachable for a published status page.
