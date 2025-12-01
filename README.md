# Microsoft Azure Application Insights for Laravel 10+

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sormagec/application-insights-laravel.svg?style=flat-square)](https://packagist.org/packages/sormagec/application-insights-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/sormagec/application-insights-laravel.svg?style=flat-square)](https://packagist.org/packages/sormagec/application-insights-laravel)
[![License](https://img.shields.io/packagist/l/sormagec/application-insights-laravel.svg?style=flat-square)](https://packagist.org/packages/sormagec/application-insights-laravel)

A fully maintained Laravel package for Microsoft Azure Application Insights integration. Push telemetry from your Laravel web app and APIs directly to Application Insights with queue support and separate handling for API and web routes.

> **Note:** This is a fork of the original [larasahib/application-insights-laravel](https://github.com/GitSahib/application-insights-laravel) package with bug fixes, improvements, and continued maintenance.

## Features

- ✅ **Request Tracking** - Automatic HTTP request monitoring
- ✅ **Exception Tracking** - Automatic error reporting to Azure
- ✅ **Custom Events** - Track custom events and metrics
- ✅ **Trace Logging** - Send log messages to Application Insights
- ✅ **Queue Support** - Async telemetry via Laravel queues (Redis, etc.)
- ✅ **Client-Side JS** - Browser telemetry collection
- ✅ **Laravel 10+** - Full support for modern Laravel versions

## Requirements

- PHP >= 8.1
- Laravel >= 10.0
- Guzzle >= 7.0

## Installation

```bash
composer require sormagec/application-insights-laravel
```

The package will auto-register via Laravel's package discovery.

## Configuration

### 1. Publish the configuration file

```bash
php artisan vendor:publish --tag=config --provider="Sormagec\AppInsightsLaravel\Providers\AppInsightsServiceProvider"
```

### 2. Set your Connection String

Add to your `.env` file:

```env
# Get this from Azure Portal > Application Insights > Overview > Connection String
MS_AI_CONNECTION_STRING=InstrumentationKey=00000000-0000-0000-0000-000000000000;IngestionEndpoint=https://<region>.in.applicationinsights.azure.com/

# Queue delay in seconds (0 = sync, >0 = async via queue)
MS_AI_FLUSH_QUEUE_AFTER_SECONDS=5

# Enable debug logging (0 = disabled, 1 = enabled)
MS_AI_ENABLE_LOGGING=0

# Max query parameters to include in telemetry
MS_AI_MAX_QUERY_PARAMS=10
```

> ⚠️ **Note:** `MS_INSTRUMENTATION_KEY` is deprecated. Use `MS_AI_CONNECTION_STRING` instead.

### Where to find the Connection String

Azure Portal → Application Insights → Overview → Connection String

## Usage

### Request Tracking Middleware

Add the middleware to your `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \Sormagec\AppInsightsLaravel\Middleware\AppInsightsWebMiddleware::class,
    ],

    'api' => [
        // ... other middleware
        \Sormagec\AppInsightsLaravel\Middleware\AppInsightsApiMiddleware::class,
    ],
];
```

**Tracked Properties:**
- `ajax` - Whether the request is AJAX
- `ip` - Client IP address
- `pjax` - Whether the request is PJAX
- `secure` - Whether HTTPS was used
- `route` - Route name (if available)
- `user` - User ID (if authenticated)
- `referer` - HTTP referer

### Exception Handler

Replace the base exception handler in `app/Exceptions/Handler.php`:

```php
<?php

namespace App\Exceptions;

// Replace this line:
// use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

// With this:
use Sormagec\AppInsightsLaravel\Handlers\AppInsightsExceptionHandler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    // Your existing code...
}
```

### Client-Side Telemetry

Add to your Blade layout (preferably in `<head>`):

```php
{!! \AIClient::javascript() !!}
```

### Custom Telemetry

```php
// Track a custom event
\AIServer::trackEvent('UserRegistered', ['plan' => 'premium']);

// Track a trace message
\AIServer::trackTrace('User completed checkout', 1, ['orderId' => '12345']);

// Track an exception manually
\AIServer::trackException($exception, ['context' => 'payment']);

// Flush immediately (sync)
\AIServer::flush();

// Or use queue for async sending
\AIQueue::dispatch(\AIServer::getChannel()->getQueue())
    ->onQueue('appinsights-queue')
    ->delay(now()->addSeconds(5));
```

### Queue Worker Setup

For async telemetry, run a dedicated queue worker:

```bash
php artisan queue:work redis --queue=appinsights-queue --sleep=3 --tries=3
```

**For production (Supervisor example):**

```ini
[program:appinsights-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=appinsights-queue --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=1
```

## Publishing Assets

### Configuration
```bash
php artisan vendor:publish --tag=config
```

### JavaScript Assets
```bash
php artisan vendor:publish --tag=laravel-assets
```

## Changelog

### v2.0.0
- 🔄 Forked and rebranded from `larasahib/application-insights-laravel`
- 🐛 Fixed singleton issues causing multiple initializations
- 🐛 Fixed Logger respecting `enable_local_logging` config
- 🐛 Fixed ExceptionHandler lazy loading
- ✨ Better default config values
- 📝 Updated documentation

### Previous versions
See original package history at [larasahib/application-insights-laravel](https://github.com/GitSahib/application-insights-laravel)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License. See [LICENSE](LICENSE) for more information.

## Credits

- Original package by [Sahib](https://github.com/GitSahib) - **Thank you for creating and sharing this package with the community!** 🙏
- Maintained by [Burak Sormagec](https://github.com/bsormagec)

## Acknowledgments

Special thanks to [Sahib](https://github.com/GitSahib) for the original [larasahib/application-insights-laravel](https://github.com/GitSahib/application-insights-laravel) package. This fork continues the development with bug fixes and improvements while preserving the original architecture and design decisions.
