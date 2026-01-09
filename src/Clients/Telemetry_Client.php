<?php
namespace Sormagec\AppInsightsLaravel\Clients;
use Sormagec\AppInsightsLaravel\Exceptions\AppInsightsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Sormagec\AppInsightsLaravel\Support\Config;
use Sormagec\AppInsightsLaravel\Support\Logger;
use Illuminate\Support\Facades\Http;

class Telemetry_Client
{

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
        // Initialize correlation ID
        $this->contextTags['ai.operation.id'] = uniqid('req_', true);

        // Flush at script end
        register_shutdown_function(function () {
            if (count($this->buffer) > $this->bufferLimit) {
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
     * Tracks a Request with the Application Insights service.
     *
     * @param string $name The name of the request.
     * @param string $url The URL of the request.
     * @param float $durationMs The duration of the request in milliseconds.
     * @param int $responseCode The HTTP response code.
     * @param bool $success Whether the request was successful.
     * @param array $properties
     * @param array $measurements
     * @return void
     */
    public function trackRequest(string $name, string $url, float $durationMs, int $responseCode, bool $success, $properties = [], $measurements = []): void
    {
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
        // Prepare the payload
        $payload = [
            'name' => 'Microsoft.ApplicationInsights.Request',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'RequestData',
                'baseData' => [
                    'ver' => 2,
                    'id' => $this->contextTags['ai.operation.id'],
                    'name' => $name,
                    'duration' => $this->formatDuration($durationMs),
                    'responseCode' => (string) $responseCode,
                    'success' => $success,
                    'url' => $baseUrl,
                    'properties' => $properties,
                ]
            ]
        ];

        $this->sendPayload($payload);
    }

    /**
     * Tracks a PageView with the Application Insights service.
     * @param string $name The name of the page.
     * @param string $url The URL of the page.
     * @param array $properties
     * @param array $measurements
     * @return void
     */
    public function trackPageView(string $name, string $url, array $properties = [], array $measurements = [])
    {
        $properties = $properties ?? [];
        $properties = array_merge($this->globalProperties, $properties);

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.PageView',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'PageViewData',
                'baseData' => [
                    'ver' => 2,
                    'name' => $name,
                    'url' => $url,
                    'properties' => $properties,
                    'measurements' => $measurements
                ]
            ]
        ];

        $this->sendPayload($payload);
    }

    /**
     * Tracks a Metric with the Application Insights service.
     * @param string $name The name of the metric.
     * @param float $value The value of the metric.
     * @param array $properties
     * @return void
     */
    public function trackMetric(string $name, float $value, array $properties = [])
    {
        $properties = $properties ?? [];
        $properties = array_merge($this->globalProperties, $properties);

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.Metric',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'MetricData',
                'baseData' => [
                    'ver' => 2,
                    'metrics' => [
                        [
                            'name' => $name,
                            'value' => $value,
                            'count' => 1
                        ]
                    ],
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
        $trace = $overrideStack
            ? [['level' => 0, 'method' => 'JS', 'assembly' => 'JS', 'stack' => $overrideStack]]
            : array_map(function ($frame, $index) {
                return [
                    'level' => $index,
                    'method' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'],
                    'assembly' => 'App', // Optionally extract assembly name
                    'fileName' => $frame['file'] ?? '',
                    'line' => $frame['line'] ?? 0
                ];
            }, $exception->getTrace());

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
     * @param array $properties Additional properties to include.
     * @return void
     */
    public function trackDependency(string $type, string $target, string $name, float $durationMs, bool $success = true, array $properties = [])
    {
        $properties = array_merge($this->globalProperties ?? [], $properties);

        $payload = [
            'name' => 'Microsoft.ApplicationInsights.RemoteDependency',
            'time' => Carbon::now()->toIso8601ZuluString(),
            'iKey' => $this->instrumentationKey,
            'tags' => $this->contextTags,
            'data' => [
                'baseType' => 'RemoteDependencyData',
                'baseData' => [
                    'ver' => 2,
                    'name' => $name,
                    'id' => bin2hex(random_bytes(8)),
                    'duration' => $this->formatDuration($durationMs),
                    'success' => $success,
                    'type' => $type,
                    'target' => $target,
                    'properties' => $properties,
                ]
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
        if (empty($this->buffer) && $enableLocalLogging) {
            Logger::debug('empty buffer response', ['body' => 'No data to send']);
            return;
        }

        try {

            if ($enableLocalLogging) {
                $this->logPayloadBeforeFlush();
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/x-ndjson',
            ])->withBody(
                    $this->formatBatchPayload(),
                    'application/x-ndjson'
                )->post($this->baseUrl . '/v2/track', [
                        'iKey' => $this->instrumentationKey,
                    ]);

            if ($enableLocalLogging && function_exists('logger')) {
                Logger::debug('Raw AppInsights response', ['body' => $response->body()]);
            }

            $this->buffer = [];
        } catch (\Throwable $e) {
            if (function_exists('logger')) {
                Logger::error('AppInsights flush failed: ' . $e->getMessage());
            }
            // else swallow silently during shutdown
        }
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
