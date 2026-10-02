<?php

namespace App\Providers;

use App\Services\Sadad\TripleDesCipher;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TripleDesCipher::class, function (): TripleDesCipher {
            return new TripleDesCipher((string) config('services.sadad.terminal_key'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The reverse proxy presents requests as localhost. Links follow APP_URL instead.
        $root = (string) config('app.url');

        if ($root === '') {
            return;
        }

        URL::useOrigin($root);

        if (str_starts_with($root, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
