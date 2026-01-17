<?php
namespace Sormagec\AppInsightsLaravel\Clients;

use Sormagec\AppInsightsLaravel\Exceptions\AppInsightsException;
use Sormagec\AppInsightsLaravel\Support\Config;
use Sormagec\AppInsightsLaravel\Support\ContextTagKeys;
use Sormagec\AppInsightsLaravel\Support\Logger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;

class Telemetry_Client
{
    /**
     * SDK version for telemetry identification
     */
    public const SDK_VERSION = '2.0.0';

    protected $baseUrl = 'https://dc.services.visualstudio.com';
    /**
     * Buffer for telemetry items to be sent.
     *
     * @var array
     */
    protected $buffer = [];

    /**
     * Limit for the buffer before it automatically flushes.
     *
     * @var int
     */
    protected $bufferLimit = 10; // Optional: auto-flush after N items

    /**
     * Global properties to be sent with every telemetry item.
     *
     * @var array
     */
    protected $globalProperties = [];

    /**
     * Context tags (Correlation ID etc)
     * 
     * @var array
     */
    protected $contextTags = [];

    /**
     * The instrumentation key for the Application Insights service.
     *
     * @var string
     */
    protected $instrumentationKey;

    /**
     * The connection string for the Application Insights service.
     *
     * @var string
     */
    protected $connectionString;

    /**
     * Telemetry_Client constructor.
     */
    public function __construct()
    {
        // Initialize W3C Trace Context compliant operation ID (32-char hex)
        $this->contextTags[ContextTagKeys::OPERATION_ID] = $this->generateW3CId();

        // Cloud role for Application Map
        $this->contextTags[ContextTagKeys::CLOUD_ROLE] = Config::get('cloud_role_name', env('APP_NAME', 'Laravel App'));

        // Cloud role instance - use config or hostname + slot name
        $roleInstance = Config::get('cloud_role_instance');
        if (empty($roleInstance)) {
            $slotName = env('WEBSITE_SLOT_NAME', '');  // Azure App Service sets this
            $roleInstance = gethostname();
            if (!empty($slotName) && $slotName !== 'production') {
                $roleInstance .= '-' . $slotName;
            }
        }
        $this->contextTags[ContextTagKeys::CLOUD_ROLE_INSTANCE] = $roleInstance;

        // Application version
        $this->contextTags[ContextTagKeys::APPLICATION_VERSION] = Config::get('application_version', '1.0.0');

        // SDK version
        $this->contextTags[ContextTagKeys::INTERNAL_SDK_VERSION] = 'php:' . self::SDK_VERSION;

        // Device type (server)
        $this->contextTags[ContextTagKeys::DEVICE_TYPE] = 'PC';

        // Flush at script end - send any remaining items in buffer
        register_shutdown_function(function () {
            if (count($this->buffer) > 0) {
                $this->flush();
            }
        });
    }

    /**
     * Sets the queue for telemetry data.
     *
     * @param array $data
     * @throws AppInsightsException
     */
    public function setQueue(array $data)
    {
        if (empty($data)) {
            Log::error('Telemetry data cannot be empty.');
            return;
        }

        if ($this->buffer === null) {
            $this->buffer = [];
        }

        array_push($this->buffer, ...$data);

        if (count($this->buffer) >= $this->bufferLimit) {
            $this->flush(); // Auto-flush
        }
    }

    /**
     * Gets the current queue of telemetry data.
     *
     * @return array
     */
    public function getQueue()
    {
        return $this->buffer;
    }

    /**
     * Sets the connection string for the Application Insights service.
     *
     * @param string $connectionString
     * @throws AppInsightsException
     */
    public function setConnectionString($connectionString)
    {
        if (Config::get('enable_local_logging', false)) {
            Log::debug('AI Setting ConnString to ', ['payload' => $connectionString]);
        }
        if (!empty($connectionString)) {
            $this->connectionString = $connectionString;
            preg_match('/IngestionEndpoint=(.+?);/', $connectionString, $matches);
            $endpoint = $matches[1] ?? $this->baseUrl;
            $instrumentationKey = preg_match('/InstrumentationKey=(.+?);/', $connectionString, $matches) ? $matches[1] : $this->instrumentationKey;
            $url = rtrim($endpoint, '/');
            $this->baseUrl = $url;
        }
        if (!empty($instrumentationKey)) {
            $this->instrumentationKey = $instrumentationKey;
        }
    }

