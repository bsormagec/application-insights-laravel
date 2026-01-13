<?php

namespace Sormagec\AppInsightsLaravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sormagec\AppInsightsLaravel\Providers\AppInsightsServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            AppInsightsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('appinsights-laravel.connection_string', 'InstrumentationKey=00000000-0000-0000-0000-000000000000;IngestionEndpoint=https://northeurope-0.in.applicationinsights.azure.com/;LiveEndpoint=https://northeurope.livediagnostics.monitor.azure.com/');
        config()->set('appinsights-laravel.enabled', true);
    }
}
