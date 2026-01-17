# Microsoft Azure Application Insights for Laravel 10+

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sormagec/application-insights-laravel.svg?style=flat-square)](https://packagist.org/packages/sormagec/application-insights-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/sormagec/application-insights-laravel.svg?style=flat-square)](https://packagist.org/packages/sormagec/application-insights-laravel)
[![License](https://img.shields.io/packagist/l/sormagec/application-insights-laravel.svg?style=flat-square)](https://packagist.org/packages/sormagec/application-insights-laravel)

A fully maintained Laravel package for Microsoft Azure Application Insights integration. Push telemetry from your Laravel web app and APIs directly to Application Insights with queue support and separate handling for API and web routes.

> **Note:** This is a fork of the original [larasahib/application-insights-laravel](https://github.com/GitSahib/application-insights-laravel) package with bug fixes, improvements, and continued maintenance.

## Features

- ✅ **Request Tracking** - Automatic HTTP request monitoring
- ✅ **Exception Tracking** - Automatic error reporting to Azure
- ✅ **Slow Query Tracking** - Automatic slow database query monitoring
- ✅ **Failed Job Tracking** - Automatic queue job failure reporting
- ✅ **Mail Tracking** - Track sent emails
- ✅ **Custom Events** - Track custom events and metrics
- ✅ **Trace Logging** - Send log messages to Application Insights
- ✅ **Queue Support** - Async telemetry via Laravel queues (Redis, etc.)
- ✅ **Client-Side JS** - Browser telemetry collection (PageView, BrowserTimings, errors)
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

# Slow query threshold in milliseconds (queries slower than this are tracked)
MS_AI_DB_SLOW_MS=500

# Feature toggles (all enabled by default)
MS_AI_FEATURE_DB=true
MS_AI_FEATURE_JOBS=true
MS_AI_FEATURE_MAIL=true
MS_AI_FEATURE_HTTP=true

# Application Map & Correlation (NEW in v3.0.0)
MS_AI_CLOUD_ROLE_NAME=MyApp          # Node name in Application Map
MS_AI_CLOUD_ROLE_INSTANCE=production # Instance name (auto-detects Azure slot)
MS_AI_APP_VERSION=1.0.0              # Application version

# User & Session Tracking
MS_AI_TRACK_AUTH_USER=true           # Track authenticated user IDs
MS_AI_TRACK_SESSION=true             # Track session IDs
MS_AI_DETECT_SYNTHETIC=true          # Detect bots and health checks
```

### Azure App Service Configuration

For proper Application Map rendering and slot differentiation:

**Production slot:**
```env
MS_AI_CLOUD_ROLE_NAME=MyApp
MS_AI_CLOUD_ROLE_INSTANCE=production
```

**Staging slot:**
```env
MS_AI_CLOUD_ROLE_NAME=MyApp
MS_AI_CLOUD_ROLE_INSTANCE=staging
```

> **Tip:** If running on Azure App Service, the slot name is automatically detected from `WEBSITE_SLOT_NAME` environment variable.


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

This automatically tracks:
- **Page Views** - Every page load with URL and title
- **Browser Timings** - Page load time, DOM processing, network latency, server response time
- **JavaScript Errors** - Uncaught exceptions and unhandled promise rejections
- **User Sessions** - Anonymous user ID and session tracking
- **AJAX/Fetch Requests** - All XHR and Fetch calls with duration and status
- **Web Vitals** - LCP, FID, CLS, FCP, FP, INP (Core Web Vitals)
- **Long Tasks** - JavaScript tasks taking 50ms+

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

## Automatic Tracking Features

The following telemetry is collected automatically without any additional configuration:

### Slow Database Queries

Queries slower than the configured threshold (`MS_AI_DB_SLOW_MS`, default 500ms) are automatically tracked as dependencies in Application Insights.

**Tracked Properties:**
- `db.sql` - The SQL query (truncated for safety)
- `db.duration_ms` - Query duration in milliseconds
- `db.connection` - Database connection name

To disable: `MS_AI_FEATURE_DB=false`

### Failed Queue Jobs

Failed queue jobs are automatically tracked as exceptions in Application Insights.

**Tracked Properties:**
- `job.name` - The job class name
- `job.queue` - The queue name
- `job.connection` - Queue connection name
- `job.payload` - Job payload (truncated)

To disable: `MS_AI_FEATURE_JOBS=false`

### Sent Emails

Sent emails are tracked as custom events in Application Insights.

**Tracked Properties:**
- `mail.to` - Number of recipients
- `mail.subject` - Email subject
- `mail.class` - Notification class name (if available)

To disable: `MS_AI_FEATURE_MAIL=false`

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

### v3.0.0 - Full SDK Parity Release 🎉

This release brings **full feature parity** with Microsoft's official Application Insights SDKs (.NET, Node.js, Java).

**New Context Tags (Application Map & Correlation):**
- ✨ W3C Trace Context support - 32-char hex trace IDs for proper correlation
- ✨ `ai.operation.name` - Operation names now appear in Performance blade
- ✨ `ai.cloud.role` / `ai.cloud.roleInstance` - Application Map now renders correctly
- ✨ Auto-detection of Azure deployment slots via `WEBSITE_SLOT_NAME`
- ✨ `ai.application.ver`, `ai.internal.sdkVersion`, `ai.device.type` tags

**New Methods:**
- ✨ `trackAvailability()` - Track availability test results
- ✨ `setOperationName()` / `getOperationName()` - Set operation name for Performance blade
- ✨ `setAuthenticatedUserId()` - Track authenticated user IDs
- ✨ `setSessionId()` - Track session IDs
- ✨ `setSyntheticSource()` - Mark synthetic traffic (bots, health checks)

**Enhanced Telemetry:**
- ✨ `trackMetric()` now supports aggregation: `count`, `min`, `max`, `stdDev`, `namespace`
- ✨ `trackRequest()` now includes span ID, source, and measurements
- ✨ `trackDependency()` now includes resultCode, data, and measurements
- ✨ `trackPageView()` now includes id, duration, and referredUri

**New Configuration Options:**
```env
MS_AI_CLOUD_ROLE_NAME=MyApp          # Application Map node name
MS_AI_CLOUD_ROLE_INSTANCE=prod       # Instance/slot identifier
MS_AI_APP_VERSION=1.0.0              # Application version
MS_AI_TRACK_AUTH_USER=true           # Track authenticated users
MS_AI_TRACK_SESSION=true             # Track sessions
MS_AI_DETECT_SYNTHETIC=true          # Detect bots/health checks
```

**⚠️ Note:** Live Metrics (QuickPulse) is not supported as it requires a WebSocket/SignalR connection that is architecturally incompatible with PHP's request-response model.

### v2.2.0

- ✨ Added slow request tracking threshold (`MS_AI_REQUEST_SLOW_MS`)
- ✨ Added automatic PageView tracking for Browser tab in Azure
- ✨ Added BrowserTimings (page load performance) tracking
- ✨ Added User Session tracking (anonymous user ID, session ID)
- ✨ Added AJAX/Fetch request tracking as dependencies
- ✨ Added Web Vitals: LCP, FID, CLS, FCP, FP, INP
- ✨ Added Long Tasks tracking (50ms+ JavaScript tasks)
- 📝 Completely rewritten client-side JavaScript SDK

### v2.1.0
- ✨ Added slow database query tracking
- ✨ Added failed queue job tracking
- ✨ Added mail sent tracking
- ✨ Added `trackDbQuery()` and `trackDependency()` methods
- ✨ Added feature toggles for granular control
- 📝 Updated documentation with new features

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
