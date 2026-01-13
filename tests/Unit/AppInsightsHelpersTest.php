<?php

use Sormagec\AppInsightsLaravel\AppInsightsHelpers;
use Sormagec\AppInsightsLaravel\AppInsightsServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

it('formats time correctly', function () {
    $helpers = app(AppInsightsHelpers::class);

    // Using reflection to test private method
    $reflection = new ReflectionClass($helpers);
    $method = $reflection->getMethod('formatTime');
    $method->setAccessible(true);

    expect($method->invoke($helpers, 0.5))->toBe('0.50 seconds');
    expect($method->invoke($helpers, 1.5))->toBe('1.50 seconds');
    expect($method->invoke($helpers, 65.5))->toBe('1:05.50');
    expect($method->invoke($helpers, 3665.5))->toBe('1:01:05.50');
});

it('gets request name correctly based on priority', function () {
    $helpers = app(AppInsightsHelpers::class);
    $reflection = new ReflectionClass($helpers);
    $method = $reflection->getMethod('getRequestName');
    $method->setAccessible(true);

    // 1. Route Name
    $request = Request::create('/test');
    $request->setRouteResolver(function () {
        return Mockery::mock(\Illuminate\Routing\Route::class)
            ->shouldReceive('getName')->andReturn('test.route')
            ->getMock();
    });
    expect($method->invoke($helpers, $request))->toBe('test.route');

    // 2. Route URI Pattern
    $request = Request::create('/test/123');
    $request->setMethod('GET');
    $request->setRouteResolver(function () {
        return Mockery::mock(\Illuminate\Routing\Route::class)
            ->shouldReceive('getName')->andReturn(null)
            ->shouldReceive('uri')->andReturn('test/{id}')
            ->getMock();
    });
    expect($method->invoke($helpers, $request))->toBe('GET /test/{id}');

    // 3. Fallback to Method + Path
    $request = Request::create('/test/path');
    $request->setMethod('POST');
    $request->setRouteResolver(function () {
        return null;
    });
    expect($method->invoke($helpers, $request))->toBe('POST test/path');
});

it('tracks exceptions', function () {
    $helpers = app(AppInsightsHelpers::class);
    $server = app('AppInsightsServer');
    $initialCount = count($server->getQueue());

    $helpers->trackException(new \Exception('Test Exception'));

    $queue = $server->getQueue();
    expect(count($queue))->toBeGreaterThan($initialCount);
    $lastItem = end($queue);
    expect($lastItem['data']['baseData']['exceptions'][0]['message'])->toBe('Test Exception');
});

it('flashes and tracks page view info', function () {
    $helpers = app(AppInsightsHelpers::class);
    $request = Request::create('/page1');

    // Mock session
    $request->setLaravelSession(app('session')->driver('array'));

    $helpers->flashPageInfo($request);
    expect($request->session()->has('ms_application_insights_page_info'))->toBeTrue();

    // Simulate next request
    $request2 = Request::create('/page2');
    $request2->setLaravelSession($request->session());

    $server = app('AppInsightsServer');
    $initialCount = count($server->getQueue());

    $helpers->trackPageViewDuration($request2);

    expect(count($server->getQueue()))->toBeGreaterThan($initialCount);
});
