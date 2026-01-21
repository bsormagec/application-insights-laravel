<?php

use Sormagec\AppInsightsLaravel\AppInsightsClient;

it('generates javascript snippet', function () {
    $client = new AppInsightsClient();
    $html = $client->javascript();

    expect($html)->toContain('<script>');
    expect($html)->toContain('window.AppInsightsConfig');
    expect($html)->toContain('/appinsights/collect');
    expect($html)->toContain('appinsights-client.min.js');
});

it('includes correlation ids in javascript snippet', function () {
    $client = new AppInsightsClient();
    $html = $client->javascript();

    // Check if operationId and parentId are present (even if empty in some environments, but here they should be filled because app('AppInsightsServer') works in tests)
    expect($html)->toContain('operationId:');
    expect($html)->toContain('parentId:');
});

it('includes dependency tracking toggle in javascript snippet', function () {
    config(['appinsights-laravel.client.track_dependencies' => false]);

    $client = new AppInsightsClient();
    $html = $client->javascript();

    expect($html)->toContain('trackDependencies: false');
});

it('returns empty snippet when client telemetry is disabled', function () {
    config(['appinsights-laravel.client.enabled' => false]);

    $client = new AppInsightsClient();
    $html = $client->javascript();

    expect(trim($html))->toBe('');
});
