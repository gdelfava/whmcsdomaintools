<?php
/**
 * AJAX Cache Clearing Endpoint
 * 
 * Handles cache clearing requests from the modal interface.
 */

require_once 'auth.php';
require_once 'cache.php';

// Set JSON content type
header('Content-Type: application/json');

// Require authentication
try {
    requireAuth();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ]);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);
    exit;
}

$action = $input['action'];

// Handle get_stats action
if ($action === 'get_stats') {
    try {
        $cache = new SimpleCache();
        $userEmail = $_SESSION['user_email'] ?? 'unknown';
        
        // Get cache statistics
        $cacheStats = $cache->getStats($userEmail);
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_files' => $cacheStats['total_files'] ?? 0,
                'total_size' => $cacheStats['total_size'] ?? '0 KB',
                'expired_files' => $cacheStats['expired_files'] ?? 0
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error getting cache stats: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle clear_cache action
if ($action !== 'clear_cache') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);
    exit;
}

try {
    $cache = new SimpleCache();
    $userEmail = $_SESSION['user_email'] ?? 'unknown';
    
    // Clear all domain-related caches for this user
    $cache->clearUserCache($userEmail);
    
    // Get cache statistics for response
    $cacheStats = $cache->getStats($userEmail);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cache cleared successfully! The domain list will be refreshed with proper alphabetical sorting.',
        'stats' => [
            'total_files' => $cacheStats['total_files'] ?? 0,
            'total_size' => $cacheStats['total_size'] ?? '0 KB',
            'expired_files' => $cacheStats['expired_files'] ?? 0
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error clearing cache: ' . $e->getMessage()
    ]);
}
?> 