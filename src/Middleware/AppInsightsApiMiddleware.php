<?php
namespace Sormagec\AppInsightsLaravel\Middleware;

use Closure;
use Sormagec\AppInsightsLaravel\AppInsightsHelpers;
use Sormagec\AppInsightsLaravel\Support\Logger;

class AppInsightsApiMiddleware
{

    /**
     * @var AppInsightsHelpers
     */
    private AppInsightsHelpers $appInsightsHelpers;


    /**
     * @param AppInsightsHelpers $appInsightssHelpers
     */
    public function __construct(AppInsightsHelpers $appInsightsHelpers)
    {
        $this->appInsightsHelpers = $appInsightsHelpers;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Parse distributed tracing headers from browser AJAX requests
        // This links browser HTTP deps to server requests in Application Map
        try {
            $this->propagateTraceContext($request);
        } catch (\Throwable $e) {
            Logger::error('AppInsightsApiMiddleware trace context error: ' . $e->getMessage(), ['exception' => $e]);
        }

        $response = $next($request);

        try {
                $appId = config('appinsights-laravel.application_id');
                if (!$appId) {
                    $appId = config('appinsights-laravel.instrumentation_key', '');
                }

                if ($appId && method_exists($response, 'header')) {
                    $response->header('Request-Context', 'appId=cid-v1:' . $appId);

                    $route = $request->route();
                    if ($route) {
                        $routePattern = $route->uri();
                        if ($routePattern) {
                            $response->header('X-AI-Route-Pattern', '/' . ltrim($routePattern, '/'));
                        }

                        $routeName = $route->getName();
                        if ($routeName) {
                            $response->header('X-AI-Route-Name', $routeName);
                        }
                    }

                    $existingExpose = '';
                    if (isset($response->headers)) {
                        $existingExpose = (string) $response->headers->get('Access-Control-Expose-Headers', '');
                    }

                    $exposeHeaders = array_filter(array_map('trim', explode(',', $existingExpose)));
                    foreach (['Request-Context', 'X-AI-Route-Pattern', 'X-AI-Route-Name'] as $headerName) {
                        if (!in_array($headerName, $exposeHeaders, true)) {
                            $exposeHeaders[] = $headerName;
                        }
                    }
                    $response->header('Access-Control-Expose-Headers', implode(', ', $exposeHeaders));
                }
        } catch (\Throwable $e) {
            // Silently fail to not break the response
        }

        return $response;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\Response $response
     * @return void
     */
    public function terminate($request, $response)
    {
        try {
            $this->appInsightsHelpers->trackRequest($request, $response);
        } catch (\Throwable $e) {
            Logger::error('AppInsightsApiMiddleware telemetry error: ' . $e->getMessage(), ['exception' => $e]);
            // Optionally cache or handle error here
        }
    }

    /**
     * Parse distributed tracing headers from incoming AJAX requests and propagate
     * the trace context to server-side telemetry. This creates the link in Application Map
     * between browser HTTP dependencies and server requests.
     *
     * Supports both W3C traceparent and AI Request-Id formats.
     *
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    private function propagateTraceContext($request): void
    {
        // Try W3C traceparent header first: 00-traceId-spanId-flags
        $traceparent = $request->header('traceparent');
        if ($traceparent) {
            $parts = explode('-', $traceparent);
            if (count($parts) >= 3) {
                $traceId = $parts[1];
                $parentSpanId = $parts[2];

                // Set operation context on the telemetry client
                \Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade::addContextTag(
                    'ai.operation.id',
                    $traceId
                );
                \Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade::addContextTag(
                    'ai.operation.parentId',
                    $parentSpanId
                );
                return;
            }
        }

        // Fallback to AI Request-Id header: |traceId.spanId
        $requestId = $request->header('Request-Id');
        if ($requestId && str_starts_with($requestId, '|')) {
            $requestId = substr($requestId, 1); // Remove leading |
            $parts = explode('.', $requestId);
            if (count($parts) >= 2) {
                $traceId = $parts[0];
                $spanId = $parts[1];

                \Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade::addContextTag(
                    'ai.operation.id',
                    $traceId
                );
                \Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade::addContextTag(
                    'ai.operation.parentId',
                    $spanId
                );
            }
        }
    }

}