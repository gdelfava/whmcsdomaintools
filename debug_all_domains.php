<?php
require_once 'auth.php';
require_once 'api.php';
require_once 'user_settings.php';

// Require authentication
requireAuth();

echo "<h1>🔍 Alternative Domain Retrieval Methods</h1>";

$settings = getUserSettings();
if (!$settings) {
    echo "❌ ERROR: No user settings found";
    exit;
}

echo "<h2>Testing Alternative WHMCS API Calls</h2>";

// Test different API actions that might return more domains
$apiTests = [
    'GetClientsDomains (no params)' => [
        'action' => 'GetClientsDomains',
        'identifier' => $settings['api_identifier'],
        'secret' => $settings['api_secret'],
        'responsetype' => 'json'
    ],
    'GetClientsDomains (high limit)' => [
        'action' => 'GetClientsDomains',
        'identifier' => $settings['api_identifier'],
        'secret' => $settings['api_secret'],
        'limitnum' => 2000,
        'responsetype' => 'json'
    ],
    'GetOrders (domain orders)' => [
        'action' => 'GetOrders',
        'identifier' => $settings['api_identifier'],
        'secret' => $settings['api_secret'],
        'limitnum' => 1000,
        'responsetype' => 'json'
    ]
];

foreach ($apiTests as $testName => $params) {
    echo "<h3>$testName</h3>";
    
    $response = curlCall($settings['api_url'], $params);
    
    echo "<p><strong>Result:</strong> " . ($response['result'] ?? 'Not set') . "</p>";
    
    if (isset($response['message'])) {
        echo "<p><strong>Message:</strong> " . htmlspecialchars($response['message']) . "</p>";
    }
    
    // Check different response structures
    if (isset($response['domains']['domain'])) {
        echo "<p><strong>Domains Found:</strong> " . count($response['domains']['domain']) . "</p>";
    } elseif (isset($response['orders']['order'])) {
        $domainOrders = 0;
        foreach ($response['orders']['order'] as $order) {
            if (isset($order['producttype']) && $order['producttype'] === 'domain') {
                $domainOrders++;
            }
        }
        echo "<p><strong>Domain Orders Found:</strong> $domainOrders / " . count($response['orders']['order']) . " total orders</p>";
    }
    
    // Show total results if available
    if (isset($response['totalresults'])) {
        echo "<p><strong>Total Results:</strong> " . $response['totalresults'] . "</p>";
    }
    
    echo "<hr>";
}

echo "<h2>Direct Database Query Simulation</h2>";
echo "<p>Let's try to understand what WHMCS is actually filtering...</p>";

// Test with different status filters
$statusTests = [
    'All Statuses' => [],
    'Active Only' => ['status' => 'Active'],
    'Include Expired' => ['status' => 'Expired'],
];

foreach ($statusTests as $testName => $extraParams) {
    echo "<h3>GetClientsDomains - $testName</h3>";
    
    $params = [
        'action' => 'GetClientsDomains',
        'identifier' => $settings['api_identifier'],
        'secret' => $settings['api_secret'],
        'limitnum' => 1000,
        'responsetype' => 'json'
    ];
    
    $params = array_merge($params, $extraParams);
    
    $response = curlCall($settings['api_url'], $params);
    
    echo "<p><strong>Result:</strong> " . ($response['result'] ?? 'Not set') . "</p>";
    if (isset($response['domains']['domain'])) {
        echo "<p><strong>Domains Found:</strong> " . count($response['domains']['domain']) . "</p>";
        
        // Show status breakdown
        $statusCounts = [];
        foreach ($response['domains']['domain'] as $domain) {
            $status = $domain['status'] ?? 'Unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        
        echo "<p><strong>Status Breakdown:</strong></p>";
        echo "<ul>";
        foreach ($statusCounts as $status => $count) {
            echo "<li>$status: $count domains</li>";
        }
        echo "</ul>";
    }
    echo "<hr>";
}

echo "<h2>WHMCS Admin Verification</h2>";
echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
echo "<h3>⚠️ Manual Verification Required</h3>";
echo "<p>To confirm the actual domain count in WHMCS:</p>";
echo "<ol>";
echo "<li><strong>Login to WHMCS Admin</strong></li>";
echo "<li><strong>Go to:</strong> Domains → Domain List</li>";
echo "<li><strong>Check the total count</strong> at the bottom of the page</li>";
echo "<li><strong>Note any filters</strong> that might be applied by default</li>";
echo "<li><strong>Try different status filters</strong> (Active, Expired, Cancelled, etc.)</li>";
echo "</ol>";
echo "</div>";

echo "<h2>Possible Issues & Solutions</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Possible Issue</th><th>How to Check</th><th>Solution</th></tr>";

$issues = [
    [
        'issue' => 'API User Lacks Domain Permissions',
        'check' => 'WHMCS Admin → Setup → Admin Roles → Check API user permissions',
        'solution' => 'Grant full domain access to API user'
    ],
    [
        'issue' => 'Domains Not Associated with Clients',
        'check' => 'WHMCS Admin → Domains → Check domains without client assignment',
        'solution' => 'Use different API method or assign domains to clients'
    ],
    [
        'issue' => 'WHMCS Default Filters',
        'check' => 'Check if WHMCS applies default status filters',
        'solution' => 'Modify API call to include all statuses'
    ],
    [
        'issue' => 'API Rate Limiting',
        'check' => 'WHMCS Activity Log for API restrictions',
        'solution' => 'Implement slower API calls with delays'
    ],
    [
        'issue' => 'Database Inconsistency',
        'check' => 'Direct database query: SELECT COUNT(*) FROM tbldomains',
        'solution' => 'WHMCS database maintenance'
    ]
];

foreach ($issues as $issue) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($issue['issue']) . "</td>";
    echo "<td>" . htmlspecialchars($issue['check']) . "</td>";
    echo "<td>" . htmlspecialchars($issue['solution']) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 