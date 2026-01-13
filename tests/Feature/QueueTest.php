<?php

use Sormagec\AppInsightsLaravel\Queue\AppInsightsTelemetryQueue;
use Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade as AIServer;

it('handles telemetry data in the queue job', function () {
    $data = [
        ['name' => 'Test Event', 'type' => 'event']
    ];

    AIServer::shouldReceive('setQueue')
        ->once()
        ->with($data);

    $job = new AppInsightsTelemetryQueue($data);
    $job->handle();
});

it('skips empty data in the queue job', function () {
    AIServer::shouldReceive('setQueue')->never();

    $job = new AppInsightsTelemetryQueue([]);
    $job->handle();
});
