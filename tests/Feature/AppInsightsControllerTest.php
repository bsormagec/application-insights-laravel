<?php

use Illuminate\Support\Facades\Config;
use Sormagec\AppInsightsLaravel\AppInsightsServer;
use Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade;

it('can collect telemetry via the controller', function () {
    $payload = [
        'type' => 'event',
        'name' => 'Test Event',
        'properties' => ['foo' => 'bar']
    ];

    $response = $this->postJson('/appinsights/collect', $payload);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});

it('rejects empty payload', function () {
    $response = $this->postJson('/appinsights/collect', []);

    $response->assertStatus(400);
    $response->assertJson(['status' => 'error', 'message' => 'Empty payload']);
});

it('rejects large payload', function () {
    $largePayload = str_repeat('a', 102401);

    $response = $this->postJson('/appinsights/collect', [], [
        'Content-Length' => 102401
    ]);

    $response->assertStatus(413);
    $response->assertJson(['status' => 'error', 'message' => 'Payload too large']);
});

it('handles batched items', function () {
    $payload = [
        [
            'type' => 'event',
            'name' => 'Event 1'
        ],
        [
            'type' => 'pageView',
            'name' => 'Page 1',
            'url' => 'https://example.com'
        ]
    ];

    $response = $this->postJson('/appinsights/collect', $payload);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});

it('truncates batch size if exceeded', function () {
    $payload = array_fill(0, 60, [
        'type' => 'event',
        'name' => 'Event'
    ]);

    $response = $this->postJson('/appinsights/collect', $payload);

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});