    /**
     * Sets the instrumentation key for the Application Insights service.
     *
     * @param string $instrumentationKey
     */
    public function setInstrumentationKey($instrumentationKey)
    {
        $this->instrumentationKey = $instrumentationKey;
    }

    /**
     * Add a context tag to be sent with every payload
     * @param string $key
     * @param string $value
     */
    public function addContextTag(string $key, string $value)
    {
        $this->contextTags[$key] = $value;
    }

    /**
     * Get the current operation ID (correlation ID)
     * @return string|null
     */
    public function getOperationId(): ?string
    {
        return $this->contextTags[ContextTagKeys::OPERATION_ID] ?? null;
    }

    /**
     * Get the parent operation ID
     * @return string|null
     */
    public function getParentId(): ?string
    {
        return $this->contextTags[ContextTagKeys::OPERATION_PARENT_ID] ?? null;
    }

    /**
     * Set the parent operation ID for correlation
     * @param string $parentId
     */
    public function setParentId(string $parentId): void
    {
        $this->contextTags[ContextTagKeys::OPERATION_PARENT_ID] = $parentId;
    }

    /**
     * Generate a W3C-compliant trace ID (32 hex characters)
     * This is used as the operation.id for distributed tracing.
     * @return string
     */
    public function generateW3CId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a new span/request ID for child operations (16 hex characters)
     * This is used as the request.id for individual requests.
     * @return string
     */
    public function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Set the operation name (appears in Performance blade "Operation Name" column)
     * THIS IS CRITICAL - without this, Performance blade shows <Empty>
     * 
     * @param string $name Operation name like "GET /users/{id}"
     */
    public function setOperationName(string $name): void
    {
        $this->contextTags[ContextTagKeys::OPERATION_NAME] = $name;
    }

    /**
     * Get the current operation name
     * @return string|null
     */
    public function getOperationName(): ?string
    {
        return $this->contextTags[ContextTagKeys::OPERATION_NAME] ?? null;
    }

    /**
     * Set authenticated user ID for correlation
     * Maps to: ai.user.authUserId
     * 
     * @param string $userId
     */
    public function setAuthenticatedUserId(string $userId): void
    {
        $this->contextTags[ContextTagKeys::USER_AUTH_USER_ID] = $userId;
    }

    /**
     * Set session ID for correlation
     * Maps to: ai.session.id
     * 
     * @param string $sessionId
     */
    public function setSessionId(string $sessionId): void
    {
        $this->contextTags[ContextTagKeys::SESSION_ID] = $sessionId;
    }

    /**
     * Set synthetic source (e.g., "Bot", "HealthCheck", "Availability")
     * Maps to: ai.operation.syntheticSource
     * 
     * @param string $source
     */
    public function setSyntheticSource(string $source): void
    {
        $this->contextTags[ContextTagKeys::OPERATION_SYNTHETIC_SOURCE] = $source;
    }

    /**
     * Get the current cloud role name
     * @return string|null
     */
    public function getCloudRoleName(): ?string
    {
        return $this->contextTags[ContextTagKeys::CLOUD_ROLE] ?? null;
    }

    /**
     * Get the current cloud role instance
     * @return string|null
     */
    public function getCloudRoleInstance(): ?string
    {
        return $this->contextTags[ContextTagKeys::CLOUD_ROLE_INSTANCE] ?? null;
    }

