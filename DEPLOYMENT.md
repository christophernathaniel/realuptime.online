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
- `REALUPTIME_MAIN_ADMIN_EMAIL=admin@your-domain.example`
- `REALUPTIME_HTTP_MONITOR_QUEUE=monitor-checks`
- `REALUPTIME_NETWORK_MONITOR_QUEUE=monitor-checks`
- `REALUPTIME_SYNTHETIC_MONITOR_QUEUE=monitor-checks`
- `REALUPTIME_NOTIFICATION_QUEUE=notifications`
- `REALUPTIME_METADATA_QUEUE=monitor-metadata`
- `REALUPTIME_DISPATCH_BATCH_SIZE=250`
- `REALUPTIME_DISPATCH_MAX_BATCHES=12`
- `REALUPTIME_CHECK_CLAIM_TTL_SECONDS=180`
- `REALUPTIME_MINIMUM_MONITOR_INTERVAL_SECONDS=60`
- `REALUPTIME_MAX_OUTBOUND_RESPONSE_BYTES=2097152`
- `REALUPTIME_MAX_OUTBOUND_REDIRECTS=5`
- `REALUPTIME_HEARTBEAT_RATE_LIMIT_PER_MINUTE=12`
- `REALUPTIME_AUTOMATIC_PRUNING_ENABLED=true`
- `REALUPTIME_PRUNE_AT=03:15`
- `STRIPE_KEY`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_PREMIUM_PRICE_ID`
- `STRIPE_ULTRA_PRICE_ID`
- `CASHIER_CURRENCY=gbp`
- `CASHIER_CURRENCY_LOCALE=en_GB`
- `GOOGLE_*` and `GITHUB_*` if OAuth sign-in is enabled

Do not enable `REALUPTIME_DEMO_DATA` in production.

Redis is optional in local development. The app still defaults to database-backed queue, cache, and session drivers unless you explicitly switch the environment variables above.

## Main admin

Only the account matching `REALUPTIME_MAIN_ADMIN_EMAIL` and carrying the database admin flag can access platform user and subscription controls. After that user exists, provision it once during deployment:

```bash
php artisan realuptime:admin-user admin@your-domain.example
```

Granting the configured account automatically removes stale admin flags from every other account. Changing the main admin requires changing the environment variable, rebuilding the config cache, and running the command for the new address.

## Stripe billing

Create one GBP monthly recurring Stripe Price for Premium and one for Ultra. Put the resulting `price_...` identifiers in `STRIPE_PREMIUM_PRICE_ID` and `STRIPE_ULTRA_PRICE_ID`.

Register this signed webhook endpoint in Stripe Workbench:

```text
https://your-domain.example/stripe/webhook
```

Use the endpoint signing secret as `STRIPE_WEBHOOK_SECRET`. Cashier registers and verifies this route and synchronises subscription creation, plan changes, cancellations, payment-method changes, and successful or action-required invoices. Enable Stripe's customer billing portal so customers can update payment methods and inspect billing without exposing Stripe credentials in RealUptime.

Use Stripe test mode first, complete Checkout, change a plan, schedule cancellation, resume it, and verify the matching local `subscriptions` row changes after each webhook. Repeat with live keys before launch.

## Redis security

- Keep Redis on a private network. Do not expose it publicly to the internet.
- Require authentication and prefer a managed Redis endpoint with TLS. Use `REDIS_URL=rediss://...` when your provider supports it.
- Use separate Redis logical databases or endpoints for cache, sessions, and queues. RealUptime supports dedicated `cache`, `session`, and `queue` Redis connections out of the box.
- Provide either the `phpredis` PHP extension or a compatible Laravel Redis client in the deployment environment.
- Moving sessions to Redis does not move authentication state into the browser. The browser still only stores the session cookie identifier. Revocation and device/session auditing continue to use the `user_sessions` table.
- Keep `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, and `SESSION_SECURE_COOKIE=true` in production.

## Monitor worker network isolation

Run monitor and notification workers in a dedicated network segment with no route to databases, Redis, control panels, cloud metadata endpoints, or other private services. At the infrastructure firewall, deny loopback, link-local, carrier-grade NAT, private IPv4, unique-local IPv6, and cloud metadata ranges before allowing outbound internet traffic. The application validates and pins public DNS answers on every outbound request, but network egress policy remains the final boundary against SSRF and DNS-rebinding regressions.

The PHP cURL extension is required so HTTP checks can pin the validated IP address with `CURLOPT_RESOLVE`. Do not deploy monitor workers with an HTTP proxy that can independently resolve or reach blocked private destinations.

## First deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan realuptime:ship-check
```

The ship check fails without printing secrets when production, Redis, main-admin, Stripe, retention, or one-minute cadence configuration is incomplete.

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

Register this as a managed, always-running service in the production platform alongside the queue workers. It is not a command that should be started manually after each deployment.

The scheduler automatically runs monitoring data retention every day at `REALUPTIME_PRUNE_AT` in the application timezone. Laravel starts it as an isolated background process with a six-hour overlap lock and single-server coordination, and logs both completion totals and failures. A large compaction therefore cannot block the scheduler or occupy a monitor queue worker. Do not add a second cleanup cron entry or run the pruning command routinely.

### Dedicated dispatcher

