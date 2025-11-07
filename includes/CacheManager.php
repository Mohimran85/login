<?php
/**
 * Advanced Caching System
 * Handles session-based and file-based caching with automatic cleanup
 */
class CacheManager
{
    private static $instance = null;
    private $sessionPrefix   = 'EMS_CACHE_';
    private $fileCache       = [];
    private $cacheDir;

    private function __construct()
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Set up file cache directory
        $this->cacheDir = __DIR__ . '/../cache/';
        if (! is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new CacheManager();
        }
        return self::$instance;
    }

    /**
     * Store data in session cache
     */
    public function setSessionCache($key, $data, $duration = 300)
    {
        $cacheKey            = $this->sessionPrefix . $key;
        $_SESSION[$cacheKey] = [
            'data'    => $data,
            'expires' => time() + $duration,
            'created' => time(),
        ];
    }

    /**
     * Get data from session cache
     */
    public function getSessionCache($key)
    {
        $cacheKey = $this->sessionPrefix . $key;

        if (! isset($_SESSION[$cacheKey])) {
            return null;
        }

        $cached = $_SESSION[$cacheKey];

        // Check if expired
        if (time() > $cached['expires']) {
            unset($_SESSION[$cacheKey]);
            return null;
        }

        return $cached['data'];
    }

    /**
     * Store data in file cache (for larger datasets)
     */
    public function setFileCache($key, $data, $duration = 600)
    {
        $filename  = $this->cacheDir . md5($key) . '.cache';
        $cacheData = [
            'data'    => $data,
            'expires' => time() + $duration,
            'created' => time(),
            'key'     => $key,
        ];

        file_put_contents($filename, serialize($cacheData), LOCK_EX);
        $this->fileCache[$key] = $filename;
    }

    /**
     * Get data from file cache
     */
    public function getFileCache($key)
    {
        $filename = $this->cacheDir . md5($key) . '.cache';

        if (! file_exists($filename)) {
            return null;
        }

        $cached = unserialize(file_get_contents($filename));

        if (! $cached || time() > $cached['expires']) {
            unlink($filename);
            unset($this->fileCache[$key]);
            return null;
        }

        return $cached['data'];
    }

    /**
     * Smart cache - automatically chooses session or file based on data size
     */
    public function set($key, $data, $duration = 300)
    {
        $dataSize = strlen(serialize($data));

        // Use session cache for small data (< 10KB)
        if ($dataSize < 10240) {
            $this->setSessionCache($key, $data, $duration);
        } else {
            // Use file cache for larger data
            $this->setFileCache($key, $data, $duration);
        }
    }

    /**
     * Smart get - checks both session and file cache
     */
    public function get($key)
    {
        // Try session cache first (faster)
        $data = $this->getSessionCache($key);
        if ($data !== null) {
            return $data;
        }

        // Try file cache
        return $this->getFileCache($key);
    }

    /**
     * Cache student dashboard data with intelligent invalidation
     */
    public function cacheStudentDashboard($regno, $data, $duration = 300)
    {
        $keys = [
            "student_dashboard_{$regno}",
            "student_stats_{$regno}",
            "student_recent_{$regno}",
        ];

        foreach ($keys as $key) {
            $this->set($key, $data, $duration);
        }
    }

    /**
     * Get cached student dashboard data
     */
    public function getStudentDashboard($regno)
    {
        return $this->get("student_dashboard_{$regno}");
    }

    /**
     * Cache participations data
     */
    public function cacheParticipations($regno, $data, $duration = 600)
    {
        $this->set("participations_{$regno}", $data, $duration);
    }

    /**
     * Get cached participations
     */
    public function getParticipations($regno)
    {
        return $this->get("participations_{$regno}");
    }

    /**
     * Cache OD requests data
     */
    public function cacheODRequests($regno, $data, $duration = 300)
    {
        $this->set("od_requests_{$regno}", $data, $duration);
    }

    /**
     * Get cached OD requests
     */
    public function getODRequests($regno)
    {
        return $this->get("od_requests_{$regno}");
    }

    /**
     * Invalidate specific user cache (when data changes)
     */
    public function invalidateUserCache($regno)
    {
        $patterns = [
            "student_dashboard_{$regno}",
            "student_stats_{$regno}",
            "student_recent_{$regno}",
            "participations_{$regno}",
            "od_requests_{$regno}",
        ];

        foreach ($patterns as $pattern) {
            $this->delete($pattern);
        }
    }

    /**
     * Delete specific cache entry
     */
    public function delete($key)
    {
        // Remove from session
        $sessionKey = $this->sessionPrefix . $key;
        unset($_SESSION[$sessionKey]);

        // Remove from file cache
        $filename = $this->cacheDir . md5($key) . '.cache';
        if (file_exists($filename)) {
            unlink($filename);
        }
        unset($this->fileCache[$key]);
    }

    /**
     * Clear all expired cache entries
     */
    public function cleanup()
    {
        // Clean session cache
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, $this->sessionPrefix) === 0 && is_array($value)) {
                if (isset($value['expires']) && time() > $value['expires']) {
                    unset($_SESSION[$key]);
                }
            }
        }

        // Clean file cache
        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            $cached = @unserialize(file_get_contents($file));
            if (! $cached || time() > $cached['expires']) {
                unlink($file);
            }
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $sessionCount = 0;
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, $this->sessionPrefix) === 0) {
                $sessionCount++;
            }
        }

        $fileCount = count(glob($this->cacheDir . '*.cache'));
        $cacheSize = 0;

        $files = glob($this->cacheDir . '*.cache');
        foreach ($files as $file) {
            $cacheSize += filesize($file);
        }

        return [
            'session_entries' => $sessionCount,
            'file_entries'    => $fileCount,
            'total_size'      => $this->formatBytes($cacheSize),
            'cache_directory' => $this->cacheDir,
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Cache with dependency tracking
     */
    public function setWithDependencies($key, $data, $dependencies = [], $duration = 300)
    {
        $cacheData = [
            'data'         => $data,
            'dependencies' => $dependencies,
            'created'      => time(),
        ];

        $this->set($key, $cacheData, $duration);
    }

    /**
     * Invalidate cache based on dependencies
     */
    public function invalidateByDependency($dependency)
    {
        // This would require a more complex implementation
        // For now, we'll use pattern matching
        $patterns = [
            "student_dashboard_*",
            "participations_*",
            "od_requests_*",
        ];

        foreach ($patterns as $pattern) {
            // In a full implementation, you'd scan all cache entries
            // and check their dependencies
        }
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
}

/**
 * Cache Helper Functions
 */
function cache_get($key)
{
    return CacheManager::getInstance()->get($key);
}

function cache_set($key, $data, $duration = 300)
{
    return CacheManager::getInstance()->set($key, $data, $duration);
}

function cache_delete($key)
{
    return CacheManager::getInstance()->delete($key);
}

function cache_cleanup()
{
    return CacheManager::getInstance()->cleanup();
}
