<?php 

namespace Sormagec\AppInsightsLaravel\Providers;

use Sormagec\AppInsightsLaravel\Clients\Telemetry_Client;
use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Mail\Events\MessageSent;
use Sormagec\AppInsightsLaravel\Middleware\AppInsightsWebMiddleware;
use Sormagec\AppInsightsLaravel\Middleware\AppInsightsApiMiddleware;
use Sormagec\AppInsightsLaravel\AppInsightsClient;
use Sormagec\AppInsightsLaravel\AppInsightsHelpers;
use Sormagec\AppInsightsLaravel\AppInsightsServer;
use Sormagec\AppInsightsLaravel\Listeners\LogSlowQuery;
use Sormagec\AppInsightsLaravel\Listeners\LogJobFailed;
use Sormagec\AppInsightsLaravel\Listeners\LogMailSent;

class AppInsightsServiceProvider extends LaravelServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        $this->handleConfigs();
        $this->registerEventListeners();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register Telemetry_Client as singleton
        $this->app->singleton(Telemetry_Client::class, function ($app) {
            return new Telemetry_Client();
        });

        // Register AppInsightsServer as singleton
        $this->app->singleton('AppInsightsServer', function ($app) {
            return new AppInsightsServer($app->make(Telemetry_Client::class));
        });
        $this->app->alias('AppInsightsServer', AppInsightsServer::class);

        // Register AppInsightsHelpers as singleton (prevents multiple initializations)
        $this->app->singleton(AppInsightsHelpers::class, function ($app) {
            return new AppInsightsHelpers($app->make('AppInsightsServer'));
        });
        $this->app->alias(AppInsightsHelpers::class, 'AppInsightsHelpers');

        // Register middlewares as singletons using the shared AppInsightsHelpers
        $this->app->singleton('AppInsightsWebMiddleware', function ($app) {
            return new AppInsightsWebMiddleware($app->make(AppInsightsHelpers::class));
        });
        $this->app->alias('AppInsightsWebMiddleware', AppInsightsWebMiddleware::class);

        $this->app->singleton('AppInsightsApiMiddleware', function ($app) {
            return new AppInsightsApiMiddleware($app->make(AppInsightsHelpers::class));
        });
        $this->app->alias('AppInsightsApiMiddleware', AppInsightsApiMiddleware::class);

        $this->app->singleton('AppInsightsClient', function ($app) {
            return new AppInsightsClient();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {

        return [
            'AppInsightsServer',
            AppInsightsServer::class,
            'AppInsightsWebMiddleware',
            AppInsightsWebMiddleware::class,
            'AppInsightsApiMiddleware',
            AppInsightsApiMiddleware::class,
            'AppInsightsClient',
            'AppInsightsHelpers',
            AppInsightsHelpers::class,
            Telemetry_Client::class,
        ];
    }

    private function handleConfigs() {

        $configPath = $this->getConfigFile();
        $routesPath = $this->getRoutesFile(); 

        $this->publishes([
            $configPath => $this->app->configPath('appinsights-laravel.php'),
        ], 'config');

        $this->publishes([
            $this->getAssetsPath("js") => public_path('vendor/app-insights-laravel/js'),
        ], 'laravel-assets');

        $this->loadRoutesFrom($routesPath);

        $this->mergeConfigFrom($configPath, 'appinsights-laravel');
        
    }

    /**
     * Register event listeners for automatic telemetry tracking.
     */
    private function registerEventListeners(): void
    {
        $events = $this->app['events'];

        // Listen for slow database queries
        $events->listen(QueryExecuted::class, LogSlowQuery::class);

        // Listen for failed queue jobs
        $events->listen(JobFailed::class, LogJobFailed::class);

        // Listen for sent emails
        $events->listen(MessageSent::class, LogMailSent::class);
    }

    /**
     * @return string
     */
    private function getAssetsPath(string $path): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $path;
    }
    /**
     * @return string
     */
    protected function getRoutesFile(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
    }

    /**
     * @return string
     */
    protected function getConfigFile(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'AppInsightsLaravel.php';
    }
}
