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
                        'message'    => $item['error']['message'] ?? 'Unknown JS error',
                        'stack'      => $item['error']['stack'] ?? null,
                        'properties' => array_merge(
                            $item['error']['properties'] ?? [],
                            [
                                'filename' => $item['error']['filename'] ?? null,
                                'lineno'   => $item['error']['lineno'] ?? null,
                                'colno'    => $item['error']['colno'] ?? null,
                            ]
                        ),
                    ]);
                    $this->flush();
                } elseif ($type === 'event') {
                    $server->trackEventFromArray([
                        'name' => is_string($item['name']) ? $item['name'] : json_encode($item['name']) ?? '',
                        'properties' => $item['properties'] ?? []
                    ]);
                    $this->flush();
                } else {
                    Logger::warning('Unknown telemetry type received', ['payload' => $item]);
                }
            }

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
        if($queue_seconds)
        {
            /** @disregard Undefined type 'AIServer' */
            \AIQueue::dispatch(\AIServer::getQueue())
            ->onQueue('appinsights-queue')
            ->delay(Carbon::now()->addSeconds($queue_seconds));
        }
        else
        {
            try 
            {  
                /** @disregard Undefined type 'AIServer' */
               \AIServer::flush();
            }
            catch(\Exception $e)
            {
                Logger::debug('Exception: Could not flush AIServer server. Error:'.$e->getMessage());
            }
        }
    }
}
