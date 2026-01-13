<?php

use Sormagec\AppInsightsLaravel\Clients\Telemetry_Client;
use Sormagec\AppInsightsLaravel\AppInsightsServer;
use Sormagec\AppInsightsLaravel\AppInsightsHelpers;

it('registers singletons in the container', function () {
    expect(app()->bound('AppInsightsServer'))->toBeTrue();
    expect(app()->bound(Telemetry_Client::class))->toBeTrue();
    expect(app()->bound(AppInsightsHelpers::class))->toBeTrue();

    $server1 = app('AppInsightsServer');
    $server2 = app('AppInsightsServer');

    expect($server1)->toBe($server2);
});

it('registers aliases', function () {
    expect(app()->make('AppInsightsServer'))->toBeInstanceOf(AppInsightsServer::class);
    expect(app()->make(AppInsightsServer::class))->toBeInstanceOf(AppInsightsServer::class);
});
