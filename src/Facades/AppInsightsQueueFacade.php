<?php
namespace Sormagec\AppInsightsLaravel\Facades;

use Illuminate\Support\Facades\Facade;

class AppInsightsQueueFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \Sormagec\AppInsightsLaravel\Queue\AppInsightsTelemetryQueue::class;
    }
}