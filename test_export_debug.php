<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

require_once 'api.php';
require_once 'user_settings_db.php';

echo "<h1>Export Debug Test</h1>";

// Get user settings
$userSettings = getUserSettingsDB();
if (!$userSettings) {
    echo "<p style='color: red;'>❌ No API settings found</p>";
    exit;
}

echo "<p style='color: green;'>✅ API settings loaded</p>";

// Test API connection
echo "<h2>Testing API Connection</h2>";
$testResponse = testApiConnection($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);

if ($testResponse['result'] === 'success') {
    echo "<p style='color: green;'>✅ API connection successful</p>";
} else {
    echo "<p style='color: red;'>❌ API connection failed: " . htmlspecialchars($testResponse['message']) . "</p>";
    exit;
}

// Test getting domains for batch 1
echo "<h2>Testing Domain Fetch (Batch 1)</h2>";
$domainsResponse = getDomainsForExport($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], 50, 0);

if (isset($domainsResponse['domains']['domain'])) {
    $domains = $domainsResponse['domains']['domain'];
    echo "<p style='color: green;'>✅ Found " . count($domains) . " domains in batch 1</p>";
    
    if (count($domains) > 0) {
        $firstDomain = $domains[0];
        echo "<p><strong>First domain:</strong> " . htmlspecialchars($firstDomain['domainname'] ?? 'Unknown') . "</p>";
        echo "<p><strong>Domain ID:</strong> " . htmlspecialchars($firstDomain['id'] ?? 'Unknown') . "</p>";
        
        // Test nameserver fetch for first domain
        if (isset($firstDomain['id'])) {
            echo "<h2>Testing Nameserver Fetch</h2>";
            $nsResponse = getDomainNameservers($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $firstDomain['id']);
            
            if (isset($nsResponse['result']) && $nsResponse['result'] === 'success') {
                echo "<p style='color: green;'>✅ Nameserver fetch successful</p>";
                echo "<p><strong>NS1:</strong> " . htmlspecialchars($nsResponse['ns1'] ?? 'N/A') . "</p>";
                echo "<p><strong>NS2:</strong> " . htmlspecialchars($nsResponse['ns2'] ?? 'N/A') . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Nameserver fetch failed: " . htmlspecialchars($nsResponse['message'] ?? 'Unknown error') . "</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ No domain ID found for first domain</p>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Could not fetch domains</p>";
    if (isset($domainsResponse['message'])) {
        echo "<p><strong>Error:</strong> " . htmlspecialchars($domainsResponse['message']) . "</p>";
    }
}

// Test session functionality
echo "<h2>Testing Session Functionality</h2>";
$_SESSION['test_data'] = ['test' => 'value'];
if (isset($_SESSION['test_data'])) {
    echo "<p style='color: green;'>✅ Session functionality working</p>";
} else {
    echo "<p style='color: red;'>❌ Session functionality not working</p>";
}

echo "<h2>PHP Configuration</h2>";
echo "<p><strong>max_execution_time:</strong> " . ini_get('max_execution_time') . " seconds</p>";
echo "<p><strong>memory_limit:</strong> " . ini_get('memory_limit') . "</p>";
echo "<p><strong>session.save_handler:</strong> " . ini_get('session.save_handler') . "</p>";

echo "<p><a href='export_progress.php'>← Back to Export Progress</a></p>";
?> 