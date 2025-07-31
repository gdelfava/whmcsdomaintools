<?php
require_once 'api.php';
require_once 'user_settings.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint.

// Check if user has configured their API settings
if (!userHasSettings()) {
    echo "<h1>API Connection Test</h1>";
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> No API settings found. Please configure your API settings first.";
    echo "</div>";
    echo "<p><a href='settings.php'>Go to Settings</a></p>";
    exit;
}

// Load user settings
$userSettings = getUserSettings();
if (!$userSettings) {
    echo "<h1>API Connection Test</h1>";
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> Unable to load your API settings.";
    echo "</div>";
    exit;
}

echo "<h1>API Connection Test</h1>";

echo "<h2>Current API Settings:</h2>";
echo "<ul>";
echo "<li><strong>API URL:</strong> " . htmlspecialchars($userSettings['api_url']) . "</li>";
echo "<li><strong>API Identifier:</strong> " . htmlspecialchars($userSettings['api_identifier']) . "</li>";
echo "<li><strong>API Secret:</strong> " . (strlen($userSettings['api_secret']) > 0 ? 'Set (' . strlen($userSettings['api_secret']) . ' chars)' : 'Not set') . "</li>";
echo "</ul>";

echo "<h2>Testing API Connection...</h2>";

// Test 1: Basic API connection
echo "<h3>Test 1: Basic API Connection</h3>";
try {
    $testResponse = curlCall($userSettings['api_url'], [
        'action' => 'GetClients',
        'identifier' => $userSettings['api_identifier'],
        'secret' => $userSettings['api_secret'],
        'responsetype' => 'json',
        'limitnum' => 1
    ]);
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>Raw API Response:</strong><br>";
    echo "<pre>" . htmlspecialchars(print_r($testResponse, true)) . "</pre>";
    echo "</div>";
    
    if (isset($testResponse['result']) && $testResponse['result'] === 'success') {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>✅ SUCCESS:</strong> API connection successful!";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>❌ ERROR:</strong> API connection failed.";
        if (isset($testResponse['message'])) {
            echo "<br><strong>Error Message:</strong> " . htmlspecialchars($testResponse['message']);
        }
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ EXCEPTION:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

// Test 2: Get domains (small batch)
echo "<h3>Test 2: Get Domains (Small Batch)</h3>";
try {
    $domainsResponse = getDomainsForExport(
        $userSettings['api_url'],
        $userSettings['api_identifier'],
        $userSettings['api_secret'],
        10, // Small batch
        0   // No offset
    );
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>Domains API Response:</strong><br>";
    echo "<pre>" . htmlspecialchars(print_r($domainsResponse, true)) . "</pre>";
    echo "</div>";
    
    if (isset($domainsResponse['result']) && $domainsResponse['result'] === 'success') {
        $domains = $domainsResponse['domains']['domain'] ?? [];
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>✅ SUCCESS:</strong> Retrieved " . count($domains) . " domains from API.";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>❌ ERROR:</strong> Failed to retrieve domains.";
        if (isset($domainsResponse['message'])) {
            echo "<br><strong>Error Message:</strong> " . htmlspecialchars($domainsResponse['message']);
        }
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ EXCEPTION:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

// Test 3: Check your current IP
echo "<h3>Test 3: Your Current IP Address</h3>";
$currentIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$publicIP = file_get_contents('https://api.ipify.org') ?: 'Could not determine';

echo "<ul>";
echo "<li><strong>Local IP:</strong> $currentIP</li>";
echo "<li><strong>Public IP:</strong> $publicIP</li>";
echo "</ul>";

echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>⚠️ IMPORTANT:</strong> If you're getting 'IP Banned' errors, you need to add your public IP address ($publicIP) to the WHMCS API whitelist.";
echo "<br><br><strong>Steps:</strong>";
echo "<ol>";
echo "<li>Log into your WHMCS admin panel</li>";
echo "<li>Go to Setup > General Settings > API</li>";
echo "<li>Add your public IP address ($publicIP) to the 'Allowed IPs' list</li>";
echo "<li>Save changes</li>";
echo "<li>Try the test again</li>";
echo "</ol>";
echo "</div>";

echo "<h2>Troubleshooting Tips:</h2>";
echo "<ul>";
echo "<li><strong>IP Whitelist:</strong> Add your public IP to WHMCS API allowed IPs</li>";
echo "<li><strong>API Credentials:</strong> Double-check your API identifier and secret</li>";
echo "<li><strong>API URL:</strong> Ensure the URL points to your live WHMCS installation</li>";
echo "<li><strong>Network:</strong> Try using a different network (mobile hotspot)</li>";
echo "<li><strong>VPN:</strong> Use a VPN service to change your IP</li>";
echo "</ul>";

echo "<p><a href='settings.php'>Edit API Settings</a> | <a href='main_page.php'>Back to Dashboard</a></p>";
?> 