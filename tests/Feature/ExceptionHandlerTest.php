<?php

use Sormagec\AppInsightsLaravel\Handlers\AppInsightsExceptionHandler;
use Sormagec\AppInsightsLaravel\AppInsightsHelpers;
use Illuminate\Container\Container;

class MockExceptionHandler extends AppInsightsExceptionHandler
{
    public $reportedToAppInsights = false;

    public function report(Throwable $e)
    {
        $helpers = $this->getAppInsightsHelpers();
        if ($helpers) {
            $helpers->trackException($e);
            $this->reportedToAppInsights = true;
        }
    }
}

it('reports exceptions to AppInsights via the handler', function () {
    $container = new Container();
    $helpers = Mockery::mock(AppInsightsHelpers::class);
    $helpers->shouldReceive('trackException')->once();

    $container->instance(AppInsightsHelpers::class, $helpers);

    $handler = new MockExceptionHandler($container);
    $handler->report(new Exception('Test Exception'));

    expect($handler->reportedToAppInsights)->toBeTrue();
});
