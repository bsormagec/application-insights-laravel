<?php

namespace Sormagec\AppInsightsLaravel\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Sormagec\AppInsightsLaravel\AppInsightsServer;
use Sormagec\AppInsightsLaravel\Support\Config;
use Sormagec\AppInsightsLaravel\Support\Logger;

class LogJobFailed
{
    public function __construct(
        protected AppInsightsServer $appInsights
    ) {}

    /**
     * Handle the JobFailed event.
     * Tracks failed queue jobs as exceptions in Application Insights.
     */
    public function handle(JobFailed $event): void
    {
        try {
            if (!Config::get('features.jobs', true)) {
                return;
            }

            $this->appInsights->trackException($event->exception, [
                'job.name' => $event->job->resolveName(),
                'job.queue' => $event->job->getQueue(),
                'job.connection' => $event->connectionName,
                'job.payload' => $this->getSafePayload($event),
            ]);

        } catch (\Throwable $e) {
            Logger::error('LogJobFailed listener error: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Get a safe payload representation (limited size)
     */
    private function getSafePayload(JobFailed $event): string
    {
        try {
            $payload = json_encode($event->job->payload());
            // Limit payload size to prevent large telemetry
            return strlen($payload) > 1000 ? substr($payload, 0, 1000) . '...' : $payload;
        } catch (\Throwable $e) {
            return 'Unable to serialize payload';
        }
    }
}
