<?php
require_once 'auth.php';
require_once 'api.php';
require_once 'user_settings.php';

// Require authentication
requireAuth();

echo "<h1>🔍 Domain Count Investigation</h1>";

$settings = getUserSettings();
if (!$settings) {
    echo "❌ ERROR: No user settings found";
    exit;
}

echo "<h2>Testing Different API Approaches</h2>";

echo "<h3>1. Standard GetClientsDomains Call</h3>";
$standardResponse = curlCall($settings['api_url'], [
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'responsetype' => 'json'
]);

echo "<p><strong>Result:</strong> " . ($standardResponse['result'] ?? 'Not set') . "</p>";
if (isset($standardResponse['domains']['domain'])) {
    echo "<p><strong>Domains returned:</strong> " . count($standardResponse['domains']['domain']) . "</p>";
}
if (isset($standardResponse['totalresults'])) {
    echo "<p><strong>Total results field:</strong> " . $standardResponse['totalresults'] . "</p>";
}

echo "<h3>2. GetClientsDomains with High Limit</h3>";
$highLimitResponse = curlCall($settings['api_url'], [
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'limitnum' => 1000,
    'responsetype' => 'json'
]);

echo "<p><strong>Result:</strong> " . ($highLimitResponse['result'] ?? 'Not set') . "</p>";
if (isset($highLimitResponse['domains']['domain'])) {
    echo "<p><strong>Domains returned:</strong> " . count($highLimitResponse['domains']['domain']) . "</p>";
}

echo "<h3>3. Client-by-Client Approach</h3>";
echo "<p>Getting all clients first, then their domains...</p>";

$clientsResponse = curlCall($settings['api_url'], [
    'action' => 'GetClients',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'limitnum' => 1000,
    'responsetype' => 'json'
]);

echo "<p><strong>GetClients Result:</strong> " . ($clientsResponse['result'] ?? 'Not set') . "</p>";
if (isset($clientsResponse['clients']['client'])) {
    $clientCount = count($clientsResponse['clients']['client']);
    echo "<p><strong>Total Clients Found:</strong> $clientCount</p>";
    
    $totalDomains = 0;
    $clientDomainCounts = [];
    
    // Get domains for each client
    foreach ($clientsResponse['clients']['client'] as $client) {
        $clientId = $client['id'];
        
        $clientDomainsResponse = curlCall($settings['api_url'], [
            'action' => 'GetClientsDomains',
            'identifier' => $settings['api_identifier'],
            'secret' => $settings['api_secret'],
            'clientid' => $clientId,
            'responsetype' => 'json'
        ]);
        
        if (isset($clientDomainsResponse['domains']['domain'])) {
            $domainCount = count($clientDomainsResponse['domains']['domain']);
            $totalDomains += $domainCount;
            $clientDomainCounts[] = [
                'client_id' => $clientId,
                'client_name' => $client['firstname'] . ' ' . $client['lastname'],
                'domain_count' => $domainCount
            ];
        }
    }
    
    echo "<h4>Domain Count by Client:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Client ID</th><th>Client Name</th><th>Domains</th></tr>";
    foreach ($clientDomainCounts as $client) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($client['client_id']) . "</td>";
        echo "<td>" . htmlspecialchars($client['client_name']) . "</td>";
        echo "<td>" . htmlspecialchars($client['domain_count']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Total Domains via Client Approach:</strong> $totalDomains</p>";
}

echo "<h3>4. Testing API Permissions</h3>";

// Test other API calls to see what works
$testCalls = [
    'GetProducts' => ['action' => 'GetProducts'],
    'GetOrders' => ['action' => 'GetOrders', 'limitnum' => 5],
    'GetInvoices' => ['action' => 'GetInvoices', 'limitnum' => 5]
];

foreach ($testCalls as $callName => $params) {
    $params['identifier'] = $settings['api_identifier'];
    $params['secret'] = $settings['api_secret'];
    $params['responsetype'] = 'json';
    
    $response = curlCall($settings['api_url'], $params);
    echo "<p><strong>$callName:</strong> " . ($response['result'] ?? 'Not set');
    if (isset($response['message'])) {
        echo " - " . htmlspecialchars($response['message']);
    }
    echo "</p>";
}

echo "<h2>Possible Causes & Solutions</h2>";
echo "<ul>";
echo "<li><strong>API User Permissions:</strong> The API user might only have access to certain clients/domains</li>";
echo "<li><strong>WHMCS Configuration:</strong> Domain access might be restricted in WHMCS settings</li>";
echo "<li><strong>Client Association:</strong> Some domains might not be associated with any client</li>";
echo "<li><strong>Domain Status Filter:</strong> WHMCS might be filtering by domain status</li>";
echo "<li><strong>Database Issues:</strong> WHMCS database might have orphaned domains</li>";
echo "</ul>";

echo "<h2>Recommendations</h2>";
echo "<ol>";
echo "<li><strong>Check WHMCS Admin:</strong> Go to Domains → Domain List and count manually</li>";
echo "<li><strong>API User Permissions:</strong> Verify the API user has full domain access</li>";
echo "<li><strong>Database Query:</strong> Check WHMCS database directly: SELECT COUNT(*) FROM tbldomains</li>";
echo "<li><strong>WHMCS Logs:</strong> Check WHMCS Activity Log for API access issues</li>";
echo "</ol>";

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 