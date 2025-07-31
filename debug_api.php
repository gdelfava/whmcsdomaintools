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

echo "<h1>🔍 API Debug Information</h1>";

echo "<h2>Current Settings:</h2>";
echo "<ul>";
echo "<li><strong>API URL:</strong> " . htmlspecialchars($settings['api_url']) . "</li>";
echo "<li><strong>API Identifier:</strong> " . htmlspecialchars(substr($settings['api_identifier'], 0, 10)) . "...</li>";
echo "<li><strong>API Secret:</strong> " . htmlspecialchars(substr($settings['api_secret'], 0, 10)) . "...</li>";
echo "<li><strong>Default NS1:</strong> " . htmlspecialchars($settings['default_ns1']) . "</li>";
echo "<li><strong>Default NS2:</strong> " . htmlspecialchars($settings['default_ns2']) . "</li>";
echo "</ul>";

echo "<h2>Testing API Connection:</h2>";

// Test 1: Basic API connection
echo "<h3>1. Testing Basic API Connection</h3>";
$testResult = testApiConnection($settings['api_url'], $settings['api_identifier'], $settings['api_secret']);
echo "<p><strong>Result:</strong> " . ($testResult['result'] === 'success' ? '✅ SUCCESS' : '❌ FAILED') . "</p>";
echo "<p><strong>Message:</strong> " . htmlspecialchars($testResult['message']) . "</p>";

// Test 2: Get Clients (should work if API is accessible)
echo "<h3>2. Testing GetClients API Call</h3>";
$clientsResponse = curlCall($settings['api_url'], [
    'action' => 'GetClients',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'responsetype' => 'json',
    'limitnum' => 5
]);

echo "<p><strong>Response Result:</strong> " . ($clientsResponse['result'] ?? 'No result field') . "</p>";
if (isset($clientsResponse['message'])) {
    echo "<p><strong>Response Message:</strong> " . htmlspecialchars($clientsResponse['message']) . "</p>";
}
if (isset($clientsResponse['clients']['client'])) {
    echo "<p><strong>Clients Found:</strong> " . count($clientsResponse['clients']['client']) . "</p>";
}

// Test 3: Get Domains directly
echo "<h3>3. Testing GetClientsDomains API Call</h3>";
$domainsResponse = curlCall($settings['api_url'], [
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'responsetype' => 'json',
    'limitnum' => 10
]);

echo "<p><strong>Response Result:</strong> " . ($domainsResponse['result'] ?? 'No result field') . "</p>";
if (isset($domainsResponse['message'])) {
    echo "<p><strong>Response Message:</strong> " . htmlspecialchars($domainsResponse['message']) . "</p>";
}
if (isset($domainsResponse['domains']['domain'])) {
    echo "<p><strong>Domains Found:</strong> " . count($domainsResponse['domains']['domain']) . "</p>";
}

// Test 4: Test the specific function used in sync
echo "<h3>4. Testing getDomainsForExport Function</h3>";
$exportResponse = getDomainsForExport($settings['api_url'], $settings['api_identifier'], $settings['api_secret'], 10, 0);
echo "<p><strong>Response Result:</strong> " . ($exportResponse['result'] ?? 'No result field') . "</p>";
if (isset($exportResponse['message'])) {
    echo "<p><strong>Response Message:</strong> " . htmlspecialchars($exportResponse['message']) . "</p>";
}
if (isset($exportResponse['domains']['domain'])) {
    echo "<p><strong>Domains Found:</strong> " . count($exportResponse['domains']['domain']) . "</p>";
}

// Test 5: Check for common WHMCS API issues
echo "<h3>5. Common WHMCS API Issues Check</h3>";
echo "<ul>";

// Check if API URL ends with /includes/api.php
if (strpos($settings['api_url'], '/includes/api.php') === false) {
    echo "<li>⚠️ <strong>API URL Issue:</strong> URL should end with '/includes/api.php'</li>";
} else {
    echo "<li>✅ API URL format looks correct</li>";
}

// Check if we can reach the API URL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $settings['api_url']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "<li>✅ API URL is reachable (HTTP 200)</li>";
} else {
    echo "<li>❌ <strong>API URL Issue:</strong> HTTP Code: $httpCode</li>";
}

// Check if identifier and secret are not empty
if (empty($settings['api_identifier'])) {
    echo "<li>❌ <strong>API Identifier Issue:</strong> Empty or missing</li>";
} else {
    echo "<li>✅ API Identifier is set</li>";
}

if (empty($settings['api_secret'])) {
    echo "<li>❌ <strong>API Secret Issue:</strong> Empty or missing</li>";
} else {
    echo "<li>✅ API Secret is set</li>";
}

echo "</ul>";

echo "<h2>🔧 Troubleshooting Tips:</h2>";
echo "<ul>";
echo "<li><strong>WHMCS API URL:</strong> Should be something like 'https://yourdomain.com/includes/api.php'</li>";
echo "<li><strong>API Identifier:</strong> Found in WHMCS Admin → Setup → General Settings → API</li>";
echo "<li><strong>API Secret:</strong> Found in WHMCS Admin → Setup → General Settings → API</li>";
echo "<li><strong>SSL Issues:</strong> If using HTTPS, make sure SSL certificates are valid</li>";
echo "<li><strong>Firewall:</strong> Make sure your server allows outbound HTTP/HTTPS requests</li>";
echo "<li><strong>WHMCS Version:</strong> Make sure you're using a supported WHMCS version</li>";
echo "</ul>";

echo "<p><a href='settings.php'>← Back to Settings</a> | <a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 