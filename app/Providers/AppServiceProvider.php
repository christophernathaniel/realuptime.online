<?php

namespace App\Providers;

use App\Services\Security\HostResolver;
use App\Services\Security\SystemHostResolver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HostResolver::class, SystemHostResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureApiRateLimiting();
        $this->configureHeartbeatRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureApiRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $token = $request->bearerToken();
            $perToken = max(1, (int) config('realuptime.security.api_rate_limit_per_minute', 120));
            $perIp = max(1, (int) config('realuptime.security.api_ip_rate_limit_per_minute', 600));

            return [
                Limit::perMinute($perToken)->by(
                    $token
                        ? 'api-token:'.hash('sha256', $token)
                        : 'api-missing:'.$request->ip(),
                ),
                Limit::perMinute($perIp)->by('api-ip:'.$request->ip()),
            ];
        });
    }

    protected function configureHeartbeatRateLimiting(): void
    {
        RateLimiter::for('heartbeat', function (Request $request) {
            $token = (string) $request->route('token');
            $perToken = max(1, (int) config('realuptime.security.heartbeat_rate_limit_per_minute', 12));
            $perIp = max(1, (int) config('realuptime.security.heartbeat_ip_rate_limit_per_minute', 600));

            return [
                Limit::perMinute($perToken)->by('heartbeat-token:'.hash('sha256', $token)),
                Limit::perMinute($perIp)->by('heartbeat-ip:'.$request->ip()),
            ];
        });
    }
}
