<?php

return [

    /*
     * Connection String (Recommended)
     * ================================
     *
     * The connection string can be found on the Application Insights dashboard on portal.azure.com
     * Microsoft Azure > Application Insights > (Application Name) > Overview > Connection String
     *
     * Add the MS_AI_CONNECTION_STRING field to your application's .env file.
     * Example: InstrumentationKey=xxx;IngestionEndpoint=https://eastus.in.applicationinsights.azure.com/
     */
    'connection_string' => env('MS_AI_CONNECTION_STRING', null),

    /*
     * Instrumentation Key (Deprecated)
     * =================================
     * 
     * Use connection_string instead. This is kept for backward compatibility.
     */
    'instrumentation_key' => env('MS_INSTRUMENTATION_KEY', null),

    /*
     * Queue Flush Delay
     * ==================
     *
     * Number of seconds to wait before sending telemetry data via queue.
     * Set to 0 for synchronous (immediate) sending - NOT RECOMMENDED for production.
     * Set to 5+ for asynchronous sending via Laravel queue - RECOMMENDED.
     * 
     * When using queue, make sure to run: php artisan queue:work --queue=appinsights-queue
     */
    'flush_queue_after_seconds' => env('MS_AI_FLUSH_QUEUE_AFTER_SECONDS', 5),

    /*
     * Local Logging
     * ==============
     *
     * Enable to log telemetry payloads to Laravel log for debugging.
     * Set to false in production to avoid log spam.
     */
    'enable_local_logging' => env('MS_AI_ENABLE_LOGGING', false),

    /*
     * Max Query Parameters
     * =====================
     *
     * Maximum number of URL query parameters to include in telemetry.
     * Helps prevent sending sensitive or excessive data.
     */
    'max_query_params' => env('MS_AI_MAX_QUERY_PARAMS', 10),

    /*
     * Max SQL Length
     * ===============
     *
     * Maximum length of SQL queries to include in telemetry.
     * Longer queries will be truncated to prevent large payloads.
     */
    'max_sql_length' => env('MS_AI_MAX_SQL_LENGTH', 1000),

    /*
     * Slow Query Threshold
     * =====================
     *
     * Only log database queries slower than this value (in milliseconds).
     * Set to 0 to log all queries (not recommended for production).
     */
    'db_slow_ms' => env('MS_AI_DB_SLOW_MS', 500),

    /*
     * Feature Toggles
     * ================
     *
     * Enable or disable specific telemetry features.
     * Useful for reducing noise or focusing on specific areas.
     */
    'features' => [
        // Track slow database queries
        'db' => env('MS_AI_FEATURE_DB', true),
        
        // Track failed queue jobs
        'jobs' => env('MS_AI_FEATURE_JOBS', true),
        
        // Track sent emails
        'mail' => env('MS_AI_FEATURE_MAIL', true),
        
        // Track HTTP requests (via middleware)
        'http' => env('MS_AI_FEATURE_HTTP', true),
    ],

];
