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
     * Application ID
     * ===============
     * 
     * The Application ID from Azure Portal (Configure > API Access).
     * This is DIFFERENT from Instrumentation Key!
     * Required for Application Map browser-to-server correlation.
     * 
     * Find it: Azure Portal > Application Insights > Configure > API Access > Application ID
     */
    'application_id' => env('MS_AI_APPLICATION_ID', null),

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

    /*
     * Excluded Paths
     * ===============
     *
     * List of URL path patterns to exclude from request tracking.
     * Supports wildcards (*) for pattern matching.
     * Useful for excluding health checks, Horizon API, Telescope, etc.
     * 
     * Examples:
     *   'horizon/*'     - Excludes all Horizon routes
     *   'health'        - Excludes exact /health path
     *   'api/v1/ping'   - Excludes exact path
     */
    'excluded_paths' => [
        'horizon/*',
        'telescope/*',
        'livewire/*',
        '_debugbar/*',
        'appinsights/*',
        'sanctum/*',
    ],

    /*
     * Client-Side Telemetry
     * ======================
     *
     * Control browser telemetry collection and dependency tracking.
     */
    'client' => [
        // Enable or disable client-side telemetry collection
        'enabled' => env('MS_AI_CLIENT_ENABLED', true),

        // Track browser-originated HTTP dependencies (XHR/fetch)
        'track_dependencies' => env('MS_AI_CLIENT_TRACK_DEPENDENCIES', true),

        // Normalize dependency URLs (strip query strings and IDs for grouping)
        'normalize_dependency_urls' => env('MS_AI_CLIENT_NORMALIZE_DEPENDENCY_URLS', true),
    ],

    /*
     * Cloud Role Name
     * ================
     *
     * The name that appears on the Application Map for this component.
     * This should be unique per application/service.
     * Maps to: ai.cloud.role
     * 
     * Example: "TRACS-AI", "API-Gateway", "WebFrontend"
     */
    'cloud_role_name' => env('MS_AI_CLOUD_ROLE_NAME', env('APP_NAME', 'Laravel App')),

    /*
     * Cloud Role Instance
     * ====================
     *
     * Instance identifier (e.g., server name, container ID, deployment slot).
     * Use this to differentiate between deployment slots (production, staging).
     * Maps to: ai.cloud.roleInstance
     * 
     * If not set, defaults to hostname + slot name (if running on Azure App Service).
     * 
     * Example: "n301-easi-tracs-app-production", "server-1"
     */
    'cloud_role_instance' => env('MS_AI_CLOUD_ROLE_INSTANCE', null),

    /*
     * Application Version
     * ====================
     *
     * Version of your application for tracking deployments.
     * Maps to: ai.application.ver
     * 
     * Useful for tracking performance changes between releases.
     */
    'application_version' => env('MS_AI_APP_VERSION', '1.0.0'),

    /*
     * Track Authenticated User
     * =========================
     *
     * Enable automatic user ID tracking from authenticated users.
     * Maps to: ai.user.authUserId
     * 
     * When enabled, the authenticated user's ID will be sent with all telemetry.
     * Useful for tracking user-specific issues.
     */
    'track_authenticated_user' => env('MS_AI_TRACK_AUTH_USER', true),

    /*
     * Track Session
     * ==============
     *
     * Enable session ID tracking.
     * Maps to: ai.session.id
     * 
     * When enabled, the session ID will be sent with all telemetry.
     * Useful for grouping telemetry by user session.
     */
    'track_session' => env('MS_AI_TRACK_SESSION', true),

    /*
     * Detect Synthetic Source
     * ========================
     *
     * Automatically detect synthetic traffic (bots, health checks).
     * Maps to: ai.operation.syntheticSource
     * 
     * When enabled, requests from known bots and health check paths
     * will be marked as synthetic traffic, making it easier to filter
     * them out in Azure Portal queries.
     */
    'detect_synthetic_source' => env('MS_AI_DETECT_SYNTHETIC', true),

];

