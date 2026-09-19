<?php

namespace App\Providers;

use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        RateLimiter::for('web-login', function (Request $request): array {
            $identity = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('web-login:'.$identity.'|'.$request->ip()),
                Limit::perMinute(20)->by('web-login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('api-login', function (Request $request): array {
            $identity = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(8)->by('api-login:'.$identity.'|'.$request->ip()),
                Limit::perMinute(30)->by('api-login-ip:'.$request->ip()),
            ];
        });
    }
}
