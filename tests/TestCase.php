<?php

namespace ChadPriddle\SambaBilling\Tests;

use ChadPriddle\SambaBilling\SambaBillingServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SambaBillingServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('samba-billing.url', 'https://billing.example.test');
        $app['config']->set('samba-billing.api_key', 'test-token');
        $app['config']->set('samba-billing.retry.times', 1);
    }
}
