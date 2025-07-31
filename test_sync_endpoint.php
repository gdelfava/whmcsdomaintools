<?php
// require_once 'auth.php';
// If needed, use:
// require_once 'auth_v2.php';
require_once 'domain_sync.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint

echo "<h1>🔍 Testing Sync Endpoint</h1>";

// Test the sync endpoint directly
$userEmail = $_SESSION['user_email'] ?? '';
if (empty($userEmail)) {
    echo "❌ ERROR: No user email found in session";
    exit;
}

echo "<h2>Testing DomainSync Class</h2>";

try {
    $sync = new DomainSync($userEmail);
    echo "✅ DomainSync class instantiated successfully<br>";
    
    // Test a small batch sync
    echo "<h3>Testing Small Batch Sync (5 domains)</h3>";
    $result = $sync->syncBatch(1, 5);
    
    echo "<h4>Sync Result:</h4>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
    
    if ($result['success']) {
        echo "✅ Sync completed successfully!<br>";
        echo "📊 Domains found: " . $result['data']['domains_found'] . "<br>";
        echo "📊 Domains processed: " . $result['data']['domains_processed'] . "<br>";
        echo "📊 Domains added: " . $result['data']['domains_added'] . "<br>";
        echo "📊 Domains updated: " . $result['data']['domains_updated'] . "<br>";
        if ($result['data']['errors'] > 0) {
            echo "⚠️ Errors: " . $result['data']['errors'] . "<br>";
        }
    } else {
        echo "❌ Sync failed: " . ($result['data']['error_message'] ?? 'Unknown error') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
}

echo "<h2>Testing AJAX Endpoint</h2>";

// Simulate the AJAX request
$_POST['action'] = 'sync_batch';
$_POST['batch_number'] = '1';
$_POST['batch_size'] = '5';

echo "<h3>Simulating AJAX Request:</h3>";
echo "<p>Action: " . $_POST['action'] . "</p>";
echo "<p>Batch Number: " . $_POST['batch_number'] . "</p>";
echo "<p>Batch Size: " . $_POST['batch_size'] . "</p>";

// Capture the output
ob_start();
include 'domain_sync.php';
$output = ob_get_clean();

echo "<h4>Raw Response:</h4>";
echo "<div style='background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; white-space: pre-wrap;'>";
echo htmlspecialchars($output);
echo "</div>";

// Try to decode as JSON
$jsonData = json_decode($output, true);
if ($jsonData === null) {
    echo "<h4>❌ JSON Decode Failed:</h4>";
    echo "<p>Error: " . json_last_error_msg() . "</p>";
    
    // Check if it's HTML
    if (strpos($output, '<!DOCTYPE') !== false || strpos($output, '<html') !== false) {
        echo "<p><strong>⚠️ HTML Detected:</strong> The endpoint is returning HTML instead of JSON</p>";
    }
} else {
    echo "<h4>✅ JSON Decode Successful:</h4>";
    echo "<pre>" . json_encode($jsonData, JSON_PRETTY_PRINT) . "</pre>";
}

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 