<?php
/**
 * Cache Clearing Script
 * 
 * This script clears the domain cache to force a refresh of the domain list
 * with proper alphabetical sorting.
 */

require_once 'auth.php';
require_once 'cache.php';

// Require authentication
requireAuth();

$message = '';
$messageType = '';

// Handle cache clearing
if (isset($_POST['clear_cache']) || isset($_GET['clear_cache_direct'])) {
    try {
        $cache = new SimpleCache();
        $userEmail = $_SESSION['user_email'] ?? 'unknown';
        
        // Clear all domain-related caches for this user
        $cache->clearUserCache($userEmail);
        
        $message = 'Cache cleared successfully! The domain list will be refreshed with proper alphabetical sorting.';
        $messageType = 'success';
        
        // If using direct link, redirect to avoid resubmission
        if (isset($_GET['clear_cache_direct'])) {
            header('Location: clear-cache.php?success=1');
            exit;
        }
    } catch (Exception $e) {
        $message = 'Error clearing cache: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle success message from redirect
if (isset($_GET['success'])) {
    $message = 'Cache cleared successfully! The domain list will be refreshed with proper alphabetical sorting.';
    $messageType = 'success';
}

// Get cache statistics
$cache = new SimpleCache();
$userEmail = $_SESSION['user_email'] ?? 'unknown';
$cacheStats = $cache->getStats($userEmail);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear Cache - WHMCS Domain Tools</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 font-sans min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Cache Management</h1>
            <p class="text-gray-600">Clear cached domain data to refresh the domain list with proper alphabetical sorting.</p>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
                <div class="flex items-center space-x-2">
                    <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Cache Information -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                <i data-lucide="database" class="w-5 h-5 text-blue-600"></i>
                <span>Cache Statistics</span>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Total Cache Files</div>
                    <div class="text-2xl font-bold text-gray-900"><?= $cacheStats['total_files'] ?? 0 ?></div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Cache Size</div>
                    <div class="text-2xl font-bold text-gray-900"><?= $cacheStats['total_size'] ?? '0 KB' ?></div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Expired Files</div>
                    <div class="text-2xl font-bold text-gray-900"><?= $cacheStats['expired_files'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <!-- Clear Cache Form -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                <i data-lucide="refresh-cw" class="w-5 h-5 text-blue-600"></i>
                <span>Clear Domain Cache</span>
            </h2>
            
            <div class="mb-6">
                <p class="text-gray-600 mb-4">
                    This will clear all cached domain data and force a fresh fetch from your WHMCS API. 
                    The domain list will be refreshed with proper alphabetical sorting.
                </p>
                
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start space-x-3">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600 mt-0.5"></i>
                        <div>
                            <h3 class="font-semibold text-yellow-800 mb-1">Important Note</h3>
                            <p class="text-sm text-yellow-700">
                                Clearing the cache will temporarily slow down the next domain list load as it fetches fresh data from your WHMCS API. 
                                This is normal and will improve performance on subsequent loads.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="space-y-4">
                <!-- Form method -->
                <form method="POST" class="inline-block">
                    <button 
                        type="submit" 
                        name="clear_cache" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center space-x-2"
                    >
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                        <span>Clear Domain Cache (Form)</span>
                    </button>
                </form>
                
                <!-- Direct link method -->
                <div class="mt-4">
                    <a 
                        href="?clear_cache_direct=1" 
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center space-x-2 inline-block"
                    >
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                        <span>Clear Domain Cache (Direct Link)</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="mt-8 flex space-x-4">
            <a href="index.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors">
                ← Back to Dashboard
            </a>
            <a href="main_page.php?view=nameservers" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                Go to Nameservers
            </a>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html> 