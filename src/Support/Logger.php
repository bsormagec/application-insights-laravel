<?php
namespace Sormagec\AppInsightsLaravel\Support;
use Illuminate\Support\Facades\Log as LaravelLog;

class Logger
{
    /**
     * Check if logging is enabled in config
     */
    private static function isLoggingEnabled(): bool
    {
        return (bool) Config::get('enable_local_logging', false);
    }

    public static function info(string $message, array $context = [])
    {
        if (!self::isLoggingEnabled()) {
            return;
        }
        if (class_exists(LaravelLog::class)) {
            LaravelLog::info($message, $context);
        }
    }

    public static function error(string $message, array $context = [])
    {
        // Errors should always be logged regardless of config
        if (class_exists(LaravelLog::class)) {
            LaravelLog::error($message, $context);
        }
    }

    public static function debug(string $message, array $context = [])
    {
        if (!self::isLoggingEnabled()) {
            return;
        }
        if (class_exists(LaravelLog::class)) {
            LaravelLog::debug($message, $context);
        }
    }

    public static function warning(string $message, array $context = [])
    {
        if (!self::isLoggingEnabled()) {
            return;
        }
        if (class_exists(LaravelLog::class)) {
            LaravelLog::warning($message, $context);
        }
    }
}
