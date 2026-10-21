<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = config('app.url');
        if (!empty($appUrl)) {
            $parsed = parse_url($appUrl);
            $path = $parsed['path'] ?? '';
            if (!empty($path) && trim($path, '/') !== '') {
                URL::forceRootUrl($appUrl);
                if (($parsed['scheme'] ?? '') === 'https') {
                    URL::forceScheme('https');
                }
            }
        }
    }
}
