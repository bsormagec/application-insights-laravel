<?php
namespace Sormagec\AppInsightsLaravel;

use Sormagec\AppInsightsLaravel\Support\Config;
use Sormagec\AppInsightsLaravel\Support\Logger;

class InstrumentationKey
{
    protected $flushQueueAfterSeconds;
    protected $connectionString;
    protected $instrumentationKey;

    public function __construct()
    {
        $this->setConnectionString();
    }

    protected function setConnectionString()
    {
        $this->flushQueueAfterSeconds = Config::get('flush_queue_after_seconds');
        $this->instrumentationKey = Config::get('instrumentation_key');

        $connectionString = Config::get('connection_string');
        if (!empty($connectionString)) {
            $this->connectionString = $connectionString;
            return;
        }
        
        if (!empty($this->instrumentationKey)) {
            // Deprecated warning - only log if logging is enabled
            Logger::warning('Using instrumentation_key is deprecated. Set MS_AI_CONNECTION_STRING in your .env file instead.');
            return;
        }

        $this->connectionString = null;
    }

    public function getFlushQueueAfterSeconds(): int
    {
        return intval($this->flushQueueAfterSeconds ?? 0);
    }
}