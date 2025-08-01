<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

require_once 'config.php';
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

echo "<h1>HTML Response Debug</h1>";
echo "<p><strong>API URL:</strong> " . htmlspecialchars($settings['api_url']) . "</p>";

// Decrypt settings
$apiUrl = $settings['api_url'];
$apiIdentifier = $userSettings->decrypt($settings['api_identifier']);
$apiSecret = $userSettings->decrypt($settings['api_secret']);

echo "<h2>Test 1: Direct CURL with Full Debug</h2>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action' => 'GetClientsDomains',
    'identifier' => $apiIdentifier,
    'secret' => $apiSecret,
    'limitstart' => 0,
    'limitnum' => 1,
    'responsetype' => 'json'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

echo "<h3>CURL Info:</h3>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
if ($curlError) {
    echo "<p><strong>CURL Error:</strong> " . htmlspecialchars($curlError) . "</p>";
}

// Split headers and body
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "<h3>Response Headers:</h3>";
echo "<pre>" . htmlspecialchars($headers) . "</pre>";

echo "<h3>Response Body (first 1000 chars):</h3>";
echo "<pre>" . htmlspecialchars(substr($body, 0, 1000)) . "</pre>";

echo "<h3>Response Analysis:</h3>";

// Check for common HTML patterns
$htmlPatterns = [
    'PHP Error' => '/<b>.*?Fatal error.*?<\/b>/i',
    'PHP Warning' => '/<b>.*?Warning.*?<\/b>/i',
    'PHP Notice' => '/<b>.*?Notice.*?<\/b>/i',
    'HTML Tags' => '/<[^>]+>/',
    'DOCTYPE' => '/<!DOCTYPE/i',
    'HTML Tag' => '/<html/i',
    'Body Tag' => '/<body/i',
    'BR Tags' => '/<br\s*\/?>/i',
    'Bold Tags' => '/<b[^>]*>.*?<\/b>/i'
];

foreach ($htmlPatterns as $patternName => $pattern) {
    if (preg_match($pattern, $body)) {
        echo "<p>🚨 <strong>Found $patternName:</strong> " . htmlspecialchars(preg_match($pattern, $body, $matches) ? $matches[0] : 'Yes') . "</p>";
    }
}

// Check if it's valid JSON
$decoded = json_decode($body, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p>✅ Response is valid JSON</p>";
    echo "<pre>" . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . "</pre>";
} else {
    echo "<p>❌ Response is NOT valid JSON</p>";
    echo "<p><strong>JSON Error:</strong> " . json_last_error_msg() . "</p>";
}

echo "<h2>Test 2: Test with Different Content-Type</h2>";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $apiUrl);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([
    'action' => 'GetClientsDomains',
    'identifier' => $apiIdentifier,
    'secret' => $apiSecret,
    'limitstart' => 0,
    'limitnum' => 1,
    'responsetype' => 'json'
]));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Accept: application/json',
    'User-Agent: DomainTools/1.0'
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "<p><strong>HTTP Code with headers:</strong> $httpCode2</p>";
echo "<p><strong>Response length:</strong> " . strlen($response2) . " characters</p>";

if (strpos($response2, '<br />') !== false) {
    echo "<p>🚨 <strong>Found &lt;br /&gt; tag in response</strong></p>";
    $brPos = strpos($response2, '<br />');
    $context = substr($response2, max(0, $brPos - 50), 100);
    echo "<p><strong>Context around &lt;br /&gt;:</strong> " . htmlspecialchars($context) . "</p>";
}

echo "<h2>Test 3: Test API.php curlCall Function</h2>";

require_once 'api.php';

$apiResponse = curlCall($apiUrl, [
    'action' => 'GetClientsDomains',
    'identifier' => $apiIdentifier,
    'secret' => $apiSecret,
    'limitstart' => 0,
    'limitnum' => 1,
    'responsetype' => 'json'
]);

echo "<h3>API.php Response:</h3>";
echo "<pre>" . htmlspecialchars(json_encode($apiResponse, JSON_PRETTY_PRINT)) . "</pre>";

echo "<h2>Recommendations:</h2>";
echo "<ul>";

if ($httpCode !== 200) {
    echo "<li>🚨 <strong>HTTP Error:</strong> Server returned code $httpCode</li>";
}

if (strpos($body, '<br />') !== false) {
    echo "<li>🚨 <strong>HTML Error:</strong> API is returning HTML error page</li>";
    echo "<li>💡 <strong>Check:</strong> WHMCS error logs and PHP configuration</li>";
}

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<li>🚨 <strong>JSON Parse Error:</strong> " . json_last_error_msg() . "</li>";
}

if (isset($apiResponse['result']) && $apiResponse['result'] === 'error') {
    echo "<li>✅ <strong>Error Handling Working:</strong> API.php correctly detected the error</li>";
}

echo "<li>🔧 <strong>Next Steps:</strong></li>";
echo "<ul>";
echo "<li>Check WHMCS admin panel → Setup → General Settings → API</li>";
echo "<li>Verify API user has domain access permissions</li>";
echo "<li>Check WHMCS error logs in admin panel</li>";
echo "<li>Test API credentials manually in WHMCS</li>";
echo "</ul>";

echo "</ul>";

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 