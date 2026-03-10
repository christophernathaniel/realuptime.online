# RealUptime Deployment

## Required services

- PHP 8.2+
- Node.js 20+
- A relational database supported by Laravel
- Redis for production queue and cache workloads
- A working outbound mail provider

## Environment checklist

Set these before production traffic:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://your-domain.example`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `MAIL_MAILER`, `MAIL_FROM_ADDRESS`
- `RESEND_API_KEY` if using the official Resend driver
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` if using SMTP instead
- `REALUPTIME_MONITOR_QUEUE=monitor-checks`
- `REALUPTIME_NOTIFICATION_QUEUE=notifications`
- `REALUPTIME_DISPATCH_BATCH_SIZE=250`
- `REALUPTIME_DISPATCH_MAX_BATCHES=12`
- `REALUPTIME_CHECK_CLAIM_TTL_SECONDS=600`
- `GOOGLE_*` and `GITHUB_*` if OAuth sign-in is enabled

Do not enable `REALUPTIME_DEMO_DATA` in production.

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
php artisan queue:work --queue=monitor-checks,notifications,default --sleep=1 --timeout=120 --tries=1
```

For multi-node deployments, run multiple queue workers and keep the scheduler on one node or use Laravel's `onOneServer()` support with a shared cache backend.

## Scaling guidance

- Use Redis, not the database queue, once you have real traffic.
- Scale `monitor-checks` workers independently from `notifications` workers if email bursts start to compete with check execution.
- Keep `monitor-checks` workers stateless and horizontally scalable.
- Watch queue lag, stale claims, and failed jobs from the `Integrations & API` page and your infrastructure monitoring.
- If throughput outgrows Redis, move queue transport to a managed system such as SQS while keeping the same job boundaries.

## Post-deploy checks

- Log in and create a monitor.
- Trigger `Run check` on a monitor detail page.
- Trigger `Test Notification` and confirm a log entry moves from `Pending` to `Sent`.
- Generate an API token from `Integrations & API` and call `/api/v1/workspace`.
- Confirm `/status/{user_id}/{slug}` is reachable for a published status page.
