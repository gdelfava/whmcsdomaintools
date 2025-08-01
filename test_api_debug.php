<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

require_once 'config.php';
require_once 'api.php';
require_once 'user_settings_db.php';

$userEmail = $_SESSION['user_email'] ?? '';
$companyId = $_SESSION['company_id'] ?? null;

if (empty($userEmail)) {
    echo "No user email in session";
    exit;
}

if (empty($companyId)) {
    echo "No company ID in session";
    exit;
}

// Get user settings
$userSettings = new UserSettingsDB();
$settings = $userSettings->loadSettings($companyId, $userEmail);

if (!$settings) {
    echo "User settings not found";
    exit;
}

echo "<h1>API Debug Test</h1>";
echo "<p><strong>API URL:</strong> " . htmlspecialchars($settings['api_url']) . "</p>";
echo "<p><strong>API Identifier:</strong> " . htmlspecialchars($settings['api_identifier']) . "</p>";
echo "<p><strong>API Secret:</strong> " . (strlen($settings['api_secret']) > 0 ? 'Set (' . strlen($settings['api_secret']) . ' chars)' : 'Not set') . "</p>";

echo "<h2>Test 1: Basic API Connection</h2>";

$testResponse = curlCall($settings['api_url'], [
    'action' => 'GetClients',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'responsetype' => 'json',
    'limitnum' => 1
]);

echo "<h3>Raw Response:</h3>";
echo "<pre>" . htmlspecialchars(json_encode($testResponse, JSON_PRETTY_PRINT)) . "</pre>";

if (isset($testResponse['result'])) {
    echo "<p><strong>Result:</strong> " . htmlspecialchars($testResponse['result']) . "</p>";
}

if (isset($testResponse['message'])) {
    echo "<p><strong>Message:</strong> " . htmlspecialchars($testResponse['message']) . "</p>";
}

echo "<h2>Test 2: Get Domains (Small Batch)</h2>";

$domainsResponse = curlCall($settings['api_url'], [
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'limitstart' => 0,
    'limitnum' => 5,
    'responsetype' => 'json'
]);

echo "<h3>Raw Response:</h3>";
echo "<pre>" . htmlspecialchars(json_encode($domainsResponse, JSON_PRETTY_PRINT)) . "</pre>";

if (isset($domainsResponse['result'])) {
    echo "<p><strong>Result:</strong> " . htmlspecialchars($domainsResponse['result']) . "</p>";
}

if (isset($domainsResponse['domains']['domain'])) {
    echo "<p><strong>Domains found:</strong> " . count($domainsResponse['domains']['domain']) . "</p>";
}

echo "<h2>Test 3: Manual CURL Test</h2>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $settings['api_url']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'limitnum' => 1,
    'responsetype' => 'json'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$rawResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p><strong>HTTP Code:</strong> " . $httpCode . "</p>";
if ($curlError) {
    echo "<p><strong>CURL Error:</strong> " . htmlspecialchars($curlError) . "</p>";
}

echo "<h3>Raw Response (first 500 chars):</h3>";
echo "<pre>" . htmlspecialchars(substr($rawResponse, 0, 500)) . "</pre>";

echo "<h3>Is JSON?</h3>";
$decoded = json_decode($rawResponse, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p>✅ Response is valid JSON</p>";
    echo "<pre>" . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . "</pre>";
} else {
    echo "<p>❌ Response is NOT valid JSON</p>";
    echo "<p><strong>JSON Error:</strong> " . json_last_error_msg() . "</p>";
}

echo "<h2>Recommendations:</h2>";
echo "<ul>";

if ($httpCode !== 200) {
    echo "<li>🚨 <strong>HTTP Error:</strong> Server returned code $httpCode</li>";
}

if (strpos($rawResponse, '<br />') !== false || strpos($rawResponse, '<b>') !== false) {
    echo "<li>🚨 <strong>HTML Error:</strong> API is returning HTML error page instead of JSON</li>";
    echo "<li>💡 <strong>Check:</strong> WHMCS API configuration and permissions</li>";
}

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<li>🚨 <strong>JSON Parse Error:</strong> " . json_last_error_msg() . "</li>";
}

if (isset($testResponse['result']) && $testResponse['result'] === 'success') {
    echo "<li>✅ <strong>Basic API connection works</strong></li>";
} else {
    echo "<li>❌ <strong>Basic API connection failed</strong></li>";
}

echo "</ul>";

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 