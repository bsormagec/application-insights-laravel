<?php

use Illuminate\Support\Facades\Route;
use Sormagec\AppInsightsLaravel\Middleware\AppInsightsWebMiddleware;
use Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade;

it('tracks requests via middleware', function () {
    Route::get('/test-middleware', function () {
        return response('ok');
    })->middleware(AppInsightsWebMiddleware::class);

    $response = $this->get('/test-middleware');

    $response->assertStatus(200);

    // Check if something was added to the queue
    $server = app('AppInsightsServer');
    expect($server->getQueue())->not->toBeEmpty();
});

it('excludes routes from tracking', function () {
    config(['appinsights-laravel.excluded_paths' => ['excluded/*']]);

    Route::get('/excluded/test', function () {
        return response('ok');
    })->middleware(AppInsightsWebMiddleware::class);

    $server = app('AppInsightsServer');
    $initialCount = count($server->getQueue());

    $response = $this->get('/excluded/test');

    $response->assertStatus(200);
    expect(count($server->getQueue()))->toBe($initialCount);
});

it('works with API middleware', function () {
    Route::get('/api-test', function () {
        return response('ok');
    })->middleware(\Sormagec\AppInsightsLaravel\Middleware\AppInsightsApiMiddleware::class);

    $response = $this->get('/api-test');

    $response->assertStatus(200);
    $server = app('AppInsightsServer');
    expect($server->getQueue())->not->toBeEmpty();
});
