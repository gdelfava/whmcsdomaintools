<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

echo "<h1>Small Sync Test</h1>";

// Simulate a POST request to ultra_simple_sync.php
$postData = [
    'batch_number' => 1,
    'batch_size' => 1 // Only sync 1 domain for testing
];

// Use cURL to make a POST request to the sync endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8888/domain-tools-fridge/ultra_simple_sync.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: DomainTools-Test/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h2>HTTP Response Code: $httpCode</h2>";

if ($curlError) {
    echo "<p>❌ <strong>CURL Error:</strong> " . htmlspecialchars($curlError) . "</p>";
    exit;
}

echo "<h2>Raw Response:</h2>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try to decode the JSON response
$responseData = json_decode($response, true);

if ($responseData) {
    echo "<h2>Parsed Response:</h2>";
    echo "<pre>" . htmlspecialchars(json_encode($responseData, JSON_PRETTY_PRINT)) . "</pre>";
    
    if (isset($responseData['success']) && $responseData['success']) {
        echo "<p>✅ <strong>Sync completed successfully!</strong></p>";
        if (isset($responseData['data'])) {
            echo "<ul>";
            echo "<li><strong>Domains found:</strong> " . ($responseData['data']['domains_found'] ?? 0) . "</li>";
            echo "<li><strong>Domains processed:</strong> " . ($responseData['data']['domains_processed'] ?? 0) . "</li>";
            echo "<li><strong>Domains added:</strong> " . ($responseData['data']['domains_added'] ?? 0) . "</li>";
            echo "<li><strong>Domains updated:</strong> " . ($responseData['data']['domains_updated'] ?? 0) . "</li>";
            echo "<li><strong>Errors:</strong> " . ($responseData['data']['errors'] ?? 0) . "</li>";
            echo "</ul>";
        }
    } else {
        echo "<p>❌ <strong>Sync failed:</strong> " . htmlspecialchars($responseData['error'] ?? 'Unknown error') . "</p>";
    }
} else {
    echo "<p>❌ <strong>Invalid JSON response</strong></p>";
    echo "<p>The sync script may have output HTML or other non-JSON content.</p>";
}

echo "<h2>PHP Error Log (Last 10 lines):</h2>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $lines = file($errorLog);
    $recentLines = array_slice($lines, -10);
    echo "<pre>" . htmlspecialchars(implode('', $recentLines)) . "</pre>";
} else {
    echo "<p>No error log found or accessible</p>";
}

echo "<p><a href='main_page.php?view=sync'>← Back to Sync Interface</a></p>"; 