<?php
namespace Sormagec\AppInsightsLaravel;

use Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade as AIServer;
use Illuminate\Support\Facades\Route;

class AppInsightsClient extends InstrumentationKey
{
    /**
     * Generate JavaScript snippet for client-side telemetry with operation correlation
     * 
     * @return string
     */
    public function javascript()
    {
        $clientEnabled = (bool) config('appinsights-laravel.client.enabled', true);
        if (!$clientEnabled) {
            return '';
        }

        // Use relative path to avoid Mixed Content issues (HTTP vs HTTPS mismatches)
        $endpoint = '/appinsights/collect';
        $endpointJson = json_encode($endpoint);
        $jsAsset = asset('vendor/app-insights-laravel/js/appinsights-client.min.js');

        // Get operation context from server for correlation
        $operationId = '';
        $parentId = '';
        $operationName = '';

        try {
            /** @var \Sormagec\AppInsightsLaravel\AppInsightsServer $server */
            $server = app('AppInsightsServer');
            if ($server && $server->telemetryClient) {
                $operationId = $server->telemetryClient->getOperationId() ?? '';
                $parentId = $server->telemetryClient->generateSpanId();
            }

            // Get route name or action for operation name correlation
            $currentRoute = Route::current();
            if ($currentRoute) {
                // Prefer route name, fall back to action name
                $operationName = $currentRoute->getName()
                    ?? $currentRoute->getActionName()
                    ?? '';

                // Clean up controller action format (e.g., "App\Http\Controllers\HomeController@index" -> "HomeController@index")
                if (str_contains($operationName, '@')) {
                    $operationName = class_basename($operationName);
                }
            }
        } catch (\Throwable $e) {
            // Silently fail if server not available
        }

        // Get excluded paths for client-side filtering
        $excludedPaths = config('appinsights-laravel.excluded_paths', []);
        $excludedPathsJson = json_encode($excludedPaths);

        $trackDependencies = (bool) config('appinsights-laravel.client.track_dependencies', true);
        $trackDependenciesJson = json_encode($trackDependencies);

        $normalizeDependencyUrls = (bool) config('appinsights-laravel.client.normalize_dependency_urls', true);
        $normalizeDependencyUrlsJson = json_encode($normalizeDependencyUrls);

        $appId = config('appinsights-laravel.application_id');
        $appIdJson = json_encode($appId);

        $operationIdJson = json_encode($operationId);
        $parentIdJson = json_encode($parentId);
        $operationNameJson = json_encode($operationName);

        return <<<HTML
    <script>
        window.AppInsightsConfig = {
            collectEndpoint: {$endpointJson},
            operationId: {$operationIdJson},
            parentId: {$parentIdJson},
            operationName: {$operationNameJson},
            appId: {$appIdJson},
            trackDependencies: {$trackDependenciesJson},
            normalizeDependencyUrls: {$normalizeDependencyUrlsJson},
            excludedPaths: {$excludedPathsJson}
        };
    </script>
    <script src="{$jsAsset}"></script>
    HTML;
    }
}