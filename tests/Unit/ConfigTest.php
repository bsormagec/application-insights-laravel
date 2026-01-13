<?php

use Sormagec\AppInsightsLaravel\Support\Config;

it('gets config values with prefix', function () {
    config(['appinsights-laravel.test_key' => 'test_value']);
    expect(Config::get('test_key'))->toBe('test_value');
});

it('returns default when key not found', function () {
    expect(Config::get('non_existent', 'default'))->toBe('default');
});

it('can get environment variables', function () {
    putenv('ENV_TEST=env_value');
    expect(Config::env('env_test'))->toBe('env_value');
    putenv('ENV_TEST'); // clear it
});

it('falls back to environment in get()', function () {
    putenv('APPINSIGHTS-LARAVEL_TEST_FALLBACK=fallback_value');
    expect(Config::get('test_fallback'))->toBe('fallback_value');
    putenv('APPINSIGHTS-LARAVEL_TEST_FALLBACK');
});
