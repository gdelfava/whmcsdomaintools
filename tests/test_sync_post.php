<?php
// Test script to simulate POST request to ultra_simple_sync.php
session_start();

// Simulate a logged-in session
$_SESSION['logged_in'] = true;
$_SESSION['user_email'] = 'test@example.com';

// Simulate POST data
$_POST['batch_number'] = 1;
$_POST['batch_size'] = 10;

// Simulate POST method
$_SERVER['REQUEST_METHOD'] = 'POST';

// Capture the output
ob_start();

// Include the sync file
include 'ultra_simple_sync.php';

$output = ob_get_clean();

echo "<h1>Test: ultra_simple_sync.php with POST</h1>";

echo "<h2>Raw Response:</h2>";
echo "<div style='background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;'>";
echo htmlspecialchars($output);
echo "</div>";

echo "<h2>Response Analysis:</h2>";

// Check if it's JSON
$jsonData = json_decode($output, true);
if ($jsonData === null) {
    echo "<p><strong>❌ Not valid JSON:</strong> " . json_last_error_msg() . "</p>";
    
    // Check for HTML
    if (strpos($output, '<!DOCTYPE') !== false || strpos($output, '<html') !== false) {
        echo "<p><strong>⚠️ HTML Content Detected!</strong></p>";
        
        // Look for PHP errors
        if (preg_match('/Fatal error:(.*?)on line/i', $output, $matches)) {
            echo "<p><strong>PHP Fatal Error:</strong> " . htmlspecialchars(trim($matches[1])) . "</p>";
        }
        
        if (preg_match('/Parse error:(.*?)on line/i', $output, $matches)) {
            echo "<p><strong>PHP Parse Error:</strong> " . htmlspecialchars(trim($matches[1])) . "</p>";
        }
        
        if (preg_match('/Warning:(.*?)in/i', $output, $matches)) {
            echo "<p><strong>PHP Warning:</strong> " . htmlspecialchars(trim($matches[1])) . "</p>";
        }
        
        if (preg_match('/Notice:(.*?)in/i', $output, $matches)) {
            echo "<p><strong>PHP Notice:</strong> " . htmlspecialchars(trim($matches[1])) . "</p>";
        }
    }
} else {
    echo "<p><strong>✅ Valid JSON Response</strong></p>";
    echo "<h3>JSON Data:</h3>";
    echo "<pre>" . json_encode($jsonData, JSON_PRETTY_PRINT) . "</pre>";
    
    if (isset($jsonData['success']) && $jsonData['success']) {
        echo "<p><strong>🎉 SUCCESS!</strong> The sync endpoint is working correctly.</p>";
    } else {
        echo "<p><strong>❌ Sync Failed:</strong> " . ($jsonData['error'] ?? 'Unknown error') . "</p>";
    }
}

echo "<h2>Session Data:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h2>POST Data:</h2>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

echo "<h2>Next Steps:</h2>";
if ($jsonData && isset($jsonData['success']) && $jsonData['success']) {
    echo "<p>✅ The sync endpoint is working! Try the sync interface now.</p>";
} else {
    echo "<p>❌ There's still an issue. Check the error message above.</p>";
}
?> 