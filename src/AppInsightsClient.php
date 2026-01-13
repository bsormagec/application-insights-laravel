<?php
namespace Sormagec\AppInsightsLaravel;

use Sormagec\AppInsightsLaravel\Facades\AppInsightsServerFacade as AIServer;

class AppInsightsClient extends InstrumentationKey
{
    /**
     * Generate JavaScript snippet for client-side telemetry with operation correlation
     * 
     * @return string
     */
    public function javascript()
    {
        // Use relative path to avoid Mixed Content issues (HTTP vs HTTPS mismatches)
        $endpoint = '/appinsights/collect';
        $jsAsset = asset('vendor/app-insights-laravel/js/appinsights-client.min.js');

        // Get operation context from server for correlation
        $operationId = '';
        $parentId = '';

        try {
            /** @var \Sormagec\AppInsightsLaravel\AppInsightsServer $server */
            $server = app('AppInsightsServer');
            if ($server && $server->telemetryClient) {
                $operationId = $server->telemetryClient->getOperationId() ?? '';
                $parentId = $server->telemetryClient->generateSpanId();
            }
        } catch (\Throwable $e) {
            // Silently fail if server not available
        }
        // Get excluded paths for client-side filtering
        $excludedPaths = config('appinsights-laravel.excluded_paths', []);
        $excludedPathsJson = json_encode($excludedPaths);

        return <<<HTML
    <script>
        window.AppInsightsConfig = {
            collectEndpoint: "{$endpoint}",
            operationId: "{$operationId}",
            parentId: "{$parentId}",
            excludedPaths: {$excludedPathsJson}
        };
    </script>
    <script src="{$jsAsset}"></script>
    HTML;
    }
}