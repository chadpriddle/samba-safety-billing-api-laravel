<?php

namespace ChadPriddle\SambaBilling;

use Illuminate\Support\ServiceProvider;

class SambaBillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/samba-billing.php',
            'samba-billing'
        );

        $this->app->singleton(SambaBilling::class, fn () => new SambaBilling());

        $this->app->alias(SambaBilling::class, 'samba-billing');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/samba-billing.php' => config_path('samba-billing.php'),
        ], 'samba-billing-config');
    }
}
