<?php
/**
 * Performance Monitoring System
 * Tracks page load times, database queries, and system performance
 */
class PerformanceMonitor
{
    private static $instance = null;
    private $startTime;
    private $memoryStart;
    private $queries = [];
    private $timers  = [];
    private $metrics = [];
    private $logFile;
    private $enabled;

    private function __construct()
    {
        $this->startTime   = microtime(true);
        $this->memoryStart = memory_get_usage(true);
        $this->logFile     = __DIR__ . '/../logs/performance.log';
        $this->enabled     = true; // Set to false in production if needed

        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (! is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Register shutdown function to log final metrics
        register_shutdown_function([$this, 'logFinalMetrics']);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new PerformanceMonitor();
        }
        return self::$instance;
    }

    /**
     * Start timing a specific operation
     */
    public function startTimer($name)
    {
        if (! $this->enabled) {
            return;
        }

        $this->timers[$name] = [
            'start'        => microtime(true),
            'memory_start' => memory_get_usage(true),
        ];
    }

    /**
     * End timing a specific operation
     */
    public function endTimer($name)
    {
        if (! $this->enabled || ! isset($this->timers[$name])) {
            return;
        }

        $this->timers[$name]['end']         = microtime(true);
        $this->timers[$name]['memory_end']  = memory_get_usage(true);
        $this->timers[$name]['duration']    = $this->timers[$name]['end'] - $this->timers[$name]['start'];
        $this->timers[$name]['memory_used'] = $this->timers[$name]['memory_end'] - $this->timers[$name]['memory_start'];

        return $this->timers[$name];
    }

    /**
     * Log a database query
     */
    public function logQuery($sql, $duration, $params = [])
    {
        if (! $this->enabled) {
            return;
        }

        $this->queries[] = [
            'sql'       => $sql,
            'duration'  => $duration,
            'params'    => $params,
            'timestamp' => microtime(true),
            'memory'    => memory_get_usage(true),
        ];
    }

