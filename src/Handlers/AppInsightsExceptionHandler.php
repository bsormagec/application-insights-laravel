<?php
namespace Sormagec\AppInsightsLaravel\Handlers;
use Sormagec\AppInsightsLaravel\AppInsightsHelpers;
use Throwable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Sormagec\AppInsightsLaravel\Support\Logger;

class AppInsightsExceptionHandler extends ExceptionHandler
{
    /**
     * @var AppInsightsHelpers|null
     */
    private ?AppInsightsHelpers $appInsightsHelpers = null;

    /**
     * @var Container
     */
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
    }

    /**
     * Get AppInsightsHelpers instance lazily from container
     */
    protected function getAppInsightsHelpers(): ?AppInsightsHelpers
    {
        if ($this->appInsightsHelpers === null) {
            try {
                $this->appInsightsHelpers = $this->container->make(AppInsightsHelpers::class);
            } catch (Throwable $e) {
                Logger::error('Could not resolve AppInsightsHelpers: ' . $e->getMessage());
                return null;
            }
        }
        return $this->appInsightsHelpers;
    }

    /**
     * Report or log an exception.
     *
     * @param  Throwable  $e
     * @return void
     */
    public function report(Throwable $e)
    {
        try {
            $helpers = $this->getAppInsightsHelpers();
            if ($helpers) {
                $helpers->trackException($e);
            }
        } catch (Throwable $ex) {
            Logger::error('AppInsightsExceptionHandler telemetry error: ' . $ex->getMessage(), ['exception' => $ex]);
        }
        
        parent::report($e);
    }
}