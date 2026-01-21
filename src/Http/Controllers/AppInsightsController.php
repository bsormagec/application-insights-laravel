<?php

namespace Sormagec\AppInsightsLaravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sormagec\AppInsightsLaravel\Queue\AppInsightsTelemetryQueue;
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

    /**
     * Collect telemetry from browser and queue for processing.
     * Returns immediately with 200 OK - no blocking.
     */
    public function collect(Request $request)
    {
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

        // Support batching: if multiple telemetry items are sent at once
        $items = isset($payload[0]) && is_array($payload) ? $payload : [$payload];

        if (!config('appinsights-laravel.client.enabled', true)) {
            return response()->json(['status' => 'ok']);
        }

        // Limit batch size to prevent abuse
        if (count($items) > self::MAX_BATCH_SIZE) {
            $items = array_slice($items, 0, self::MAX_BATCH_SIZE);
        }

        // Add request context to each item
        $context = [
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        foreach ($items as &$item) {
            $item['_context'] = $context;
        }

        // Dispatch AFTER response is sent to client (non-blocking)
        AppInsightsTelemetryQueue::dispatchAfterResponse($items)->onQueue('appinsights-queue');

        // Get instrumentation key for Application Map correlation
        // Request-Context header tells browser the appId, which browser includes in dependency target
        // This is how Azure links browser HTTP deps to server instance
        $appId = config('appinsights-laravel.application_id');
        if (!$appId) {
            $appId = config('appinsights-laravel.instrumentation_key', '');
        }

        $response = response()->json(['status' => 'ok']);
        if ($appId) {
            $response->header('Request-Context', 'appId=cid-v1:' . $appId)
                ->header('Access-Control-Expose-Headers', 'Request-Context');
        }

        return $response;
    }
}
