<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

require_once 'config.php';
require_once 'database_v2.php';
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

echo "<h1>Sync Error Debug</h1>";
echo "<p><strong>User Email:</strong> " . htmlspecialchars($userEmail) . "</p>";
echo "<p><strong>Company ID:</strong> " . htmlspecialchars($companyId) . "</p>";

// Get user settings
$userSettings = new UserSettingsDB();
$settings = $userSettings->loadSettings($companyId, $userEmail);

if (!$settings) {
    echo "<p>❌ <strong>No settings found for user</strong></p>";
    exit;
}

echo "<p>✅ <strong>Settings loaded successfully</strong></p>";

// Test database connection
$db = Database::getInstance();
if (!$db->isConnected()) {
    echo "<p>❌ <strong>Database connection failed</strong></p>";
    exit;
}

echo "<p>✅ <strong>Database connected successfully</strong></p>";

// Test API connection
require_once 'api.php';

$apiResponse = curlCall($settings['api_url'], [
    'action' => 'GetClientsDomains',
    'identifier' => $settings['api_identifier'],
    'secret' => $settings['api_secret'],
    'limitstart' => 0,
    'limitnum' => 5,
    'responsetype' => 'json'
]);

echo "<h2>API Test Results</h2>";
echo "<pre>" . htmlspecialchars(json_encode($apiResponse, JSON_PRETTY_PRINT)) . "</pre>";

if (isset($apiResponse['result']) && $apiResponse['result'] === 'success') {
    echo "<p>✅ <strong>API connection successful</strong></p>";
    
    if (isset($apiResponse['domains']['domain'])) {
        $domains = $apiResponse['domains']['domain'];
        echo "<p><strong>Domains found:</strong> " . count($domains) . "</p>";
        
        echo "<h3>Sample Domain Data:</h3>";
        if (!empty($domains)) {
            $sampleDomain = $domains[0];
            echo "<pre>" . htmlspecialchars(json_encode($sampleDomain, JSON_PRETTY_PRINT)) . "</pre>";
        }
        
        // Test domain insertion
        echo "<h2>Domain Insertion Test</h2>";
        
        if (!empty($domains)) {
            $testDomain = $domains[0];
            $domainData = [
                'domain_id' => $testDomain['id'] ?? 'test_' . time(),
                'domain_name' => $testDomain['domainname'] ?? 'test.com',
                'status' => $testDomain['status'] ?? 'Unknown',
                'registrar' => $testDomain['registrar'] ?? null,
                'expiry_date' => !empty($testDomain['expirydate']) ? date('Y-m-d', strtotime($testDomain['expirydate'])) : null,
                'registration_date' => !empty($testDomain['registrationdate']) ? date('Y-m-d', strtotime($testDomain['registrationdate'])) : null,
                'next_due_date' => !empty($testDomain['nextduedate']) ? date('Y-m-d', strtotime($testDomain['nextduedate'])) : null,
                'amount' => $testDomain['amount'] ?? null,
                'currency' => $testDomain['currency'] ?? null,
                'notes' => $testDomain['notes'] ?? null,
                'batch_number' => 1
            ];
            
            echo "<h4>Test Domain Data:</h4>";
            echo "<pre>" . htmlspecialchars(json_encode($domainData, JSON_PRETTY_PRINT)) . "</pre>";
            
            try {
                $result = $db->insertDomain($companyId, $userEmail, $domainData);
                if ($result) {
                    echo "<p>✅ <strong>Domain insertion successful</strong></p>";
                    
                    // Test domain retrieval
                    $retrievedDomains = $db->getDomains($companyId, $userEmail, 1, 1, $domainData['domain_name']);
                    if (!empty($retrievedDomains)) {
                        echo "<p>✅ <strong>Domain retrieval successful</strong></p>";
                        echo "<pre>" . htmlspecialchars(json_encode($retrievedDomains[0], JSON_PRETTY_PRINT)) . "</pre>";
                    } else {
                        echo "<p>❌ <strong>Domain retrieval failed</strong></p>";
                    }
                } else {
                    echo "<p>❌ <strong>Domain insertion failed</strong></p>";
                }
            } catch (Exception $e) {
                echo "<p>❌ <strong>Domain insertion error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p><strong>Error details:</strong></p>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            }
        }
    } else {
        echo "<p>❌ <strong>No domains in API response</strong></p>";
    }
} else {
    echo "<p>❌ <strong>API connection failed</strong></p>";
    if (isset($apiResponse['message'])) {
        echo "<p><strong>Error:</strong> " . htmlspecialchars($apiResponse['message']) . "</p>";
    }
}

// Check recent sync logs
echo "<h2>Recent Sync Logs</h2>";

try {
    $recentLogs = $db->getRecentSyncLogs(5);
    if (!empty($recentLogs)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>User Email</th><th>Batch</th><th>Status</th><th>Found</th><th>Processed</th><th>Added</th><th>Updated</th><th>Errors</th><th>Started</th></tr>";
        
        foreach ($recentLogs as $log) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($log['id']) . "</td>";
            echo "<td>" . htmlspecialchars($log['user_email']) . "</td>";
            echo "<td>" . htmlspecialchars($log['batch_number']) . "</td>";
            echo "<td>" . htmlspecialchars($log['status']) . "</td>";
            echo "<td>" . htmlspecialchars($log['domains_found']) . "</td>";
            echo "<td>" . htmlspecialchars($log['domains_processed']) . "</td>";
            echo "<td>" . htmlspecialchars($log['domains_added']) . "</td>";
            echo "<td>" . htmlspecialchars($log['domains_updated']) . "</td>";
            echo "<td>" . htmlspecialchars($log['errors']) . "</td>";
            echo "<td>" . htmlspecialchars($log['sync_started']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No recent sync logs found</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ <strong>Error getting sync logs:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check database table structure
echo "<h2>Database Table Check</h2>";

try {
    $db->createTables();
    echo "<p>✅ <strong>Tables created/verified successfully</strong></p>";
} catch (Exception $e) {
    echo "<p>❌ <strong>Table creation error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check current domain count
echo "<h2>Current Domain Count</h2>";

try {
    $domainCount = $db->getDomainCount($companyId, $userEmail);
    echo "<p><strong>Total domains in database:</strong> " . $domainCount . "</p>";
} catch (Exception $e) {
    echo "<p>❌ <strong>Error getting domain count:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Recommendations</h2>";
echo "<ul>";

if (isset($apiResponse['result']) && $apiResponse['result'] !== 'success') {
    echo "<li>🚨 <strong>API Issue:</strong> Check WHMCS API configuration and credentials</li>";
}

if (isset($apiResponse['domains']['domain']) && empty($apiResponse['domains']['domain'])) {
    echo "<li>🚨 <strong>No Domains:</strong> Check if the API user has access to domains</li>";
}

echo "<li>🔧 <strong>Check PHP error logs</strong> for detailed error messages</li>";
echo "<li>🔧 <strong>Verify database permissions</strong> for the web server user</li>";
echo "<li>🔧 <strong>Test with smaller batch size</strong> (try batch size 1)</li>";
echo "</ul>";

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 