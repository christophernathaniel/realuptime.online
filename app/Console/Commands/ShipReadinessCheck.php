<?php

namespace App\Console\Commands;

use App\Enums\MembershipPlan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ShipReadinessCheck extends Command
{
    protected $signature = 'realuptime:ship-check';

    protected $description = 'Validate production-critical RealUptime configuration without exposing secrets';

    public function handle(): int
    {
        $mainAdminEmail = trim((string) config('realuptime.admin.main_admin_email'));
        $mainAdminExists = $mainAdminEmail !== '' && User::query()
            ->where('is_admin', true)
            ->whereRaw('LOWER(email) = ?', [Str::lower($mainAdminEmail)])
            ->exists();
        $queueConnection = (string) config('queue.default');
        $cacheStore = (string) config('cache.default');
        $sessionDriver = (string) config('session.driver');
        $stripeKey = (string) config('cashier.key');
        $stripeSecret = (string) config('cashier.secret');
        $rawRetentionDays = (int) config('realuptime.retention.raw_check_results_days');
        $fineRetentionDays = (int) config('realuptime.retention.fine_rollup_days');
        $historyRetentionDays = (int) config('realuptime.retention.check_history_days');

        $checks = [
            ['Production environment', app()->environment('production'), (string) app()->environment()],
            ['Application key', filled(config('app.key')), 'APP_KEY'],
            ['Debug disabled', ! (bool) config('app.debug'), 'APP_DEBUG=false'],
            ['HTTPS application URL', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL'],
            ['Scalable queue driver', ! in_array($queueConnection, ['sync', 'database', 'null'], true), $queueConnection],
            ['Shared cache store', ! in_array($cacheStore, ['array', 'database', 'file', 'null'], true), $cacheStore],
            ['Redis sessions', $sessionDriver === 'redis', $sessionDriver],
            ['Encrypted sessions', (bool) config('session.encrypt'), 'SESSION_ENCRYPT=true'],
            ['Secure session cookies', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE=true'],
            ['HTTP-only session cookies', (bool) config('session.http_only'), 'SESSION_HTTP_ONLY=true'],
            ['Same-site session cookies', in_array(config('session.same_site'), ['lax', 'strict'], true), 'SESSION_SAME_SITE=lax or strict'],
            ['Secure outbound HTTP support', extension_loaded('curl') && defined('CURLOPT_RESOLVE'), 'PHP cURL with CURLOPT_RESOLVE'],
            ['Main admin email', $mainAdminEmail !== '', 'REALUPTIME_MAIN_ADMIN_EMAIL'],
            ['Main admin account', $mainAdminExists, $mainAdminEmail !== '' ? $mainAdminEmail : 'not configured'],
            ['Stripe publishable key', filled(config('cashier.key')), 'STRIPE_KEY'],
            ['Stripe secret key', filled(config('cashier.secret')), 'STRIPE_SECRET'],
            ['Stripe live mode', str_starts_with($stripeKey, 'pk_live_') && str_starts_with($stripeSecret, 'sk_live_'), 'live key pair'],
            ['Stripe webhook secret', filled(config('cashier.webhook.secret')), 'STRIPE_WEBHOOK_SECRET'],
            ['Premium Stripe price', filled(MembershipPlan::PREMIUM->stripePriceId()), 'STRIPE_PREMIUM_PRICE_ID'],
            ['Ultra Stripe price', filled(MembershipPlan::ULTRA->stripePriceId()), 'STRIPE_ULTRA_PRICE_ID'],
            ['Automatic data compaction', (bool) config('realuptime.retention.automatic_pruning_enabled'), 'REALUPTIME_AUTOMATIC_PRUNING_ENABLED'],
            ['Raw result ceiling', $rawRetentionDays <= 30, $rawRetentionDays.' days'],
            ['15-minute rollup ceiling', $fineRetentionDays <= 180 && $fineRetentionDays > $rawRetentionDays, $fineRetentionDays.' days'],
            ['Two-year history ceiling', $historyRetentionDays <= 730 && $historyRetentionDays > $fineRetentionDays, $historyRetentionDays.' days'],
            ['One-minute check floor', (int) config('realuptime.dispatch.minimum_interval_seconds') >= 60, (string) config('realuptime.dispatch.minimum_interval_seconds')],
        ];

        $this->table(
            ['Check', 'Result', 'Configuration'],
            collect($checks)->map(fn (array $check) => [
                $check[0],
                $check[1] ? 'PASS' : 'FAIL',
                $check[2],
            ])->all(),
        );

        $failed = collect($checks)->contains(fn (array $check) => ! $check[1]);

        if ($failed) {
            $this->error('RealUptime is not ready for production. Resolve every failed check before serving live traffic.');

            return self::FAILURE;
        }

        $this->info('RealUptime production configuration is ready.');

        return self::SUCCESS;
    }
}
