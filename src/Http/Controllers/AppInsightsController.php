<?php

namespace Sormagec\AppInsightsLaravel\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sormagec\AppInsightsLaravel\AppInsightsServer;
use Sormagec\AppInsightsLaravel\Support\Logger;

class AppInsightsController extends Controller
{
    /**
     * Maximum payload size in bytes (100KB)
     */
    private const MAX_PAYLOAD_SIZE = 102400;

    /**
     * Maximum number of items per batch
     */
    private const MAX_BATCH_SIZE = 50;

    public function collect(Request $request)
    {
        try {
            // Validate payload size to prevent abuse
            $contentLength = $request->header('Content-Length', 0);
            if ($contentLength > self::MAX_PAYLOAD_SIZE) {
                return response()->json(['status' => 'error', 'message' => 'Payload too large'], 413);
            }

            $payload = $request->all();

            // Validate payload is not empty
            if (empty($payload)) {
                return response()->json(['status' => 'error', 'message' => 'Empty payload'], 400);
            }

            /** @var AppInsightsServer $server */
            $server = app('AppInsightsServer');

            // Add Client IP and UA to context
            $server->addContextTag('ai.location.ip', $request->ip());
            $server->addContextTag('ai.user.userAgent', $request->userAgent());

            // Support batching: if multiple telemetry items are sent at once
            $items = isset($payload[0]) && is_array($payload) ? $payload : [$payload];

            // Limit batch size to prevent abuse
            if (count($items) > self::MAX_BATCH_SIZE) {
                $items = array_slice($items, 0, self::MAX_BATCH_SIZE);
                Logger::warning('Batch size exceeded limit, truncated to ' . self::MAX_BATCH_SIZE . ' items');
            }

            foreach ($items as $item) {
                $type = $item['type'] ?? null;

                if ($type === 'exception') {
                    $server->trackExceptionFromArray([
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
                    ]);
                } elseif ($type === 'event') {
                    $server->trackEvent($item['name'], $item['properties'] ?? []);
                } elseif ($type === 'pageView') {
                    $server->trackPageView(
                        $item['name'] ?? 'Unknown Page',
                        $item['url'] ?? '',
                        $item['properties'] ?? [],
                        $item['measurements'] ?? []
                    );
                } elseif ($type === 'metric') {
                    $server->trackMetric(
                        $item['name'] ?? 'Unknown Metric',
                        (float) ($item['value'] ?? 0),
                        $item['properties'] ?? []
                    );
                } elseif ($type === 'browserTimings') {
                    $server->trackBrowserTimings(
                        $item['name'] ?? 'Page View',
                        $item['url'] ?? '',
                        $item['measurements'] ?? [],
                        $item['properties'] ?? []
                    );
                } elseif ($type === 'dependency') {
                    $server->trackDependency(
                        'HTTP',
                        parse_url($item['url'] ?? '', PHP_URL_HOST) ?: 'unknown',
                        $item['name'] ?? 'HTTP Request',
                        (float) ($item['duration'] ?? 0),
                        $item['success'] ?? true,
                        array_merge($item['properties'] ?? [], [
                            'responseCode' => $item['responseCode'] ?? 0,
                            'url' => $item['url'] ?? ''
                        ])
                    );
                } else {
                    Logger::warning('Unknown telemetry type received', ['payload' => $item]);
                }
            }

            $this->flush();

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Logger::error('Telemetry backend error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function flush()
    {
        $server = app('AppInsightsServer');
        $queue_seconds = $server->getFlushQueueAfterSeconds();
        if ($queue_seconds) {
            /** @disregard Undefined type 'AIServer' */
            \AIQueue::dispatch(\AIServer::getQueue())
                ->onQueue('appinsights-queue')
                ->delay(Carbon::now()->addSeconds($queue_seconds));
        } else {
            try {
                /** @disregard Undefined type 'AIServer' */
                \AIServer::flush();
            } catch (\Exception $e) {
                Logger::debug('Exception: Could not flush AIServer server. Error:' . $e->getMessage());
            }
        }
    }
}
