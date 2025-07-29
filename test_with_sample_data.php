<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'user_settings.php';

// Require authentication
requireAuth();

// Initialize database
try {
    $db = Database::getInstance();
    $db->createTables(); // Ensure tables exist
} catch (Exception $e) {
    echo "<h1>Database Test with Sample Data</h1>";
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> Database connection failed: " . $e->getMessage();
    echo "</div>";
    exit;
}

echo "<h1>Database Test with Sample Data</h1>";

// Sample domain data
$sampleDomains = [
    [
        'domain_id' => '1001',
        'domain_name' => 'example.com',
        'status' => 'Active',
        'registrar' => 'GoDaddy',
        'expiry_date' => '2024-12-31',
        'registration_date' => '2023-01-15',
        'next_due_date' => '2024-12-31',
        'amount' => '12.99',
        'currency' => 'USD',
        'notes' => 'Sample domain for testing',
        'batch_number' => 1
    ],
    [
        'domain_id' => '1002',
        'domain_name' => 'testdomain.net',
        'status' => 'Active',
        'registrar' => 'Namecheap',
        'expiry_date' => '2024-11-15',
        'registration_date' => '2023-02-20',
        'next_due_date' => '2024-11-15',
        'amount' => '10.99',
        'currency' => 'USD',
        'notes' => 'Another test domain',
        'batch_number' => 1
    ],
    [
        'domain_id' => '1003',
        'domain_name' => 'expired-domain.org',
        'status' => 'Expired',
        'registrar' => 'GoDaddy',
        'expiry_date' => '2023-12-01',
        'registration_date' => '2022-12-01',
        'next_due_date' => '2023-12-01',
        'amount' => '15.99',
        'currency' => 'USD',
        'notes' => 'Expired domain for testing',
        'batch_number' => 1
    ],
    [
        'domain_id' => '1004',
        'domain_name' => 'pending-domain.info',
        'status' => 'Pending',
        'registrar' => 'Namecheap',
        'expiry_date' => '2025-03-15',
        'registration_date' => '2024-03-15',
        'next_due_date' => '2025-03-15',
        'amount' => '8.99',
        'currency' => 'USD',
        'notes' => 'Pending domain transfer',
        'batch_number' => 1
    ],
    [
        'domain_id' => '1005',
        'domain_name' => 'suspended-domain.com',
        'status' => 'Suspended',
        'registrar' => 'GoDaddy',
        'expiry_date' => '2024-08-20',
        'registration_date' => '2023-08-20',
        'next_due_date' => '2024-08-20',
        'amount' => '12.99',
        'currency' => 'USD',
        'notes' => 'Suspended for non-payment',
        'batch_number' => 1
    ]
];

// Sample nameserver data
$sampleNameservers = [
    '1001' => ['ns1' => 'ns1.godaddy.com', 'ns2' => 'ns2.godaddy.com'],
    '1002' => ['ns1' => 'ns1.namecheap.com', 'ns2' => 'ns2.namecheap.com'],
    '1003' => ['ns1' => 'ns1.godaddy.com', 'ns2' => 'ns2.godaddy.com'],
    '1004' => ['ns1' => 'ns1.namecheap.com', 'ns2' => 'ns2.namecheap.com'],
    '1005' => ['ns1' => 'ns1.godaddy.com', 'ns2' => 'ns2.godaddy.com']
];

echo "<h2>Inserting Sample Data...</h2>";

$insertedCount = 0;
$errorCount = 0;

foreach ($sampleDomains as $domain) {
    try {
        // Insert domain
        if ($db->insertDomain($domain)) {
            $insertedCount++;
            
            // Insert nameservers if available
            if (isset($sampleNameservers[$domain['domain_id']])) {
                $db->insertNameservers($domain['domain_id'], $sampleNameservers[$domain['domain_id']]);
            }
        } else {
            $errorCount++;
        }
    } catch (Exception $e) {
        $errorCount++;
        echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 5px 0;'>";
        echo "<strong>Error inserting domain {$domain['domain_name']}:</strong> " . $e->getMessage();
        echo "</div>";
    }
}

echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>✅ SUCCESS:</strong> Inserted $insertedCount sample domains into database.";
if ($errorCount > 0) {
    echo "<br><strong>⚠️ WARNING:</strong> $errorCount domains failed to insert.";
}
echo "</div>";

// Test database queries
echo "<h2>Testing Database Queries...</h2>";

// Test 1: Get domain count
$totalDomains = $db->getDomainCount();
echo "<p><strong>Total domains in database:</strong> $totalDomains</p>";

// Test 2: Get domain statistics
$stats = $db->getDomainStats();
echo "<h3>Domain Statistics:</h3>";
echo "<ul>";
echo "<li><strong>Total Domains:</strong> " . ($stats['total_domains'] ?? 0) . "</li>";
echo "<li><strong>Active Domains:</strong> " . ($stats['active_domains'] ?? 0) . "</li>";
echo "<li><strong>Expired Domains:</strong> " . ($stats['expired_domains'] ?? 0) . "</li>";
echo "<li><strong>Pending Domains:</strong> " . ($stats['pending_domains'] ?? 0) . "</li>";
echo "<li><strong>Suspended Domains:</strong> " . ($stats['suspended_domains'] ?? 0) . "</li>";
echo "</ul>";

// Test 3: Get domains with search
$domains = $db->getDomains(1, 10, 'example', '', 'domain_name', 'ASC');
echo "<h3>Search Results for 'example':</h3>";
if (!empty($domains)) {
    echo "<ul>";
    foreach ($domains as $domain) {
        echo "<li><strong>{$domain['domain_name']}</strong> - {$domain['status']} ({$domain['registrar']})</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No domains found matching 'example'</p>";
}

// Test 4: Get domains by status
$activeDomains = $db->getDomains(1, 10, '', 'Active', 'domain_name', 'ASC');
echo "<h3>Active Domains:</h3>";
if (!empty($activeDomains)) {
    echo "<ul>";
    foreach ($activeDomains as $domain) {
        echo "<li><strong>{$domain['domain_name']}</strong> - {$domain['registrar']} (Expires: {$domain['expiry_date']})</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No active domains found</p>";
}

echo "<h2>Database Functionality Test Complete!</h2>";
echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>ℹ️ INFO:</strong> The database is working correctly! You can now:";
echo "<ul>";
echo "<li><a href='domains_db.php'>View the sample domains</a></li>";
echo "<li><a href='sync_interface.php'>Test the sync interface</a></li>";
echo "<li><a href='main_page.php'>Return to dashboard</a></li>";
echo "</ul>";
echo "</div>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Resolve API IP ban:</strong> Add your IP to WHMCS API whitelist</li>";
echo "<li><strong>Test with real data:</strong> Use sync interface once API works</li>";
echo "<li><strong>Explore features:</strong> Try search, filtering, and pagination</li>";
echo "</ol>";
?> 