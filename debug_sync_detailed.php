<?php
require_once 'auth.php';
require_once 'domain_sync.php';
require_once 'api.php';
require_once 'user_settings.php';

// Require authentication
requireAuth();

echo "<h1>🔍 Detailed Sync Process Debug</h1>";

$userEmail = $_SESSION['user_email'] ?? '';
if (empty($userEmail)) {
    echo "❌ ERROR: No user email found in session";
    exit;
}

echo "<h2>Step 1: User Settings Check</h2>";
$settings = getUserSettings();
if (!$settings) {
    echo "❌ ERROR: No user settings found";
    exit;
}

echo "✅ User settings loaded successfully<br>";
echo "<ul>";
echo "<li><strong>API URL:</strong> " . htmlspecialchars($settings['api_url']) . "</li>";
echo "<li><strong>API Identifier:</strong> " . htmlspecialchars(substr($settings['api_identifier'], 0, 10)) . "...</li>";
echo "<li><strong>API Secret:</strong> " . htmlspecialchars(substr($settings['api_secret'], 0, 10)) . "...</li>";
echo "</ul>";

echo "<h2>Step 2: Test API Call (getDomainsForExport)</h2>";
echo "<p>Testing the exact same API call that sync uses...</p>";

// Test the exact same parameters that sync uses
$batchSize = 10;
$offset = 0; // For batch 1

echo "<p><strong>Parameters:</strong></p>";
echo "<ul>";
echo "<li>Batch Size: $batchSize</li>";
echo "<li>Offset: $offset</li>";
echo "</ul>";

$domainsResponse = getDomainsForExport(
    $settings['api_url'],
    $settings['api_identifier'],
    $settings['api_secret'],
    $batchSize,
    $offset
);

echo "<h3>Raw API Response:</h3>";
echo "<div style='background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;'>";
echo htmlspecialchars(json_encode($domainsResponse, JSON_PRETTY_PRINT));
echo "</div>";

echo "<h3>API Response Analysis:</h3>";
echo "<ul>";
echo "<li><strong>Result:</strong> " . ($domainsResponse['result'] ?? 'Not set') . "</li>";
if (isset($domainsResponse['message'])) {
    echo "<li><strong>Message:</strong> " . htmlspecialchars($domainsResponse['message']) . "</li>";
}
echo "<li><strong>Has domains array:</strong> " . (isset($domainsResponse['domains']) ? 'Yes' : 'No') . "</li>";
if (isset($domainsResponse['domains'])) {
    echo "<li><strong>Has domain sub-array:</strong> " . (isset($domainsResponse['domains']['domain']) ? 'Yes' : 'No') . "</li>";
    if (isset($domainsResponse['domains']['domain'])) {
        $domainCount = is_array($domainsResponse['domains']['domain']) ? count($domainsResponse['domains']['domain']) : 0;
        echo "<li><strong>Domain count:</strong> $domainCount</li>";
    }
}
echo "</ul>";

echo "<h2>Step 3: Test DomainSync Class</h2>";
try {
    $sync = new DomainSync($userEmail);
    echo "✅ DomainSync class instantiated successfully<br>";
    
    echo "<h3>Testing Small Sync (1 domain)</h3>";
    
    // Try to sync just 1 domain to see what happens
    $result = $sync->syncBatch(1, 1);
    
    echo "<h4>Sync Result:</h4>";
    echo "<div style='background: #f5f5f5; border: 1px solid #ddd; padding: 10px; font-family: monospace; white-space: pre-wrap;'>";
    echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT));
    echo "</div>";
    
    if (isset($result['data']['error_message'])) {
        echo "<p><strong>❌ Error Message:</strong> " . htmlspecialchars($result['data']['error_message']) . "</p>";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<h2>Step 4: Direct API Test with Different Parameters</h2>";

// Test with different parameters to see what works
echo "<h3>Test 1: Direct GetClientsDomains (no limits)</h3>";
$directResponse = curlCall($settings['api_url'], [
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'responsetype' => 'json'
]);

echo "<p><strong>Result:</strong> " . ($directResponse['result'] ?? 'Not set') . "</p>";
if (isset($directResponse['domains']['domain'])) {
    echo "<p><strong>Total domains found:</strong> " . count($directResponse['domains']['domain']) . "</p>";
}

echo "<h3>Test 2: GetClientsDomains with limitnum only</h3>";
$limitResponse = curlCall($settings['api_url'], [
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'responsetype' => 'json',
    'limitnum' => 10
]);

echo "<p><strong>Result:</strong> " . ($limitResponse['result'] ?? 'Not set') . "</p>";
if (isset($limitResponse['domains']['domain'])) {
    echo "<p><strong>Domains found:</strong> " . count($limitResponse['domains']['domain']) . "</p>";
}

echo "<h2>Recommendations:</h2>";
echo "<ul>";

if (!isset($domainsResponse['domains']['domain']) || empty($domainsResponse['domains']['domain'])) {
    echo "<li>🚨 <strong>Main Issue:</strong> API is not returning domains in expected format</li>";
    
    if (isset($domainsResponse['result']) && $domainsResponse['result'] !== 'success') {
        echo "<li>💡 <strong>API Error:</strong> Check API credentials and permissions</li>";
    }
    
    if (isset($directResponse['domains']['domain']) && !empty($directResponse['domains']['domain'])) {
        echo "<li>💡 <strong>Solution:</strong> Remove limitstart parameter - use limitnum only</li>";
    }
}

echo "<li>🔧 <strong>Try refreshing the page and running sync again</strong></li>";
echo "<li>🔧 <strong>Check WHMCS admin panel - API logs section</strong></li>";
echo "<li>🔧 <strong>Verify API user has domain access permissions</strong></li>";
echo "</ul>";

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 