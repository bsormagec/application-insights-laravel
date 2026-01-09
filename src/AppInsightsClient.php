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
        $endpoint = url('/appinsights/collect');
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

        return <<<HTML
    <script>
        window.AppInsightsConfig = {
            collectEndpoint: "{$endpoint}",
            operationId: "{$operationId}",
            parentId: "{$parentId}"
        };
    </script>
    <script src="{$jsAsset}"></script>
    HTML;
    }
}