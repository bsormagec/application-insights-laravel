<?php

namespace Sormagec\AppInsightsLaravel\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Sormagec\AppInsightsLaravel\AppInsightsServer;
use Sormagec\AppInsightsLaravel\Support\Config;
use Sormagec\AppInsightsLaravel\Support\Logger;

class LogSlowQuery
{
    public function __construct(
        protected AppInsightsServer $appInsights
    ) {}

    /**
     * Handle the QueryExecuted event.
     * Only logs queries that exceed the configured threshold.
     */
    public function handle(QueryExecuted $event): void
    {
        try {
            if (!Config::get('features.db', true)) {
                return;
            }

            $threshold = (float) Config::get('db_slow_ms', 500);
            $durationMs = $event->time;

            // Only track slow queries
            if ($durationMs < $threshold) {
                return;
            }

            $this->appInsights->trackDbQuery(
                $event->sql,
                $durationMs,
                [
                    'db.connection' => $event->connectionName,
                    'db.bindings_count' => count($event->bindings),
                ]
            );

        } catch (\Throwable $e) {
            Logger::error('LogSlowQuery listener error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
