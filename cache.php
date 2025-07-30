<?php
/**
 * Simple file-based cache system for API responses
 */
class SimpleCache {
    private $cacheDir;
    
    public function __construct($cacheDir = 'cache') {
        $this->cacheDir = $cacheDir;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
            
            // Add .htaccess to prevent direct access
            file_put_contents($this->cacheDir . '/.htaccess', "Deny from all\n");
        }
    }
    
    /**
     * Get cache key based on user and request parameters
     */
    private function getCacheKey($key, $userEmail) {
        return md5($userEmail . '_' . $key);
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFilePath($cacheKey) {
        return $this->cacheDir . '/' . $cacheKey . '.cache';
    }
    
    /**
     * Store data in cache
     */
    public function set($key, $data, $userEmail, $ttl = 300) {
        $cacheKey = $this->getCacheKey($key, $userEmail);
        $filePath = $this->getCacheFilePath($cacheKey);
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($filePath, serialize($cacheData)) !== false;
    }
    
    /**
     * Get data from cache
     */
    public function get($key, $userEmail) {
        $cacheKey = $this->getCacheKey($key, $userEmail);
        $filePath = $this->getCacheFilePath($cacheKey);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }
        
        $cacheData = unserialize($contents);
        if ($cacheData === false) {
            return null;
        }
        
        // Check if cache has expired
        if (time() > $cacheData['expires']) {
            $this->delete($key, $userEmail);
            return null;
        }
        
        return $cacheData['data'];
    }
    
    /**
     * Delete cache entry
     */
    public function delete($key, $userEmail) {
        $cacheKey = $this->getCacheKey($key, $userEmail);
        $filePath = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return true;
    }
    
    /**
     * Check if cache entry exists and is valid
     */
    public function has($key, $userEmail) {
        return $this->get($key, $userEmail) !== null;
    }
    
    /**
     * Clear all cache for a user
     */
    public function clearUserCache($userEmail) {
        $files = glob($this->cacheDir . '/*.cache');
        $userPrefix = md5($userEmail . '_');
        $cleared = 0;
        
        foreach ($files as $file) {
            $filename = basename($file, '.cache');
            // Check if this cache file belongs to the user by checking if the filename starts with the user prefix
            if (strpos($filename, substr($userPrefix, 0, 8)) === 0) {
                if (unlink($file)) {
                    $cleared++;
                }
            }
        }
        
        return $cleared;
    }
    
    /**
     * Clear expired cache entries
     */
    public function clearExpired() {
        $files = glob($this->cacheDir . '/*.cache');
        $cleared = 0;
        
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) continue;
            
            $cacheData = unserialize($contents);
            if ($cacheData === false) continue;
            
            if (time() > $cacheData['expires']) {
                unlink($file);
                $cleared++;
            }
        }
        
        return $cleared;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats($userEmail = null) {
        $files = glob($this->cacheDir . '/*.cache');
        $stats = [
            'total_entries' => count($files),
            'user_entries' => 0,
            'total_size' => 0,
            'expired_entries' => 0
        ];
        
        foreach ($files as $file) {
            $stats['total_size'] += filesize($file);
            
            // Check if user-specific cache
            if ($userEmail) {
                $filename = basename($file, '.cache');
                $userPrefix = md5($userEmail . '_');
                if (strpos($filename, substr($userPrefix, 0, 8)) === 0) {
                    $stats['user_entries']++;
                }
            }
            
            // Check if expired
            $contents = file_get_contents($file);
            if ($contents !== false) {
                $cacheData = unserialize($contents);
                if ($cacheData !== false && time() > $cacheData['expires']) {
                    $stats['expired_entries']++;
                }
            }
        }
        
        return $stats;
    }
}

/**
 * Cache helper functions
 */

/**
 * Get cached API response or make new request
 */
function getCachedApiResponse($cacheKey, $userEmail, $apiCallback, $ttl = 300) {
    static $cache = null;
    
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    
    // Try to get from cache first
    $cachedData = $cache->get($cacheKey, $userEmail);
    if ($cachedData !== null) {
        return $cachedData;
    }
    
    // Make API call
    $response = $apiCallback();
    
    // Cache the response if successful
    if (isset($response['result']) && $response['result'] === 'success') {
        $cache->set($cacheKey, $response, $userEmail, $ttl);
    }
    
    return $response;
}

/**
 * Clear user cache (call when settings change)
 */
function clearUserCache($userEmail) {
    $cache = new SimpleCache();
    return $cache->clearUserCache($userEmail);
}

/**
 * Get cache stats for debugging
 */
function getCacheStats($userEmail = null) {
    $cache = new SimpleCache();
    return $cache->getStats($userEmail);
}
?> 