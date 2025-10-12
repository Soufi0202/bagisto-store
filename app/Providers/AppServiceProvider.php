<?php

namespace App\Providers;

use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $allowedIPs = array_map('trim', explode(',', config('app.debug_allowed_ips')));

        $allowedIPs = array_filter($allowedIPs);

        if (empty($allowedIPs)) {
            return;
        }

        if (in_array(Request::ip(), $allowedIPs)) {
            Debugbar::enable();
        } else {
            Debugbar::disable();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS URLs in production with Railway proxy fix
        if (env('APP_ENV') === 'production' || env('APP_ENV') === 'staging') {
            URL::forceScheme('https');
            
            // Force Laravel to recognize HTTPS behind Railway's proxy
            $this->app['request']->server->set('HTTPS', 'on');
            
            // Trust Railway's proxy headers
            if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $_SERVER['HTTPS'] = 'on';
            }
            
            // Fix for mixed content issues
            if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
                $this->app['request']->headers->set('X-Forwarded-Host', $_SERVER['HTTP_X_FORWARDED_HOST']);
            }
        }
        
        // Set default string length for migrations (prevents key length issues)
        Schema::defaultStringLength(191);

        ParallelTesting::setUpTestDatabase(function (string $database, int $token) {
            Artisan::call('db:seed');
        });
    }
}