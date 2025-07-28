<?php
/**
 * Command Line Cache Clearing Script
 * 
 * Usage: php clear-cache-cli.php [user_email]
 */

require_once 'cache.php';

// Get user email from command line argument or use default
$userEmail = $argv[1] ?? 'unknown';

echo "=== Domain Cache Clearing Tool ===\n\n";

try {
    $cache = new SimpleCache();
    
    // Get cache statistics before clearing
    $cacheStats = $cache->getStats($userEmail);
    
    echo "Cache Statistics for user: $userEmail\n";
    echo "Total Cache Files: " . ($cacheStats['total_files'] ?? 0) . "\n";
    echo "Cache Size: " . ($cacheStats['total_size'] ?? '0 KB') . "\n";
    echo "Expired Files: " . ($cacheStats['expired_files'] ?? 0) . "\n\n";
    
    // Clear all domain-related caches for this user
    $cache->clearUserCache($userEmail);
    
    echo "✅ Cache cleared successfully!\n";
    echo "The domain list will be refreshed with proper alphabetical sorting on next load.\n\n";
    
    // Get updated cache statistics
    $updatedStats = $cache->getStats($userEmail);
    echo "Updated Cache Statistics:\n";
    echo "Total Cache Files: " . ($updatedStats['total_files'] ?? 0) . "\n";
    echo "Cache Size: " . ($updatedStats['total_size'] ?? '0 KB') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error clearing cache: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nCache clearing completed successfully!\n";
?> 