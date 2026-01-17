<?php

use Sormagec\AppInsightsLaravel\Clients\Telemetry_Client;
use Sormagec\AppInsightsLaravel\Support\ContextTagKeys;

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

// ==============================
// NEW TESTS FOR SDK 2.0 FEATURES
// ==============================

it('generates W3C compliant trace ID', function () {
    $client = new Telemetry_Client();
    $traceId = $client->generateW3CId();

    // W3C trace ID must be 32 hex characters
    expect(strlen($traceId))->toBe(32);
    expect(ctype_xdigit($traceId))->toBeTrue();
});

it('initializes with W3C operation ID', function () {
    $client = new Telemetry_Client();
    $operationId = $client->getOperationId();

    // Should be 32 hex characters (W3C format)
    expect(strlen($operationId))->toBe(32);
    expect(ctype_xdigit($operationId))->toBeTrue();
});

it('initializes cloud role tags', function () {
    $client = new Telemetry_Client();
    $client->trackEvent('Test');
    $queue = $client->getQueue();

    // Should have cloud role and cloud role instance
    expect($queue[0]['tags'])->toHaveKey(ContextTagKeys::CLOUD_ROLE);
    expect($queue[0]['tags'])->toHaveKey(ContextTagKeys::CLOUD_ROLE_INSTANCE);
});

it('initializes SDK version tag', function () {
    $client = new Telemetry_Client();
    $client->trackEvent('Test');
    $queue = $client->getQueue();

    expect($queue[0]['tags'])->toHaveKey(ContextTagKeys::INTERNAL_SDK_VERSION);
    expect($queue[0]['tags'][ContextTagKeys::INTERNAL_SDK_VERSION])->toStartWith('php:');
});

it('can set and get operation name', function () {
    $client = new Telemetry_Client();

    $client->setOperationName('GET /users/{id}');

    expect($client->getOperationName())->toBe('GET /users/{id}');
});

it('sets operation name in trackRequest', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackRequest('GET /test', 'https://example.com/test', 100, 200, true);

    $queue = $client->getQueue();
    expect($queue[0]['tags'])->toHaveKey(ContextTagKeys::OPERATION_NAME);
    expect($queue[0]['tags'][ContextTagKeys::OPERATION_NAME])->toBe('GET /test');
});

it('uses span ID for request id field', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackRequest('GET /test', 'https://example.com/test', 100, 200, true);

    $queue = $client->getQueue();
    $requestId = $queue[0]['data']['baseData']['id'];

    // Request ID should be 16 hex characters (span ID format)
    expect(strlen($requestId))->toBe(16);
    expect(ctype_xdigit($requestId))->toBeTrue();

    // Request ID should NOT be the operation ID
    expect($requestId)->not->toBe($client->getOperationId());
});

it('can set authenticated user ID', function () {
    $client = new Telemetry_Client();

    $client->setAuthenticatedUserId('user-123');
    $client->trackEvent('Test');

    $queue = $client->getQueue();
    expect($queue[0]['tags'])->toHaveKey(ContextTagKeys::USER_AUTH_USER_ID);
    expect($queue[0]['tags'][ContextTagKeys::USER_AUTH_USER_ID])->toBe('user-123');
});

it('can set session ID', function () {
    $client = new Telemetry_Client();

    $client->setSessionId('session-abc');
    $client->trackEvent('Test');

    $queue = $client->getQueue();
    expect($queue[0]['tags'])->toHaveKey(ContextTagKeys::SESSION_ID);
    expect($queue[0]['tags'][ContextTagKeys::SESSION_ID])->toBe('session-abc');
});

it('can set synthetic source', function () {
    $client = new Telemetry_Client();

    $client->setSyntheticSource('Bot');
    $client->trackEvent('Test');

    $queue = $client->getQueue();
    expect($queue[0]['tags'])->toHaveKey(ContextTagKeys::OPERATION_SYNTHETIC_SOURCE);
    expect($queue[0]['tags'][ContextTagKeys::OPERATION_SYNTHETIC_SOURCE])->toBe('Bot');
});

it('tracks availability results', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackAvailability('Health Check', 150.5, true, 'East US', 'All OK');

    $queue = $client->getQueue();
    expect($queue[0]['name'])->toBe('Microsoft.ApplicationInsights.Availability');
    expect($queue[0]['data']['baseType'])->toBe('AvailabilityData');
    expect($queue[0]['data']['baseData']['name'])->toBe('Health Check');
    expect($queue[0]['data']['baseData']['success'])->toBeTrue();
    expect($queue[0]['data']['baseData']['runLocation'])->toBe('East US');
    expect($queue[0]['data']['baseData']['message'])->toBe('All OK');
});

it('tracks dependencies with result code and data', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackDependency(
        'HTTP',
        'api.example.com',
        'GET /users',
        250.0,
        true,
        '200',
        'https://api.example.com/users?page=1'
    );

    $queue = $client->getQueue();
    expect($queue[0]['data']['baseData']['resultCode'])->toBe('200');
    expect($queue[0]['data']['baseData']['data'])->toBe('https://api.example.com/users?page=1');
});

it('includes id field in page views', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackPageView('Home Page', 'https://example.com/');

    $queue = $client->getQueue();
    expect($queue[0]['data']['baseData'])->toHaveKey('id');
    expect(strlen($queue[0]['data']['baseData']['id']))->toBe(16);
});

// ==============================
// METRIC AGGREGATION TESTS
// ==============================

it('tracks simple measurement metric', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackMetric('ResponseTime', 150.5);

    $queue = $client->getQueue();
    $metric = $queue[0]['data']['baseData']['metrics'][0];

    expect($metric['name'])->toBe('ResponseTime');
    expect($metric['value'])->toBe(150.5);
    expect($metric['kind'])->toBe(0);  // Measurement
    expect($metric['count'])->toBe(1);
});

it('tracks aggregated metric with count min max stdDev', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackMetric(
        name: 'ProcessingTime',
        value: 2500.0,  // Sum of 100 samples
        count: 100,
        min: 10.0,
        max: 150.0,
        stdDev: 25.5
    );

    $queue = $client->getQueue();
    $metric = $queue[0]['data']['baseData']['metrics'][0];

    expect($metric['name'])->toBe('ProcessingTime');
    expect($metric['value'])->toBe(2500.0);
    expect($metric['kind'])->toBe(1);  // Aggregation
    expect($metric['count'])->toBe(100);
    expect($metric['min'])->toBe(10.0);
    expect($metric['max'])->toBe(150.0);
    expect($metric['stdDev'])->toBe(25.5);
});

it('tracks metric with namespace', function () {
    $client = new Telemetry_Client();
    $client->setInstrumentationKey('test-key');

    $client->trackMetric(
        name: 'CacheHitRate',
        value: 0.85,
        namespace: 'MyApp/Cache'
    );

    $queue = $client->getQueue();
    $metric = $queue[0]['data']['baseData']['metrics'][0];

    expect($metric['name'])->toBe('CacheHitRate');
    expect($metric['ns'])->toBe('MyApp/Cache');
});
