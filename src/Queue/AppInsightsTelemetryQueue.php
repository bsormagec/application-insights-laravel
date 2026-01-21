<?php

namespace Sormagec\AppInsightsLaravel\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade as AIServer;
use Sormagec\AppInsightsLaravel\Support\Config;

class AppInsightsTelemetryQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The telemetry items to process
     */
    protected array $items;

    /**
     * Create a new job instance.
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Execute the job - process telemetry items and send to Azure.
     */
    public function handle(): void
    {
        if (empty($this->items)) {
            return;
        }

        try {
            foreach ($this->items as $item) {
                $this->processItem($item);
            }

            // Flush all buffered telemetry to Azure
            AIServer::flush();
        } catch (\Throwable $e) {
            Log::error('AppInsights queue job failed: ' . $e->getMessage(), [
                'exception' => $e,
                'items_count' => count($this->items),
            ]);
        }
    }

    /**
     * Process a single telemetry item
     */
    protected function processItem(array $item): void
    {
        $type = $item['type'] ?? null;

        // Skip items without a valid type
        if (empty($type)) {
            return;
        }

        if (!Config::get('client.enabled', true)) {
            return;
        }

        if ($type === 'dependency' && !Config::get('client.track_dependencies', true)) {
            return;
        }

        $context = $item['_context'] ?? [];

        // Add context tags
        if (!empty($context['client_ip'])) {
            AIServer::addContextTag('ai.location.ip', $context['client_ip']);
        }
        if (!empty($context['user_agent'])) {
            AIServer::addContextTag('ai.user.userAgent', $context['user_agent']);
        }

        // Forward operation context from client for correlation
        $properties = $item['properties'] ?? [];
        if (!empty($properties['operationId'])) {
            AIServer::addContextTag('ai.operation.id', $properties['operationId']);
        }
        if (!empty($properties['parentId'])) {
            AIServer::addContextTag('ai.operation.parentId', $properties['parentId']);
        }

        match ($type) {
            'exception' => AIServer::trackExceptionFromArray([
                'message' => $item['error']['message'] ?? 'Unknown JS error',
                'stack' => $item['error']['stack'] ?? null,
                'properties' => array_merge(
                    $item['properties'] ?? [],
                    $item['error']['properties'] ?? [],
                    [
                        'filename' => $item['error']['filename'] ?? null,
                        'lineno' => $item['error']['lineno'] ?? null,
                        'colno' => $item['error']['colno'] ?? null,
                    ]
                ),
            ]),
            'event' => AIServer::trackEvent($item['name'], $item['properties'] ?? []),
            'pageView' => AIServer::trackPageView(
                $item['name'] ?? 'Unknown Page',
                $item['url'] ?? '',
                // Duration: prefer explicit, fallback to measurements.pageLoadTime
                isset($item['duration'])
                ? (float) $item['duration']
                : (isset($item['measurements']['pageLoadTime']) ? (float) $item['measurements']['pageLoadTime'] : null),
                $item['referredUri'] ?? null,
                $item['properties'] ?? [],
                $item['measurements'] ?? []
            ),
            'metric' => AIServer::trackMetric(
                $item['name'] ?? 'Unknown Metric',
                (float) ($item['value'] ?? 0),
                null,  // count
                null,  // min
                null,  // max
                null,  // stdDev
                null,  // namespace
                $item['properties'] ?? []
            ),
            'browserTimings' => AIServer::trackBrowserTimings(
                $item['name'] ?? 'Page View',
                $item['url'] ?? '',
                $item['measurements'] ?? [],
                $item['properties'] ?? []
            ),
            'dependency' => AIServer::trackDependency(
                'HTTP',
                parse_url($item['url'] ?? '', PHP_URL_HOST) ?: 'unknown',
                $item['name'] ?? 'HTTP Request',
                (float) ($item['duration'] ?? 0),
                $item['success'] ?? true,
                isset($item['responseCode']) ? (string) $item['responseCode'] : null,  // resultCode
                $item['url'] ?? null,  // data
                $item['properties'] ?? [],  // properties
                [],  // measurements
                true,  // isBrowser - these are browser-originated AJAX/fetch dependencies
                // NOTE: Do NOT add cid-v1 to target - Azure correlates via operation_Id, not target appId
                // Adding cid-v1 actually breaks the Application Map visualization
                null
            ),
            default => Log::warning('Unknown telemetry type in queue', ['type' => $type]),
        };
    }
}
