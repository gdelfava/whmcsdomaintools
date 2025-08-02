<?php
// require_once 'auth.php';
// If needed, use:
// require_once 'auth_v2.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint

echo "<h1>🔧 Testing Simple Sync Endpoint</h1>";

$userEmail = $_SESSION['user_email'] ?? '';
if (empty($userEmail)) {
    echo "❌ ERROR: No user email found in session";
    exit;
}

echo "<h2>Step 1: Test Simple Sync Endpoint Directly</h2>";

// Simulate the POST request that the frontend makes
$postData = [
    'batch_number' => '1',
    'batch_size' => '5'
];

echo "<p>Testing with parameters:</p>";
echo "<ul>";
echo "<li>Batch Number: " . $postData['batch_number'] . "</li>";
echo "<li>Batch Size: " . $postData['batch_size'] . "</li>";
echo "</ul>";

// Use cURL to test the endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8888/domain-tools/simple_sync.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

// Forward session cookies
$sessionName = session_name();
$sessionId = session_id();
curl_setopt($ch, CURLOPT_COOKIE, "$sessionName=$sessionId");

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>Response Details:</h3>";
echo "<ul>";
echo "<li><strong>HTTP Code:</strong> $httpCode</li>";
echo "<li><strong>Content Type:</strong> $contentType</li>";
if ($error) {
    echo "<li><strong>cURL Error:</strong> $error</li>";
}
echo "</ul>";

echo "<h3>Raw Response:</h3>";
echo "<div style='background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;'>";
echo htmlspecialchars($response);
echo "</div>";

// Test JSON decoding
echo "<h3>JSON Analysis:</h3>";
$jsonData = json_decode($response, true);
if ($jsonData === null) {
    echo "<p><strong>❌ JSON Decode Failed:</strong> " . json_last_error_msg() . "</p>";
    
    // Check for HTML
    if (strpos($response, '<!DOCTYPE') !== false || strpos($response, '<html') !== false) {
        echo "<p><strong>⚠️ HTML Content Detected!</strong></p>";
        
        // Try to extract useful info
        if (preg_match('/<title>(.*?)<\/title>/i', $response, $matches)) {
            echo "<p><strong>HTML Title:</strong> " . htmlspecialchars($matches[1]) . "</p>";
        }
        
        // Look for PHP errors
        if (preg_match('/Fatal error:(.*?)on line/i', $response, $matches)) {
            echo "<p><strong>PHP Fatal Error:</strong> " . htmlspecialchars(trim($matches[1])) . "</p>";
        }
        
        if (preg_match('/Parse error:(.*?)on line/i', $response, $matches)) {
            echo "<p><strong>PHP Parse Error:</strong> " . htmlspecialchars(trim($matches[1])) . "</p>";
        }
    }
} else {
    echo "<p><strong>✅ JSON Decode Successful</strong></p>";
    echo "<h4>Response Data:</h4>";
    echo "<pre>" . json_encode($jsonData, JSON_PRETTY_PRINT) . "</pre>";
    
    if (isset($jsonData['success']) && $jsonData['success']) {
        echo "<p><strong>🎉 SUCCESS!</strong> Simple sync endpoint is working correctly.</p>";
        
        if (isset($jsonData['data'])) {
            $data = $jsonData['data'];
            echo "<ul>";
            echo "<li>Total Domains: " . ($data['total_domains'] ?? 'Unknown') . "</li>";
            echo "<li>Domains in Batch: " . ($data['domains_found'] ?? 'Unknown') . "</li>";
            echo "<li>Domains Processed: " . ($data['domains_processed'] ?? 'Unknown') . "</li>";
            echo "<li>Domains Added: " . ($data['domains_added'] ?? 'Unknown') . "</li>";
            echo "<li>Domains Updated: " . ($data['domains_updated'] ?? 'Unknown') . "</li>";
            echo "<li>Errors: " . ($data['errors'] ?? 'Unknown') . "</li>";
            echo "</ul>";
        }
    } else {
        echo "<p><strong>❌ Sync Failed:</strong> " . ($jsonData['error'] ?? 'Unknown error') . "</p>";
    }
}

echo "<h2>Next Steps:</h2>";
if ($jsonData && isset($jsonData['success']) && $jsonData['success']) {
    echo "<p>✅ The simple sync endpoint is working! You can now:</p>";
    echo "<ul>";
    echo "<li><a href='sync_interface.php'>Try the sync interface</a> - It should work now</li>";
    echo "<li>Run batch 2, 3, etc. to sync more domains</li>";
    echo "</ul>";
} else {
    echo "<p>❌ The simple sync endpoint has issues. Check:</p>";
    echo "<ul>";
    echo "<li>PHP error logs in MAMP</li>";
    echo "<li>Database connection</li>";
    echo "<li>WHMCS API settings</li>";
    echo "</ul>";
}

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 