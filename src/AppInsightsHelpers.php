<?php

namespace Sormagec\AppInsightsLaravel;

use Exception;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade as AIServer;
use Sormagec\AppInsightsLaravel\Facades\AppInsightsQueueFacade as AIQueue;
use Sormagec\AppInsightsLaravel\Support\Config;
use Sormagec\AppInsightsLaravel\Support\PathExclusionTrait;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppInsightsHelpers
{
    use PathExclusionTrait;

    /**
     * @var AppInsightsServer
     */
    private $appInsights;


    public function __construct(AppInsightsServer $appInsights)
    {
        $this->appInsights = $appInsights;
    }

    /**
     * Track a page view
     *
     * @param $request
     * @return void
     */
    public function trackPageViewDuration($request)
    {
        if (!$this->telemetryEnabled()) {
            return;
        }

        // Skip excluded paths
        if ($this->shouldExcludeRequest($request)) {
            return;
        }

        if (!$request->session()->has('ms_application_insights_page_info')) {
            return;
        }

        $properties = $this->getPageViewProperties($request);
        AIServer::trackMessage('browse_duration', $properties);
        $this->flush();

    }


    /**
     * Track application performance
     *
     * @param $request
     * @param $response
     */
    public function trackRequest($request, $response)
    {
        if (!$this->telemetryEnabled()) {
            return;
        }
        if (!$this->appInsights->telemetryClient) {
            return;
        }

        // Skip excluded paths
        if ($this->shouldExcludeRequest($request)) {
            return;
        }

        // Add Context Tags (IP, User Agent)
        $this->appInsights->addContextTag('ai.location.ip', $request->ip());
        $this->appInsights->addContextTag('ai.user.userAgent', $request->userAgent());

        $properties = $this->getRequestProperties($request);
        AIServer::trackRequest(
            $this->getRequestName($request),
            $request->fullUrl(),
            $this->getRequestDuration(),
            $this->getResponseCode($response),
            $this->isSuccessful($response),
            $properties,
            $this->getRequestMeasurements($request, $response)
        );
        $this->flush();
    }

    /**
     * Track application exceptions
     *
     * @param Exception $e
     */
    public function trackException(Throwable $e)
    {
        if (!$this->telemetryEnabled()) {
            return;
        }
        AIServer::trackException($e, $this->getRequestPropertiesFromException($e) ?? []);
        $this->flush();
    }

    /**
     * flushes the telemery queue, will wait for the time provided in config
     * if time was not set in config then it wil flush immediately
     */
    private function flush()
    {
        $queue_seconds = $this->appInsights->getFlushQueueAfterSeconds();
        if ($queue_seconds) {
            AIQueue::dispatch(AIServer::getQueue())
                ->onQueue('appinsights-queue')
                ->delay(Carbon::now()->addSeconds($queue_seconds));
        } else {
            try {
                AIServer::flush();
            } catch (Exception $e) {
                Log::debug('Exception: Could not flush AIServer server. Error:' . $e->getMessage());
            }
        }
    }

    /**
     * Get request properties from the exception trace, if available
     *
     * @param Exception $e
     *
     * @return array|null
     */
    private function getRequestPropertiesFromException(Throwable $e)
    {
        foreach ($e->getTrace() as $item) {
            if (isset($item['args'])) {
                foreach ($item['args'] as $arg) {

                    /** @disregard Undefined type 'Request' */
                    if ($arg instanceof Request) {
                        return $this->getRequestProperties($arg);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Flash page info for use in following page request
     *
     * @param $request
     */
    public function flashPageInfo($request)
    {
        if (!$this->telemetryEnabled()) {
            return;
        }

        // Skip excluded paths
        if ($this->shouldExcludeRequest($request)) {
            return;
        }

        $request->session()->flash('ms_application_insights_page_info', [
            'url' => $request->fullUrl(),
            'load_time' => microtime(true),
            'properties' => $this->getRequestProperties($request)
        ]);

    }

    /**
     * Determines whether the Telemetry Client is enabled
     *
     * @return bool
     */
    private function telemetryEnabled()
    {
        return isset($this->appInsights->telemetryClient);
    }


    /**
     * Get properties from the Laravel request
     *
     * @param $request
     *
     * @return array|null
     */
    private function getRequestProperties($request)
    {
        $properties = [
            'ajax' => $request->ajax(),
            'fullUrl' => $request->fullUrl(),
            'ip' => $request->ip(),
            'pjax' => $request->pjax(),
            'secure' => $request->secure(),
            'method' => $request->method(),
            'path' => $request->path(),
        ];

        if ($request->route()) {
            // Add route name if available
            if ($request->route()->getName()) {
                $properties['route_name'] = $request->route()->getName();
            }
            
            // Add route pattern (URI template)
            if ($request->route()->uri()) {
                $properties['route_pattern'] = $request->route()->uri();
            }
            
            // Add controller action
            $action = $request->route()->getActionName();
            if ($action && $action !== 'Closure') {
                $properties['route_action'] = $action;
            }
            
            // Keep backward compatibility with 'route' key
            $properties['route'] = $request->route()->getName() ?? $request->route()->uri();
        }

        if ($request->user()) {
            $properties['user'] = $request->user()->id;
        }

        if ($request->server('HTTP_REFERER')) {
            $properties['referer'] = $request->server('HTTP_REFERER');
        }

        return $properties;
    }

    /**
     * Get a meaningful name for the request to send to Azure Insights
     * Priority: Route Name > Route Pattern > Controller@Action > Method + Path
     *
     * @param $request
     * @return string
     */
    private function getRequestName($request): string
    {
        // Priority 1: Use route name if available (e.g., "users.index")
        if ($request->route() && $request->route()->getName()) {
            return $request->route()->getName();
        }
        
        $method = $request->method();
        
        // Priority 2: Use route URI pattern (e.g., "GET /api/users/{id}")
        if ($request->route() && $request->route()->uri()) {
            return $method . ' /' . $request->route()->uri();
        }
        
        // Priority 3: Use Controller@action (e.g., "GET UserController@show")
        if ($request->route()) {
            $action = $request->route()->getActionName();
            if ($action && $action !== 'Closure') {
                // Extract just ControllerName@method from full namespace
                $parts = explode('@', $action);
                if (count($parts) === 2) {
                    $controllerParts = explode('\\', $parts[0]);
                    $controllerName = end($controllerParts);
                    return $method . ' ' . $controllerName . '@' . $parts[1];
                }
            }
        }
        
        // Priority 4: Fallback to method + path
        return $method . ' ' . $request->path();
    }


    /**
     * Doesn't do a lot right now!
     *
     * @param $request
     * @param $response
     *
     * @return array|null
     */
    private function getRequestMeasurements($request, $response)
    {
        $measurements = [];

        return (!empty($measurements)) ? $measurements : null;
    }


    /**
     * Estimate the time spent viewing the previous page
     *
     * @param $loadTime
     *
     * @return mixed
     */
    private function getPageViewDuration($loadTime)
    {
        return round(($_SERVER['REQUEST_TIME_FLOAT'] - $loadTime), 2);
    }

    /**
     * Calculate the time spent processing the request
     *
     * @return mixed
     */
    private function getRequestDuration()
    {
        return (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
    }


    /**
     * Determine if the request was successful
     *
     * @param $response
     *
     * @return bool
     */
    private function isSuccessful($response)
    {
        return ($this->getResponseCode($response) < 400);
    }


    /**
     * Get additional properties for page view at the end of the request
     *
     * @param $request
     *
     * @return mixed
     */
    private function getPageViewProperties($request)
    {
        $pageInfo = $request->session()->get('ms_application_insights_page_info');

        $properties = $pageInfo['properties'];

        $properties['url'] = $pageInfo['url'];
        $properties['duration'] = $this->getPageViewDuration($pageInfo['load_time']);
        $properties['duration_formatted'] = $this->formatTime($properties['duration']);

        return $properties;
    }


    /**
     * Formats time strings into a human-readable format
     *
     * @param $duration
     *
     * @return string
     */
    private function formatTime($duration)
    {
        $milliseconds = str_pad((round($duration - floor($duration), 2) * 100), 2, '0', STR_PAD_LEFT);

        if ($duration < 1) {
            return "0.{$milliseconds} seconds";
        }

        $seconds = floor($duration % 60);

        if ($duration < 60) {
            return "{$seconds}.{$milliseconds} seconds";
        }

        $string = str_pad($seconds, 2, '0', STR_PAD_LEFT) . '.' . $milliseconds;

        $minutes = floor(($duration % 3600) / 60);

        if ($duration < 3600) {
            return "{$minutes}:{$string}";
        }

        $string = str_pad($minutes, 2, '0', STR_PAD_LEFT) . ':' . $string;

        $hours = floor(($duration % 86400) / 3600);

        if ($duration < 86400) {
            return "{$hours}:{$string}";
        }

        $string = str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . $string;

        $days = floor($duration / 86400);

        return $days . ':' . $string;
    }

    /**
     * If you use stream() or streamDownload() then the response object isn't a standard one. Here we check different
     * places for the status code depending on the object that Laravel sends us.
     *
     * @param StreamedResponse|BinaryFileResponse|Response $response The response object
     *
     * @return int The HTTP status code
     */
    private function getResponseCode($response): int
    {
        // All Symfony response types (Response, StreamedResponse, BinaryFileResponse) 
        // inherit from Symfony\Component\HttpFoundation\Response which has getStatusCode()
        return $response->getStatusCode();
    }
}