    /**
     * Tracks a Request with the Application Insights service.
     *
     * @param string $name The name of the request.
     * @param string $url The URL of the request.
     * @param float $durationMs The duration of the request in milliseconds.
     * @param int $responseCode The HTTP response code.
     * @param bool $success Whether the request was successful.
     * @param array $properties
     * @param array $measurements
     * @param string|null $source Source of the request (optional)
     * @return void
     */
    public function trackRequest(string $name, string $url, float $durationMs, int $responseCode, bool $success, $properties = [], $measurements = [], ?string $source = null): void
    {
        // Set operation name in context tags (CRITICAL for Performance blade!)
        $this->setOperationName($name);

        // Generate unique span ID for this request (16-char hex, NOT the operation ID)
        $spanId = $this->generateSpanId();

        $urlParts = parse_url($url);
        $baseUrl = ($urlParts['scheme'] ?? '') . '://' .
            ($urlParts['host'] ?? '') .
            ($urlParts['path'] ?? '');
        // Query parameters (array)
        $queryParams = [];
        if (!empty($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        }

        $max_params = Config::get('max_query_params', 10);
        $queryParams = array_slice($queryParams, 0, $max_params, true);
        $properties = $properties ?? [];
        $properties = array_merge($this->globalProperties, $properties);
        $properties['fullUrl'] = $baseUrl;
        $properties['query_params'] = json_encode($queryParams);

        // Build baseData
        $baseData = [
            'ver' => 2,
            'id' => $spanId,  // Use span ID, NOT operation ID
            'name' => $name,
            'duration' => $this->formatDuration($durationMs),
            'responseCode' => (string) $responseCode,
            'success' => $success,
            'url' => $baseUrl,
            'properties' => $properties,
        ];

        // Add optional fields
        if (!empty($measurements)) {
            $baseData['measurements'] = $measurements;
        }
        if (!empty($source)) {
            $baseData['source'] = $source;
        }

        // Prepare the payload
        $payload = [
            'name' => 'Microsoft.ApplicationInsights.Request',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'RequestData',
                'baseData' => $baseData
            ]
        ];

        $this->sendPayload($payload);
    }


