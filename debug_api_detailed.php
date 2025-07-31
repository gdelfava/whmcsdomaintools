<?php
// require_once 'auth.php';
// If needed, use:
// require_once 'auth_v2.php';
require_once 'api.php';
require_once 'user_settings.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint

// Get user settings
$settings = getUserSettings();

if (!$settings) {
    echo "❌ ERROR: No user settings found. Please configure your API settings first.";
    exit;
}

echo "<h1>🔍 Detailed API Debug Information</h1>";

echo "<h2>Current Settings:</h2>";
echo "<ul>";
echo "<li><strong>API URL:</strong> " . htmlspecialchars($settings['api_url']) . "</li>";
echo "<li><strong>API Identifier:</strong> " . htmlspecialchars(substr($settings['api_identifier'], 0, 10)) . "...</li>";
echo "<li><strong>API Secret:</strong> " . htmlspecialchars(substr($settings['api_secret'], 0, 10)) . "...</li>";
echo "</ul>";

echo "<h2>Testing API with Raw Response:</h2>";

// Test the exact same call that's failing in sync
echo "<h3>1. Testing GetClientsDomains (same as sync)</h3>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $settings['api_url']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'responsetype' => 'json',
    'limitnum' => 10
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'WHMCS-Domain-Tools/1.0');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
echo "<p><strong>Content Type:</strong> $contentType</p>";
if ($error) {
    echo "<p><strong>CURL Error:</strong> $error</p>";
}

echo "<h3>Raw Response (first 1000 characters):</h3>";
echo "<div style='background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;'>";
echo htmlspecialchars(substr($response, 0, 1000));
if (strlen($response) > 1000) {
    echo "\n\n... (truncated, total length: " . strlen($response) . " characters)";
}
echo "</div>";

// Try to decode as JSON
echo "<h3>JSON Decode Test:</h3>";
$jsonData = json_decode($response, true);
if ($jsonData === null) {
    echo "<p><strong>❌ JSON Decode Failed:</strong> " . json_last_error_msg() . "</p>";
    
    // Check if it's HTML
    if (strpos($response, '<!DOCTYPE') !== false || strpos($response, '<html') !== false) {
        echo "<p><strong>⚠️ HTML Detected:</strong> The API is returning HTML instead of JSON</p>";
        
        // Try to extract error information from HTML
        if (preg_match('/<title>(.*?)<\/title>/i', $response, $matches)) {
            echo "<p><strong>HTML Title:</strong> " . htmlspecialchars($matches[1]) . "</p>";
        }
        
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $response, $matches)) {
            $bodyContent = strip_tags($matches[1]);
            echo "<p><strong>Body Content:</strong> " . htmlspecialchars(substr($bodyContent, 0, 200)) . "</p>";
        }
    }
} else {
    echo "<p><strong>✅ JSON Decode Successful</strong></p>";
    echo "<p><strong>Response Keys:</strong> " . implode(', ', array_keys($jsonData)) . "</p>";
    if (isset($jsonData['result'])) {
        echo "<p><strong>Result:</strong> " . htmlspecialchars($jsonData['result']) . "</p>";
    }
    if (isset($jsonData['message'])) {
        echo "<p><strong>Message:</strong> " . htmlspecialchars($jsonData['message']) . "</p>";
    }
}

echo "<h2>Common Solutions:</h2>";
echo "<ul>";

// Check API URL format
if (strpos($settings['api_url'], '/includes/api.php') === false) {
    echo "<li>❌ <strong>API URL Issue:</strong> URL should end with '/includes/api.php'</li>";
    echo "<li>💡 <strong>Fix:</strong> Update your API URL to include '/includes/api.php'</li>";
} else {
    echo "<li>✅ API URL format looks correct</li>";
}

// Check if it's HTTPS and suggest HTTP for testing
if (strpos($settings['api_url'], 'https://') === 0) {
    echo "<li>⚠️ <strong>HTTPS Detected:</strong> Try HTTP temporarily for testing</li>";
    echo "<li>💡 <strong>Test URL:</strong> " . str_replace('https://', 'http://', $settings['api_url']) . "</li>";
}

// Check for common WHMCS paths
$urlParts = parse_url($settings['api_url']);
if (isset($urlParts['path'])) {
    $path = $urlParts['path'];
    if (strpos($path, '/whmcs/') !== false) {
        echo "<li>💡 <strong>WHMCS Path Detected:</strong> Make sure the path is correct</li>";
    }
}

echo "</ul>";

echo "<h2>🔧 Troubleshooting Steps:</h2>";
echo "<ol>";
echo "<li><strong>Check API URL:</strong> Make sure it ends with '/includes/api.php'</li>";
echo "<li><strong>Test with HTTP:</strong> Try changing https:// to http:// temporarily</li>";
echo "<li><strong>Verify WHMCS Installation:</strong> Make sure WHMCS is properly installed</li>";
echo "<li><strong>Check API Access:</strong> Verify API is enabled in WHMCS Admin</li>";
echo "<li><strong>Test Direct Access:</strong> Try accessing the API URL directly in browser</li>";
echo "</ol>";

echo "<h2>Quick Test URLs:</h2>";
echo "<p>Try these URLs in your browser to test:</p>";
echo "<ul>";
echo "<li><a href='" . htmlspecialchars($settings['api_url']) . "' target='_blank'>Current API URL</a></li>";
if (strpos($settings['api_url'], 'https://') === 0) {
    echo "<li><a href='" . htmlspecialchars(str_replace('https://', 'http://', $settings['api_url'])) . "' target='_blank'>HTTP Version</a></li>";
}
echo "</ul>";

echo "<p><a href='settings.php'>← Back to Settings</a> | <a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 