Run this as a separate long-running service for monitor throughput. It removes the 30-second scheduler burst pattern and dispatches due checks every second instead:

```bash
php artisan monitors:dispatch-loop --sleep-ms=1000
```

Run exactly one dispatcher process initially. Monitor claims are database-backed and safe across restarts; a composite due-monitor index keeps each claim query bounded. The scheduler remains a low-frequency fallback if the dedicated dispatcher is temporarily unavailable.

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

If you need to isolate slow check types, split the monitor queues by family:

```env
REALUPTIME_HTTP_MONITOR_QUEUE_SHARDS=monitor-http-a,monitor-http-b,monitor-http-c,monitor-http-d
REALUPTIME_NETWORK_MONITOR_QUEUE_SHARDS=monitor-network-a,monitor-network-b
REALUPTIME_SYNTHETIC_MONITOR_QUEUE=monitor-synthetic
```

Then run dedicated workers for those families:

```bash
php artisan queue:work --queue=monitor-http-a,monitor-http-b,monitor-http-c,monitor-http-d,monitor-metadata --sleep=1 --timeout=120 --tries=1
php artisan queue:work --queue=monitor-network-a,monitor-network-b --sleep=1 --timeout=120 --tries=1
php artisan queue:work --queue=monitor-synthetic --sleep=1 --timeout=180 --tries=1
php artisan queue:work --queue=notifications,default --sleep=1 --timeout=120 --tries=3
```

If you want the monitor `region` setting to correspond to real probe origins, enable region queues and run workers from machines in those regions:

```env
REALUPTIME_USE_REGION_QUEUES=true
REALUPTIME_REGION_RECOVERY_CONFIRMATION=true
REALUPTIME_NORTH_AMERICA_QUEUE_TOKEN=na
REALUPTIME_EUROPE_QUEUE_TOKEN=eu
REALUPTIME_ASIA_PACIFIC_QUEUE_TOKEN=apac
REALUPTIME_NORTH_AMERICA_CONFIRMATION_REGIONS=Europe
REALUPTIME_EUROPE_CONFIRMATION_REGIONS=North America
REALUPTIME_ASIA_PACIFIC_CONFIRMATION_REGIONS=Europe
```

Example region worker layout:

```bash
# North America host
php artisan queue:work --queue=monitor-checks-na,monitor-http-a-na,monitor-http-b-na,monitor-network-a-na,monitor-metadata --sleep=1 --timeout=120 --tries=1

# Europe host
php artisan queue:work --queue=monitor-checks-eu,monitor-http-a-eu,monitor-http-b-eu,monitor-network-a-eu,monitor-metadata --sleep=1 --timeout=120 --tries=1

# Asia Pacific host
php artisan queue:work --queue=monitor-checks-apac,monitor-http-a-apac,monitor-http-b-apac,monitor-network-a-apac,monitor-metadata --sleep=1 --timeout=120 --tries=1
```

Regional recovery confirmation uses those queues to request a second opinion before resolving Cloudflare/CDN-style outages. If you enable `REALUPTIME_USE_REGION_QUEUES` but do not run workers for the suffixed queues, recovery confirmations will stay pending.

For multi-node deployments, run multiple queue workers and keep the scheduler on one node or use Laravel's `onOneServer()` support with a shared cache backend.

## Scaling guidance

- Use Redis, not the database queue, once you have real traffic.
- Put sessions on Redis when you have multiple web nodes so logins, revocations, and session continuity stay consistent across the fleet.
- Scale `monitor-checks` workers independently from `notifications` workers if email bursts start to compete with check execution.
- Use `REALUPTIME_MONITOR_QUEUE_SHARDS` once a single `monitor-checks` queue becomes a hot spot.
- Keep `monitor-checks` workers stateless and horizontally scalable.
- Watch queue lag, stale claims, and failed jobs from the `Integrations & API` page and your infrastructure monitoring.
- If throughput outgrows Redis, move queue transport to a managed system such as SQS while keeping the same job boundaries.
- Size monitor workers from measured check duration: required concurrency is approximately `sites × average check seconds ÷ 60`, then add at least 50% headroom. For 5,000 sites averaging one second, start near 125 concurrent check slots across the worker fleet.
- Keep timeouts and retries conservative. A 15-second timeout with two retries can occupy a worker for roughly 45 seconds, so widespread upstream failure requires substantially more capacity than the healthy baseline.
- Alert when queue lag exceeds 30 seconds or stale claims increase. Those are earlier capacity signals than page-response time.

## Post-deploy checks

- Log in and create a monitor.
- Trigger `Run check` on a monitor detail page.
- Trigger `Test Notification` and confirm a log entry moves from `Pending` to `Sent`.
- Generate an API token from `Integrations & API` and call `/api/v1/workspace`.
- Confirm `/status/{user_id}/{slug}` is reachable for a published status page.
- Confirm the production process manager reports the scheduler as healthy. Daily retention uses that existing service and requires no separate worker, cleanup daemon, or recurring manual command.
- Confirm the process manager reports the dedicated dispatcher and every queue shard worker as healthy.
- Confirm monitor worker firewall rules reject private addresses and the platform cloud metadata endpoint.
- Run `php artisan realuptime:ship-check` against the live environment and resolve every failed row.
- Confirm a signed Stripe test webhook reaches `/stripe/webhook` and changes local subscription state.
