<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/debug_errors.log');

require_once 'auth.php';
require_once 'domain_sync.php';
require_once 'api.php';
require_once 'user_settings.php';

// Require authentication
requireAuth();

echo "<h1>🔍 Large Sync Debug Test</h1>";

$userEmail = $_SESSION['user_email'] ?? '';
if (empty($userEmail)) {
    echo "❌ ERROR: No user email found in session";
    exit;
}

echo "<h2>Step 1: Memory and Time Limits</h2>";
echo "<ul>";
echo "<li><strong>Memory Limit:</strong> " . ini_get('memory_limit') . "</li>";
echo "<li><strong>Max Execution Time:</strong> " . ini_get('max_execution_time') . " seconds</li>";
echo "<li><strong>Current Memory Usage:</strong> " . round(memory_get_usage() / 1024 / 1024, 2) . " MB</li>";
echo "</ul>";

echo "<h2>Step 2: Test API Call with Full Dataset</h2>";
$settings = getUserSettings();

echo "<p>Testing API call that fetches all 465 domains...</p>";
$startTime = microtime(true);
$startMemory = memory_get_usage();

try {
    $response = getDomainsForExport(
        $settings['api_url'],
        $settings['api_identifier'],
        $settings['api_secret'],
        10, // batch size
        0   // offset
    );
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage();
    
    echo "<p><strong>✅ API Call Successful</strong></p>";
    echo "<ul>";
    echo "<li><strong>Execution Time:</strong> " . round($endTime - $startTime, 2) . " seconds</li>";
    echo "<li><strong>Memory Used:</strong> " . round(($endMemory - $startMemory) / 1024 / 1024, 2) . " MB</li>";
    echo "<li><strong>Total Domains:</strong> " . ($response['totalresults'] ?? 'Unknown') . "</li>";
    echo "<li><strong>Batch Returned:</strong> " . count($response['domains']['domain'] ?? []) . " domains</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p><strong>❌ API Call Failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Step 3: Test DomainSync Class with Small Batch</h2>";
try {
    $sync = new DomainSync($userEmail);
    echo "<p>✅ DomainSync class instantiated</p>";
    
    echo "<p>Testing sync with 2 domains...</p>";
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    
    $result = $sync->syncBatch(1, 2); // Very small batch
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage();
    
    echo "<h4>Sync Result:</h4>";
    echo "<ul>";
    echo "<li><strong>Success:</strong> " . ($result['success'] ? 'Yes' : 'No') . "</li>";
    echo "<li><strong>Domains Found:</strong> " . ($result['data']['domains_found'] ?? 'Unknown') . "</li>";
    echo "<li><strong>Domains Processed:</strong> " . ($result['data']['domains_processed'] ?? 'Unknown') . "</li>";
    echo "<li><strong>Errors:</strong> " . ($result['data']['errors'] ?? 'Unknown') . "</li>";
    echo "<li><strong>Execution Time:</strong> " . round($endTime - $startTime, 2) . " seconds</li>";
    echo "<li><strong>Memory Used:</strong> " . round(($endMemory - $startMemory) / 1024 / 1024, 2) . " MB</li>";
    echo "</ul>";
    
    if (!$result['success'] && isset($result['data']['error_message'])) {
        echo "<p><strong>❌ Error Message:</strong> " . htmlspecialchars($result['data']['error_message']) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>❌ Sync Failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Stack Trace:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>Step 4: Test AJAX Endpoint Simulation</h2>";
echo "<p>Simulating the exact AJAX call that's failing...</p>";

// Simulate POST request
$_POST['action'] = 'sync_batch';
$_POST['batch_number'] = '1';
$_POST['batch_size'] = '10';

// Capture output
ob_start();

try {
    // Set headers that would be set by the endpoint
    header('Content-Type: application/json');
    
    $sync = new DomainSync($userEmail);
    $result = $sync->syncBatch(1, 10);
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$output = ob_get_clean();

echo "<h4>Raw AJAX Response:</h4>";
echo "<div style='background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; white-space: pre-wrap; max-height: 300px; overflow-y: auto;'>";
echo htmlspecialchars($output);
echo "</div>";

// Test JSON decode
$jsonData = json_decode($output, true);
if ($jsonData === null) {
    echo "<p><strong>❌ JSON Decode Failed:</strong> " . json_last_error_msg() . "</p>";
    
    // Check for HTML
    if (strpos($output, '<!DOCTYPE') !== false || strpos($output, '<html') !== false) {
        echo "<p><strong>⚠️ HTML Content Detected!</strong></p>";
        
        // Try to extract error info
        if (preg_match('/<title>(.*?)<\/title>/i', $output, $matches)) {
            echo "<p><strong>Page Title:</strong> " . htmlspecialchars($matches[1]) . "</p>";
        }
    }
} else {
    echo "<p><strong>✅ JSON Decode Successful</strong></p>";
}

echo "<h2>Step 5: PHP Error Log Check</h2>";
$errorLogFile = __DIR__ . '/logs/debug_errors.log';
if (file_exists($errorLogFile)) {
    $errorLog = file_get_contents($errorLogFile);
    if (!empty($errorLog)) {
        echo "<h4>Recent PHP Errors:</h4>";
        echo "<div style='background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; white-space: pre-wrap; max-height: 200px; overflow-y: auto;'>";
        echo htmlspecialchars($errorLog);
        echo "</div>";
    } else {
        echo "<p>✅ No recent PHP errors found</p>";
    }
} else {
    echo "<p>⚠️ Error log file not found (may be in different location)</p>";
}

echo "<h2>Recommendations:</h2>";
echo "<ul>";
echo "<li>🔧 <strong>If memory issues:</strong> Increase PHP memory_limit in php.ini</li>";
echo "<li>🔧 <strong>If timeout issues:</strong> Increase max_execution_time</li>";
echo "<li>🔧 <strong>If session issues:</strong> Check session.gc_maxlifetime</li>";
echo "<li>🔧 <strong>If database issues:</strong> Check MySQL error logs</li>";
echo "</ul>";

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 