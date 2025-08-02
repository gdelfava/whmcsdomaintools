<?php
// require_once 'auth.php';
// require_once 'database.php';
// If needed, use:
// require_once 'auth_v2.php';
// require_once 'database_v2.php';

echo "<h1>🔍 Session & Sync Debug</h1>";

echo "<h2>Session Information:</h2>";
echo "<ul>";
echo "<li><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</li>";
echo "<li><strong>Session ID:</strong> " . session_id() . "</li>";
echo "<li><strong>Logged In:</strong> " . (isLoggedIn() ? 'Yes' : 'No') . "</li>";
if (isset($_SESSION['user_email'])) {
    echo "<li><strong>User Email:</strong> " . htmlspecialchars($_SESSION['user_email']) . "</li>";
}
echo "<li><strong>Firebase Token:</strong> " . (isset($_SESSION['firebase_token']) ? 'Present' : 'Missing') . "</li>";
echo "</ul>";

echo "<h2>Database Status:</h2>";
try {
    $db = Database::getInstance();
    
    // Get domain count
    $domainCount = $db->getDomainCount($_SESSION['user_email'] ?? '');
    echo "<p><strong>Total Domains:</strong> $domainCount</p>";
    
    // Get recent domains
    $recentDomains = $db->getDomains($_SESSION['user_email'] ?? '', 1, 10);
    echo "<h3>Recent Domains (last 10):</h3>";
    if (!empty($recentDomains)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Domain Name</th><th>Status</th><th>Last Synced</th><th>Batch Number</th></tr>";
        foreach ($recentDomains as $domain) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($domain['domain_name']) . "</td>";
            echo "<td>" . htmlspecialchars($domain['status']) . "</td>";
            echo "<td>" . htmlspecialchars($domain['last_synced']) . "</td>";
            echo "<td>" . htmlspecialchars($domain['batch_number']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No domains found</p>";
    }
    
    // Get sync logs
    echo "<h3>Recent Sync Logs:</h3>";
    $syncLogs = $db->getRecentSyncLogs(5);
    if (!empty($syncLogs)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Batch</th><th>Status</th><th>Domains Found</th><th>Processed</th><th>Errors</th><th>Started</th><th>Error Message</th></tr>";
        foreach ($syncLogs as $log) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($log['batch_number']) . "</td>";
            echo "<td>" . htmlspecialchars($log['status']) . "</td>";
            echo "<td>" . htmlspecialchars($log['domains_found']) . "</td>";
            echo "<td>" . htmlspecialchars($log['domains_processed']) . "</td>";
            echo "<td>" . htmlspecialchars($log['errors']) . "</td>";
            echo "<td>" . htmlspecialchars($log['sync_started']) . "</td>";
            echo "<td>" . htmlspecialchars($log['error_message'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No sync logs found</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Testing Authentication:</h2>";
try {
    requireAuth();
    echo "<p>✅ Authentication passed - user is properly logged in</p>";
} catch (Exception $e) {
    echo "<p>❌ Authentication failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Testing Domain Sync Class:</h2>";
try {
    $userEmail = $_SESSION['user_email'] ?? '';
    if (!empty($userEmail)) {
        require_once 'domain_sync.php';
        $sync = new DomainSync($userEmail);
        echo "<p>✅ DomainSync class instantiated successfully</p>";
        
        // Test getting last sync info
        $lastSync = $sync->getLastSyncInfo();
        if ($lastSync) {
            echo "<h3>Last Sync Information:</h3>";
            echo "<ul>";
            echo "<li><strong>Batch Number:</strong> " . htmlspecialchars($lastSync['batch_number']) . "</li>";
            echo "<li><strong>Status:</strong> " . htmlspecialchars($lastSync['status']) . "</li>";
            echo "<li><strong>Domains Found:</strong> " . htmlspecialchars($lastSync['domains_found']) . "</li>";
            echo "<li><strong>Domains Processed:</strong> " . htmlspecialchars($lastSync['domains_processed']) . "</li>";
            echo "<li><strong>Errors:</strong> " . htmlspecialchars($lastSync['errors']) . "</li>";
            echo "<li><strong>Started:</strong> " . htmlspecialchars($lastSync['sync_started']) . "</li>";
            if (!empty($lastSync['error_message'])) {
                echo "<li><strong>Error Message:</strong> " . htmlspecialchars($lastSync['error_message']) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No previous sync found</p>";
        }
    } else {
        echo "<p>❌ No user email in session</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ DomainSync error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Browser Information:</h2>";
echo "<ul>";
echo "<li><strong>User Agent:</strong> " . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Not available') . "</li>";
echo "<li><strong>Referer:</strong> " . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'Not available') . "</li>";
echo "<li><strong>Request Method:</strong> " . htmlspecialchars($_SERVER['REQUEST_METHOD']) . "</li>";
echo "</ul>";

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 