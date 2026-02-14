<?php
/**
 * Advanced Database Connection Manager
 * Implements singleton pattern with connection pooling and query optimization
 */
class DatabaseManager
{
    private static $instance = null;
    private $connection;
    private $config;
    private $queryCache  = [];
    private $cacheExpiry = 300; // 5 minutes

    // Database configuration
    private function __construct()
    {
        // Load configuration from environment or config file
        $this->config = [
            'host'     => getenv('DB_HOST') ?: 'localhost',
            'username' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASS') ?: '',
            'database' => getenv('DB_NAME') ?: 'event_management_system',
            'charset'  => getenv('DB_CHARSET') ?: 'utf8mb4',
        ];

        // Validate required configuration
        if (empty($this->config['host']) || empty($this->config['database'])) {
            error_log("CRITICAL: Database configuration is incomplete");
            throw new Exception("Database configuration error. Please contact the administrator.");
        }

        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new DatabaseManager();
        }
        return self::$instance;
    }

    /**
     * Establish database connection with optimized settings
     */
    private function connect()
    {
        try {
            $this->connection = new mysqli(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database']
            );

            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }

            // Set charset and optimization settings
            $this->connection->set_charset($this->config['charset']);

            // Optimize connection settings
            $this->connection->query("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            $this->connection->query("SET SESSION wait_timeout=28800");
            $this->connection->query("SET SESSION interactive_timeout=28800");

        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get database connection
     */
    public function getConnection()
    {
        // Check if connection is still alive
        if (! $this->connection->ping()) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Execute prepared statement with caching
     */
    public function executeQuery($sql, $params = [], $types = '', $cacheKey = null, $cacheDuration = null)
    {
        $startTime = microtime(true);

        // Check cache first
        if ($cacheKey && isset($this->queryCache[$cacheKey])) {
            $cached = $this->queryCache[$cacheKey];
            if (time() - $cached['timestamp'] < ($cacheDuration ?? $this->cacheExpiry)) {
                return $cached['data'];
            }
        }

        try {
            $stmt = $this->connection->prepare($sql);
            if (! $stmt) {
                throw new Exception("Prepare failed: " . $this->connection->error);
            }

            if (! empty($params)) {
                if (empty($types)) {
                    // Auto-detect parameter types
                    $types = '';
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } else {
                            $types .= 's';
                        }
                    }
                }
                if (! $stmt->bind_param($types, ...$params)) {
                    $stmt->close();
                    throw new Exception("Bind failed: " . $stmt->error);
                }
            }

            if (! $stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception("Execute failed: " . $error);
            }

            $result  = $stmt->get_result();

            // Handle different result types
            $data = null;
            if ($result === false) {
                // INSERT, UPDATE, DELETE queries
                $data = [
                    'affected_rows' => $stmt->affected_rows,
                    'insert_id'     => $stmt->insert_id,
                ];
            } else {
                // SELECT queries
                $data = $result->fetch_all(MYSQLI_ASSOC);
            }

            $stmt->close();

            // Cache the result if cache key provided
            if ($cacheKey && $result !== false) {
                $this->queryCache[$cacheKey] = [
                    'data'      => $data,
                    'timestamp' => time(),
                ];
            }

            // Log slow queries (>100ms)
            $executionTime = (microtime(true) - $startTime) * 1000;
            if ($executionTime > 100) {
                error_log("Slow query ({$executionTime}ms): " . $sql);
            }

            return $data;

        } catch (Exception $e) {
            error_log("Query error: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    /**
     * Execute multiple queries in a transaction
     */
    public function executeTransaction($queries)
    {
        $this->connection->begin_transaction();

        try {
            $results = [];
            foreach ($queries as $query) {
                $result = $this->executeQuery(
                    $query['sql'],
                    $query['params'] ?? [],
                    $query['types'] ?? ''
                );
                $results[] = $result;
            }

            $this->connection->commit();
            return $results;

        } catch (Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Get optimized dashboard data in single query
     */
    public function getStudentDashboardData($regno)
    {
        $cacheKey = "student_dashboard_" . $regno;

        $sql = "
            SELECT
                -- Basic student info
                s.name, s.regno,

                -- Event statistics (only approved events count)
                (SELECT COUNT(*) FROM student_event_register WHERE regno = ? AND verification_status = 'Approved') as total_events,
                (SELECT COUNT(*) FROM student_event_register WHERE regno = ? AND verification_status = 'Approved' AND prize IN ('First', 'Second', 'Third')) as events_won,

                -- OD statistics
                (SELECT COUNT(*) FROM od_requests WHERE student_regno = ?) as total_od_requests,
                (SELECT COUNT(*) FROM od_requests WHERE student_regno = ? AND status = 'pending') as pending_od,
                (SELECT COUNT(*) FROM od_requests WHERE student_regno = ? AND status = 'approved') as approved_od,
                (SELECT COUNT(*) FROM od_requests WHERE student_regno = ? AND status = 'rejected') as rejected_od

            FROM student_register s WHERE s.regno = ?
        ";

        $params = [$regno, $regno, $regno, $regno, $regno, $regno, $regno];
        $result = $this->executeQuery($sql, $params, 'sssssss', $cacheKey, 300);

        return $result[0] ?? null;
    }

    /**
     * Get recent activities with caching
     */
    public function getRecentActivities($regno, $limit = 5)
    {
        $cacheKey = "recent_activities_{$regno}_{$limit}";

        $sql = "SELECT event_name, event_type, start_date, end_date, no_of_days, prize
                FROM student_event_register
                WHERE regno = ? AND verification_status = 'Approved'
                ORDER BY start_date DESC, id DESC
                LIMIT ?";

        return $this->executeQuery($sql, [$regno, $limit], 'si', $cacheKey, 180);
    }

    /**
     * Get event type breakdown with caching
     */
    public function getEventTypeBreakdown($regno, $limit = 8)
    {
        $cacheKey = "event_types_{$regno}_{$limit}";

        $sql = "SELECT event_type, COUNT(*) as count
                FROM student_event_register
                WHERE regno = ?
                GROUP BY event_type
                ORDER BY count DESC
                LIMIT ?";

        return $this->executeQuery($sql, [$regno, $limit], 'si', $cacheKey, 300);
    }

    /**
     * Get recent OD requests with caching
     */
    public function getRecentODRequests($regno, $limit = 3)
    {
        $cacheKey = "recent_od_{$regno}_{$limit}";

        $sql = "SELECT event_name, status, request_date, event_date
                FROM od_requests
                WHERE student_regno = ?
                ORDER BY request_date DESC
                LIMIT ?";

        return $this->executeQuery($sql, [$regno, $limit], 'si', $cacheKey, 120);
    }

    /**
     * Clear cache for specific key or all cache
     */
    public function clearCache($key = null)
    {
        if ($key) {
            unset($this->queryCache[$key]);
        } else {
            $this->queryCache = [];
        }
    }

    /**
     * Get connection statistics
     */
    public function getStats()
    {
        $sql    = "SHOW STATUS LIKE 'Connections'";
        $result = $this->connection->query($sql);
        $stats  = [];

        while ($row = $result->fetch_assoc()) {
            $stats[$row['Variable_name']] = $row['Value'];
        }

        return [
            'cache_entries' => count($this->queryCache),
            'mysql_stats'   => $stats,
        ];
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Close connection on destruct
     */
    public function __destruct()
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
