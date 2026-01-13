<?php

use Sormagec\AppInsightsLaravel\Support\PathExclusionTrait;
use Illuminate\Http\Request;

class TestTrait
{
    use PathExclusionTrait;

    public function testShouldExclude($request)
    {
        return $this->shouldExcludeRequest($request);
    }
}

it('matches paths correctly with wildcards', function () {
    $trait = new TestTrait();

    // Using reflection to test protected method
    $reflection = new ReflectionClass($trait);
    $method = $reflection->getMethod('pathMatchesPattern');
    $method->setAccessible(true);

    expect($method->invoke($trait, 'foo/bar', 'foo/*'))->toBeTrue();
    expect($method->invoke($trait, 'foo/bar', 'foo/bar'))->toBeTrue();
    expect($method->invoke($trait, 'foo/baz', 'foo/bar'))->toBeFalse();
    expect($method->invoke($trait, 'api/v1/users', 'api/*/users'))->toBeTrue();
    expect($method->invoke($trait, 'health', 'health'))->toBeTrue();
});

it('excludes requests based on config', function () {
    config(['appinsights-laravel.excluded_paths' => ['exclude-me/*']]);

    $trait = new TestTrait();

    $request1 = Request::create('/exclude-me/something');
    $request2 = Request::create('/keep-me');

    expect($trait->testShouldExclude($request1))->toBeTrue();
    expect($trait->testShouldExclude($request2))->toBeFalse();
});
