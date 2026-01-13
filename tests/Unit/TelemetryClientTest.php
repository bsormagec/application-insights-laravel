<?php

use Sormagec\AppInsightsLaravel\Clients\Telemetry_Client;

it('sets connection string and extracts instrumentation key', function () {
    $client = new Telemetry_Client();
    $connectionString = 'InstrumentationKey=123-456;IngestionEndpoint=https://test.endpoint.com/';

    $client->setConnectionString($connectionString);

    expect($client->getInstrumentationKey())->toBe('123-456');
});

it('buffers telemetry items', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackEvent('Test Event');

    expect($client->getQueue())->toHaveCount(1);
    expect($client->getQueue()[0]['data']['baseData']['name'])->toBe('Test Event');
});

it('formats duration correctly', function () {
    $client = new Telemetry_Client();

    // Using reflection to test protected method
    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('formatDuration');
    $method->setAccessible(true);

    expect($method->invoke($client, 1000))->toBe('00:00:01.000');
    expect($method->invoke($client, 61000))->toBe('00:01:01.000');
    expect($method->invoke($client, 3661000))->toBe('01:01:01.000');
});

it('generates span id', function () {
    $client = new Telemetry_Client();
    $spanId = $client->generateSpanId();

    expect(strlen($spanId))->toBe(16);
});

it('can add context tags', function () {
    $client = new Telemetry_Client();
    $client->addContextTag('test.tag', 'tag-value');

    $client->trackEvent('Test');
    $queue = $client->getQueue();

    expect($queue[0]['tags']['test.tag'])->toBe('tag-value');
});