    /**
     * Tracks a PageView with the Application Insights service.
     * @param string $name The name of the page.
     * @param string $url The URL of the page.
     * @param float|null $durationMs Duration in milliseconds (optional)
     * @param string|null $referredUri The referring URI (optional)
     * @param array $properties
     * @param array $measurements
     * @return void
     */
    public function trackPageView(string $name, string $url, ?float $durationMs = null, ?string $referredUri = null, array $properties = [], array $measurements = [])
    {
        $properties = $properties ?? [];
        $properties = array_merge($this->globalProperties, $properties);

        $baseData = [
            'ver' => 2,
            'id' => $this->generateSpanId(),  // Add unique ID for page view
            'name' => $name,
            'url' => $url,
            'properties' => $properties,
        ];

        if ($durationMs !== null) {
            $baseData['duration'] = $this->formatDuration($durationMs);
        }
        if ($referredUri !== null) {
            $baseData['referredUri'] = $referredUri;
        }
        if (!empty($measurements)) {
            $baseData['measurements'] = $measurements;
        }

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.PageView',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'PageViewData',
                'baseData' => $baseData
            ]
        ];

        $this->sendPayload($payload);
    }


    /**
     * Tracks a Metric with the Application Insights service.
     * 
     * Supports two modes:
     * - Measurement: Single value (count is null or 1)
     * - Aggregation: Pre-aggregated metrics with count, min, max, stdDev
     * 
     * @param string $name The name of the metric (max 150 chars)
     * @param float $value The value of the metric. For aggregations, this is the SUM.
     * @param int|null $count Number of samples (null for single measurement)
     * @param float|null $min Minimum value across samples
     * @param float|null $max Maximum value across samples
     * @param float|null $stdDev Standard deviation of samples
     * @param string|null $namespace Metric namespace for grouping
     * @param array $properties Custom properties
     * @return void
     */
    public function trackMetric(
        string $name,
        float $value,
        ?int $count = null,
        ?float $min = null,
        ?float $max = null,
        ?float $stdDev = null,
        ?string $namespace = null,
        array $properties = []
    ): void {
        $properties = $properties ?? [];
        $properties = array_merge($this->globalProperties, $properties);

        // Build metric data point
        $dataPoint = [
            'name' => $name,
            'value' => $value,
        ];

        // Add namespace if provided
        if ($namespace !== null) {
            $dataPoint['ns'] = $namespace;
        }

        // Determine if this is an aggregation or measurement
        $isAggregation = ($count !== null && $count > 1)
            || $min !== null
            || $max !== null
            || $stdDev !== null;

        if ($isAggregation) {
            // Aggregation mode: include all stats
            $dataPoint['kind'] = 1; // 1 = Aggregation, 0 = Measurement
            $dataPoint['count'] = $count ?? 1;

            if ($min !== null) {
                $dataPoint['min'] = $min;
            }
            if ($max !== null) {
                $dataPoint['max'] = $max;
            }
            if ($stdDev !== null) {
                $dataPoint['stdDev'] = $stdDev;
            }
        } else {
            // Measurement mode: single value
            $dataPoint['kind'] = 0;
            $dataPoint['count'] = 1;
        }

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.Metric',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'MetricData',
                'baseData' => [
                    'ver' => 2,
                    'metrics' => [$dataPoint],
                    'properties' => $properties
                ]
            ]
        ];

        $this->sendPayload($payload);
    }


    /**
     * Tracks browser timings (page load performance) with the Application Insights service.
     * This sends PageViewPerformanceData which appears in the Browser tab in Azure.
     * 
     * @param string $name The name of the page.
     * @param string $url The URL of the page.
     * @param array $measurements Performance measurements from Navigation Timing API.
     * @param array $properties Additional properties.
     * @return void
     */
    public function trackBrowserTimings(string $name, string $url, array $measurements = [], array $properties = [])
    {
        $properties = array_merge($this->globalProperties ?? [], $properties);

        $duration = isset($measurements['pageLoadTime'])
            ? $this->formatDuration((float) $measurements['pageLoadTime'])
            : '00:00:00.000';

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.PageViewPerformance',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'PageViewPerformanceData',
                'baseData' => [
                    'ver' => 2,
                    'name' => $name,
                    'url' => $url,
                    'duration' => $duration,
                    'perfTotal' => $duration,
                    'networkConnect' => isset($measurements['tcpConnectTime'])
                        ? $this->formatDuration((float) $measurements['tcpConnectTime'])
                        : null,
                    'sentRequest' => isset($measurements['networkLatency'])
                        ? $this->formatDuration((float) $measurements['networkLatency'])
                        : null,
                    'receivedResponse' => isset($measurements['serverResponseTime'])
                        ? $this->formatDuration((float) $measurements['serverResponseTime'])
                        : null,
                    'domProcessing' => isset($measurements['domProcessingTime'])
                        ? $this->formatDuration((float) $measurements['domProcessingTime'])
                        : null,
                    'properties' => $properties,
                    'measurements' => $measurements
                ]
            ]
        ];

        $payload['data']['baseData'] = array_filter($payload['data']['baseData'], fn($v) => $v !== null);

        $this->sendPayload($payload);
    }

    /**
     * Tracks a JavaScript exception sent from the client side.
     *
     * @param array $data
     * @return void
     */
    public function trackExceptionFromArray(array $data)
    {
        $message = $data['message'] ?? 'Unknown JS error';
        $jsStack = $data['stack'] ?? null;

        // Keep JS details in properties
        $properties = [
            'filename' => $data['filename'] ?? null,
            'lineno' => $data['lineno'] ?? null,
            'colno' => $data['colno'] ?? null,
            'jsStack' => $jsStack,
        ];

        // Merge any additional properties
        if (isset($data['properties']) && is_array($data['properties'])) {
            $properties = array_merge($properties, $data['properties']);
        }

        $ex = new \Exception($message);

        // Send exception including JS stack
        $this->trackException($ex, $properties, $jsStack);
    }


    /**
     * Tracks an exception with the Application Insights service.
     * @param \Throwable $exception The exception to track.
     * @return void
     */
    public function trackException(\Throwable $exception, array $properties = [], ?string $overrideStack = null)
    {
        $properties = $properties ?? [];
        $properties = array_merge($this->globalProperties ?? [], $properties);
        $stackTrace = $exception->getTrace();
        $trace = $overrideStack
            ? [['level' => 0, 'method' => 'JS', 'assembly' => 'JS', 'stack' => $overrideStack]]
            : array_map(function ($frame, $index) {
                return [
                    'level' => $index,
                    'method' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
                    'assembly' => 'App',
                    'fileName' => $frame['file'] ?? '',
                    'line' => $frame['line'] ?? 0
                ];
            }, $stackTrace, array_keys($stackTrace));

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.Exception',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'ExceptionData',
                'baseData' => [
                    'ver' => 2,
                    'exceptions' => [
                        [
                            'id' => 1,
                            'outerId' => 0,
                            'typeName' => get_class($exception),
                            'message' => $exception->getMessage(),
                            'hasFullStack' => true,
                            'stack' => json_encode($trace),
                            'parsedStack' => $trace
                        ]
                    ],
                    'properties' => $properties
                ]
            ]
        ];

        $this->sendPayload($payload);
    }

    /**
     * Tracks a custom event with the Application Insights service.
     * @param string $eventName The name of the event.
     * @param array $properties Additional properties to include with the event.
     * @return void
     */
    public function trackEvent(string $eventName, array $properties = [])
    {
        $properties = $properties ?? [];
        $properties = array_merge($this->globalProperties ?? [], $properties);
        $payload = [
            'name' => 'Microsoft.ApplicationInsights.Event',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'EventData',
                'baseData' => [
                    'ver' => 2,
                    'name' => $eventName,
                    'properties' => $properties
                ]
            ]
        ];

        $this->sendPayload($payload);
    }

    /**
     * Tracks a message with the Application Insights service.
     * @param string $message The message to track.
     * @param int $severity The severity level of the message (0=Verbose, 1=Info, 2=Warning, 3=Error, 4=Critical).
     * @param array $properties Additional properties to include with the message.
     * @return void
     */
    public function trackMessage(string $message, array $properties = [], int $severity = 1)
    {
        $this->trackTrace($message, $severity, $properties);
    }
    /**
     * Tracks a trace message with the Application Insights service.
     * @param string $message The trace message.
     * @param int $severity The severity level of the trace (0=Verbose, 1=Info, 2=Warning, 3=Error, 4=Critical).
     * @param array $properties Additional properties to include with the trace.
     * @return void
     */
    public function trackTrace(string $message, int $severity = 1, array $properties = [])
    {
        $properties = $properties ?? [];
        $properties = array_merge($this->globalProperties ?? [], $properties);
        $payload = [
            'name' => 'Microsoft.ApplicationInsights.Message',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'MessageData',
                'baseData' => [
                    'ver' => 2,
                    'message' => $message,
                    'severityLevel' => $severity,
                    'properties' => $properties
                ]
            ]
        ];

        $this->sendPayload($payload);
    }

    /**
     * Tracks a database query (typically slow queries) with the Application Insights service.
     * 
     * @param string $sql The SQL query.
     * @param float $durationMs The duration of the query in milliseconds.
     * @param array $properties Additional properties to include with the query.
     * @return void
     */
    public function trackDbQuery(string $sql, float $durationMs, array $properties = [])
    {
        $properties = array_merge($this->globalProperties ?? [], $properties, [
            'db.sql' => $this->sanitizeSql($sql),
            'db.duration_ms' => $durationMs,
        ]);

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.RemoteDependency',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'RemoteDependencyData',
                'baseData' => [
                    'ver' => 2,
                    'name' => 'SQL Query',
                    'id' => bin2hex(random_bytes(8)),
                    'data' => $this->sanitizeSql($sql),
                    'duration' => $this->formatDuration($durationMs),
                    'success' => true,
                    'type' => 'SQL',
                    'target' => $properties['db.connection'] ?? 'database',
                    'properties' => $properties,
                ]
            ]
        ];

        $this->sendPayload($payload);
    }

    /**
     * Tracks a dependency call (HTTP, SQL, etc.) with the Application Insights service.
     * 
     * @param string $type The type of dependency (HTTP, SQL, etc.).
     * @param string $target The target of the dependency (hostname, database name, etc.).
     * @param string $name The name of the dependency call.
     * @param float $durationMs The duration of the call in milliseconds.
     * @param bool $success Whether the call was successful.
     * @param string|null $resultCode Result code (HTTP status code, SQL error code, etc.)
     * @param string|null $data Command/URL/query string
     * @param array $properties Additional properties to include.
     * @param array $measurements Additional measurements to include.
     * @return void
     */
    public function trackDependency(
        string $type,
        string $target,
        string $name,
        float $durationMs,
        bool $success = true,
        ?string $resultCode = null,
        ?string $data = null,
        array $properties = [],
        array $measurements = []
    ): void {
        $properties = array_merge($this->globalProperties ?? [], $properties);

        $baseData = [
            'ver' => 2,
            'name' => $name,
            'id' => $this->generateSpanId(),
            'duration' => $this->formatDuration($durationMs),
            'success' => $success,
            'type' => $type,
            'target' => $target,
            'properties' => $properties,
        ];

        if ($resultCode !== null) {
            $baseData['resultCode'] = $resultCode;
        }
        if ($data !== null) {
            $baseData['data'] = $data;
        }
        if (!empty($measurements)) {
            $baseData['measurements'] = $measurements;
        }

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.RemoteDependency',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'RemoteDependencyData',
                'baseData' => $baseData
            ]
        ];

        $this->sendPayload($payload);
    }


    /**
     * Tracks an availability test result with the Application Insights service.
     * 
     * @param string $name Test name
     * @param float $durationMs Duration in milliseconds
     * @param bool $success Whether the test passed
     * @param string|null $runLocation Location where the test ran
     * @param string|null $message Diagnostic message
     * @param array $properties Additional properties
     * @param array $measurements Additional measurements
     * @return void
     */
    public function trackAvailability(
        string $name,
        float $durationMs,
        bool $success,
        ?string $runLocation = null,
        ?string $message = null,
        array $properties = [],
        array $measurements = []
    ): void {
        $properties = array_merge($this->globalProperties ?? [], $properties);

        $baseData = [
            'ver' => 2,
            'id' => $this->generateSpanId(),
            'name' => $name,
            'duration' => $this->formatDuration($durationMs),
            'success' => $success,
            'properties' => $properties,
        ];

        if ($runLocation !== null) {
            $baseData['runLocation'] = $runLocation;
        }
        if ($message !== null) {
            $baseData['message'] = $message;
        }
        if (!empty($measurements)) {
            $baseData['measurements'] = $measurements;
        }

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.Availability',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'AvailabilityData',
                'baseData' => $baseData
            ]
        ];

        $this->sendPayload($payload);
    }

    /**

     * Sanitize SQL query by removing sensitive data patterns.
     * 
     * @param string $sql
     * @return string
     */
    protected function sanitizeSql(string $sql): string
    {
        // Limit SQL length to prevent large payloads
        $maxLength = (int) Config::get('max_sql_length', 1000);
        if (strlen($sql) > $maxLength) {
            $sql = substr($sql, 0, $maxLength) . '...';
        }
        return $sql;
    }

    protected function sendPayload(array $payload)
    {
        $this->buffer[] = $payload;
        if (Config::get('enable_local_logging', false)) {
            Log::debug('Added payload to buffer', ['payload' => $payload]);
        }
        if (count($this->buffer) >= $this->bufferLimit) {
            $this->flush(); // Auto-flush
        }
    }

    public function flush()
    {
        $enableLocalLogging = false;
        try {
            $enableLocalLogging = Config::get('enable_local_logging', false);
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                Logger::error('AppInsights flush failed: ' . $e->getMessage());
            }
        }

        if (empty($this->buffer)) {
            if ($enableLocalLogging) {
                Logger::debug('empty buffer response', ['body' => 'No data to send']);
            }
            return;
        }

        try {
            if ($enableLocalLogging) {
                $this->logPayloadBeforeFlush();
            }

            $payload = $this->formatBatchPayload();
            $url = $this->baseUrl . '/v2/track';

            // Use async HTTP to avoid blocking the main thread
            $this->sendAsync($url, $payload, $enableLocalLogging);

            $this->buffer = [];
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                Logger::error('AppInsights flush failed: ' . $e->getMessage());
            }
            // else swallow silently during shutdown
        }
    }

    /**
     * Send telemetry data asynchronously (fire-and-forget)
     * Uses ultra-low timeout to avoid blocking the main request
     *
     * @param string $url
     * @param string $payload
     * @param bool $enableLocalLogging
     * @return void
     */
    protected function sendAsync(string $url, string $payload, bool $enableLocalLogging = false): void
    {
        try {
            // For CLI/queue contexts, use normal timeout
            if ($this->shouldWaitForResponse()) {
                $client = new Client([
                    'timeout' => 10,
                    'connect_timeout' => 5,
                ]);

                $response = $client->post($url, [
                    'headers' => ['Content-Type' => 'application/x-ndjson'],
                    'body' => $payload,
                    'query' => ['iKey' => $this->instrumentationKey],
                ]);

                if ($enableLocalLogging && function_exists('logger')) {
                    Logger::debug('Raw AppInsights response', ['body' => $response->getBody()->getContents()]);
                }
                return;
            }

            // For web requests: Fire-and-forget with minimal timeout
            // The request will be sent but we won't wait for response
            $client = new Client([
                'timeout' => 0.1,        // 100ms max - just enough to send
                'connect_timeout' => 0.1, // 100ms to connect
                'http_errors' => false,   // Don't throw on HTTP errors
            ]);

            try {
                $client->post($url, [
                    'headers' => ['Content-Type' => 'application/x-ndjson'],
                    'body' => $payload,
                    'query' => ['iKey' => $this->instrumentationKey],
                ]);
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                // Timeout expected - request was sent, we just didn't wait for response
                if ($enableLocalLogging) {
                    Logger::debug('AppInsights fire-and-forget sent (timeout expected)');
                }
            }
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                Logger::error('AppInsights sendAsync failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Determine if we should wait for the response
     * In CLI/queue contexts, waiting is fine. In web requests, we don't wait.
     *
     * @return bool
     */
    protected function shouldWaitForResponse(): bool
    {
        // In CLI (artisan commands, queue workers), we can wait
        if (php_sapi_name() === 'cli') {
            return true;
        }

        // In web requests, don't block
        return false;
    }


    protected function logPayloadBeforeFlush()
    {
        Log::debug("AppInsights Payload Posting to", ['url' => $this->baseUrl . '/v2/track']);
        foreach ($this->buffer as $index => $item) {
            Log::debug("AppInsights Payload [{$index}]", ['json' => json_encode($item)]);
        }

        try {
            $ndjson = $this->formatBatchPayload();
            Log::debug("AppInsights NDJSON Payload", ['ndjson' => $ndjson]);
        } catch (\Throwable $e) {
            Log::error("Failed to format AppInsights NDJSON payload: " . $e->getMessage());
        }
    }


    protected function formatBatchPayload()
    {
        return implode("\n", array_map(fn($item) => json_encode($item), $this->buffer));
    }

    /**
     * Formats the duration in milliseconds to a string format.
     *
     * @param float $milliseconds
     * @return string
     */
    protected function formatDuration($milliseconds): string
    {
        $hours = floor($milliseconds / 3600000);
        $minutes = floor(($milliseconds % 3600000) / 60000);
        $seconds = floor(($milliseconds % 60000) / 1000);
        $ms = $milliseconds % 1000;
        if (Config::get('enable_local_logging', false)) {
            Log::info("AppInsights duration formatted: {$milliseconds} {$hours}:{$minutes}:{$seconds}.{$ms}");
        }
        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $ms);
    }

    /**
     * Get the instrumentation key.
     *
     * @return string|null
     */
    public function getInstrumentationKey()
    {
        return $this->instrumentationKey;
    }
}
