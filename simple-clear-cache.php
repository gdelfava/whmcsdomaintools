<?php
/**
 * Simple Cache Clearing Page
 * 
 * A very simple page with just a direct link to clear the cache.
 */

require_once 'auth.php';
require_once 'cache.php';

// Require authentication
requireAuth();

$message = '';
$messageType = '';

// Handle cache clearing via GET parameter
if (isset($_GET['clear'])) {
    try {
        $cache = new SimpleCache();
        $userEmail = $_SESSION['user_email'] ?? 'unknown';
        
        // Clear all domain-related caches for this user
        $cache->clearUserCache($userEmail);
        
        $message = 'Cache cleared successfully! The domain list will be refreshed with proper alphabetical sorting.';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error clearing cache: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear Cache - WHMCS Domain Tools</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1f2937;
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header p {
            color: #6b7280;
            margin: 0;
            font-size: 14px;
        }
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .message.success {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .message.error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #1d4ed8;
        }
        .button.danger {
            background-color: #dc2626;
        }
        .button.danger:hover {
            background-color: #b91c1c;
        }
        .nav {
            margin-top: 30px;
            text-align: center;
        }
        .nav a {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            margin: 0 10px;
        }
        .nav a:hover {
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Clear Domain Cache</h1>
            <p>Clear cached domain data to refresh the domain list with proper alphabetical sorting</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="?clear=1" class="button danger">
                🗑️ Clear Domain Cache
            </a>
        </div>

        <div class="nav">
            <a href="index.php">← Back to Dashboard</a>
            <a href="main_page.php?view=nameservers">Go to Nameservers</a>
        </div>
    </div>
</body>
</html> 