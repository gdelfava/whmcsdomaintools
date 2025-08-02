<?php
// Debug script to test ultra_simple_sync.php response
session_start();

// Simulate a logged-in session
$_SESSION['logged_in'] = true;
$_SESSION['user_email'] = 'test@example.com';

// Capture the output
ob_start();

// Include the sync file
include 'ultra_simple_sync.php';

$output = ob_get_clean();

echo "<h1>Debug: ultra_simple_sync.php Response</h1>";

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
}

echo "<h2>Headers Sent:</h2>";
$headers = headers_list();
if (empty($headers)) {
    echo "<p>No headers sent</p>";
} else {
    echo "<ul>";
    foreach ($headers as $header) {
        echo "<li>" . htmlspecialchars($header) . "</li>";
    }
    echo "</ul>";
}

echo "<h2>Session Data:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h2>Next Steps:</h2>";
echo "<ul>";
echo "<li>If HTML is detected, check PHP error logs</li>";
echo "<li>If JSON is valid, the sync should work</li>";
echo "<li>Check if user settings file exists</li>";
echo "</ul>";
?> 