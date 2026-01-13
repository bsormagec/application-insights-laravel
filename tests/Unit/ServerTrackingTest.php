<?php

use Sormagec\AppInsightsLaravel\AppInsightsServer;

it('tracks various telemetry types', function () {
    $server = app('AppInsightsServer');
    $initialCount = count($server->getQueue());

    $server->trackMessage('test message');
    $server->trackTrace('test trace', 2);
    $server->trackMetric('test metric', 42.0);
    $server->trackDependency('SQL', 'db', 'select', 10.0);

    expect(count($server->getQueue()))->toBe($initialCount + 4);
});

it('forwards calls to telemetry client', function () {
    $server = app('AppInsightsServer');

    // Test a method that exists on Telemetry_Client but not on AppInsightsServer directly
    // Telemetry_Client::trackEvent is also available via __call
    $initialCount = count($server->getQueue());
    $server->trackEvent('Dynamic Event');

    expect(count($server->getQueue()))->toBe($initialCount + 1);
});
