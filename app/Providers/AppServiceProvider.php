<?php

namespace App\Providers;

use App\Services\Sadad\TripleDesCipher;
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
        //
    }
}