    /**
     * Log a custom metric
     */
    public function logMetric($name, $value, $unit = '')
    {
        if (! $this->enabled) {
            return;
        }

        $this->metrics[$name] = [
            'value'     => $value,
            'unit'      => $unit,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get current performance metrics
     */
    public function getMetrics()
    {
        $currentTime   = microtime(true);
        $currentMemory = memory_get_usage(true);

        return [
            'execution_time'     => round(($currentTime - $this->startTime) * 1000, 2), // ms
            'memory_usage'       => $this->formatBytes($currentMemory),
            'memory_peak'        => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_delta'       => $this->formatBytes($currentMemory - $this->memoryStart),
            'query_count'        => count($this->queries),
            'slow_queries'       => count(array_filter($this->queries, function ($q) {
                return $q['duration'] > 0.1; // > 100ms
            })),
            'total_query_time'   => round(array_sum(array_column($this->queries, 'duration')) * 1000, 2),
            'average_query_time' => count($this->queries) > 0 ?
            round((array_sum(array_column($this->queries, 'duration')) / count($this->queries)) * 1000, 2) : 0,
        ];
    }

    /**
     * Get detailed query analysis
     */
    public function getQueryAnalysis()
    {
        if (! $this->enabled) {
            return [];
        }

        $analysis = [
            'total_queries' => count($this->queries),
            'slow_queries'  => [],
            'query_types'   => [],
            'total_time'    => 0,
        ];

        foreach ($this->queries as $query) {
            $analysis['total_time'] += $query['duration'];

            // Identify slow queries (>100ms)
            if ($query['duration'] > 0.1) {
                $analysis['slow_queries'][] = [
                    'sql'       => substr($query['sql'], 0, 100) . '...',
                    'duration'  => round($query['duration'] * 1000, 2) . 'ms',
                    'timestamp' => date('H:i:s', $query['timestamp']),
                ];
            }

            // Categorize query types
            $type                           = $this->getQueryType($query['sql']);
            $analysis['query_types'][$type] = ($analysis['query_types'][$type] ?? 0) + 1;
        }

        return $analysis;
    }

    /**
     * Log final metrics on script shutdown
     */
    public function logFinalMetrics()
    {
        if (! $this->enabled) {
            return;
        }

        $metrics       = $this->getMetrics();
        $queryAnalysis = $this->getQueryAnalysis();

        $logData = [
            'timestamp'   => date('Y-m-d H:i:s'),
            'url'         => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'method'      => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'localhost',
            'performance' => $metrics,
            'queries'     => $queryAnalysis,
        ];

        // Log to file
        file_put_contents(
            $this->logFile,
            json_encode($logData) . "\n",
            FILE_APPEND | LOCK_EX
        );

        // Log slow pages (>1000ms) to separate file
        if ($metrics['execution_time'] > 1000) {
            $slowLogFile = __DIR__ . '/../logs/slow_pages.log';
            file_put_contents(
                $slowLogFile,
                json_encode($logData) . "\n",
                FILE_APPEND | LOCK_EX
            );
        }
    }

    /**
     * Generate performance report
     */
    public function generateReport($hours = 24)
    {
        $logFile = $this->logFile;
        if (! file_exists($logFile)) {
            return ['error' => 'No performance data available'];
        }

        $lines      = file($logFile, FILE_IGNORE_NEW_LINES);
        $cutoffTime = time() - ($hours * 3600);
        $data       = [];

        foreach (array_reverse($lines) as $line) {
            $entry = json_decode($line, true);
            if ($entry && strtotime($entry['timestamp']) >= $cutoffTime) {
                $data[] = $entry;
            }
        }

        if (empty($data)) {
            return ['error' => 'No data for specified time period'];
        }

        // Calculate statistics
        $executionTimes = array_column(array_column($data, 'performance'), 'execution_time');
        $queryCounts    = array_column(array_column($data, 'performance'), 'query_count');
        $slowQueries    = array_column(array_column($data, 'queries'), 'slow_queries');

        $report = [
            'period'                      => $hours . ' hours',
            'total_requests'              => count($data),
            'average_response_time'       => round(array_sum($executionTimes) / count($executionTimes), 2) . 'ms',
            'max_response_time'           => max($executionTimes) . 'ms',
            'min_response_time'           => min($executionTimes) . 'ms',
            'average_queries_per_request' => round(array_sum($queryCounts) / count($queryCounts), 1),
            'slow_requests'               => count(array_filter($executionTimes, function ($time) {
                return $time > 1000;
            })),
            'slowest_pages'               => $this->getSlowPages($data, 5),
            'most_accessed_pages'         => $this->getMostAccessedPages($data, 5),
            'query_performance'           => $this->analyzeQueryPerformance($data),
        ];

        return $report;
    }

    /**
     * Get performance dashboard data
     */
    public function getDashboardData()
    {
        return [
            'current_metrics' => $this->getMetrics(),
            'recent_report'   => $this->generateReport(1), // Last hour
            'system_info'     => [
                'php_version'        => PHP_VERSION,
                'memory_limit'       => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'server_software'    => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            ],
        ];
    }

    /**
     * Clear old log files (older than 30 days)
     */
    public function cleanupLogs()
    {
        $files = [
            $this->logFile,
            __DIR__ . '/../logs/slow_pages.log',
        ];

        foreach ($files as $file) {
            if (file_exists($file) && filemtime($file) < (time() - (30 * 24 * 3600))) {
                unlink($file);
            }
        }
    }

    /**
     * Helper methods
     */
    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }

    private function getQueryType($sql)
    {
        $sql = trim(strtoupper($sql));
        if (strpos($sql, 'SELECT') === 0) {
            return 'SELECT';
        }

        if (strpos($sql, 'INSERT') === 0) {
            return 'INSERT';
        }

        if (strpos($sql, 'UPDATE') === 0) {
            return 'UPDATE';
        }

        if (strpos($sql, 'DELETE') === 0) {
            return 'DELETE';
        }

        return 'OTHER';
    }

    private function getSlowPages($data, $limit)
    {
        usort($data, function ($a, $b) {
            return $b['performance']['execution_time'] <=> $a['performance']['execution_time'];
        });

        return array_slice(array_map(function ($item) {
            return [
                'url'       => $item['url'],
                'time'      => $item['performance']['execution_time'] . 'ms',
                'queries'   => $item['performance']['query_count'],
                'timestamp' => $item['timestamp'],
            ];
        }, $data), 0, $limit);
    }

    private function getMostAccessedPages($data, $limit)
    {
        $pages = [];
        foreach ($data as $item) {
            $url         = $item['url'];
            $pages[$url] = ($pages[$url] ?? 0) + 1;
        }

        arsort($pages);
        return array_slice($pages, 0, $limit, true);
    }

    private function analyzeQueryPerformance($data)
    {
        $totalQueries     = 0;
        $totalSlowQueries = 0;
        $queryTypes       = [];

        foreach ($data as $item) {
            $totalQueries += $item['performance']['query_count'];
            $totalSlowQueries += $item['performance']['slow_queries'];

            foreach ($item['queries']['query_types'] as $type => $count) {
                $queryTypes[$type] = ($queryTypes[$type] ?? 0) + $count;
            }
        }

        return [
            'total_queries'         => $totalQueries,
            'slow_queries'          => $totalSlowQueries,
            'slow_query_percentage' => $totalQueries > 0 ? round(($totalSlowQueries / $totalQueries) * 100, 1) : 0,
            'query_types'           => $queryTypes,
        ];
    }
}

/**
 * Helper function to get performance monitor instance
 */
function getPerformanceMonitor()
{
    return PerformanceMonitor::getInstance();
}

/**
 * Helper function to start a timer
 */
function perfStart($name)
{
    return PerformanceMonitor::getInstance()->startTimer($name);
}

/**
 * Helper function to end a timer
 */
function perfEnd($name)
{
    return PerformanceMonitor::getInstance()->endTimer($name);
}

/**
 * Helper function to log a metric
 */
function perfLog($name, $value, $unit = '')
{
    return PerformanceMonitor::getInstance()->logMetric($name, $value, $unit);
}
