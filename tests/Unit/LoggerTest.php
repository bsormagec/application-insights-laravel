<?php

use Sormagec\AppInsightsLaravel\Support\Logger;
use Illuminate\Support\Facades\Log;

it('logs only when enabled', function () {
    config(['appinsights-laravel.enable_local_logging' => false]);

    Log::shouldReceive('info')->never();
    Logger::info('test');

    config(['appinsights-laravel.enable_local_logging' => true]);
    Log::shouldReceive('info')->once()->with('test', []);
    Logger::info('test');
});

it('always logs errors', function () {
    config(['appinsights-laravel.enable_local_logging' => false]);

    Log::shouldReceive('error')->once()->with('error message', []);
    Logger::error('error message');
});

it('logs debug and warning when enabled', function () {
    config(['appinsights-laravel.enable_local_logging' => true]);

    Log::shouldReceive('debug')->once();
    Logger::debug('debug');

    Log::shouldReceive('warning')->once();
    Logger::warning('warning');
});
