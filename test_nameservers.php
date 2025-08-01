<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

echo "<h1>Nameserver Test</h1>";

try {
    require_once 'config.php';
    require_once 'user_settings_db.php';
    require_once 'database_v2.php';
    require_once 'api.php';
    
    $userEmail = $_SESSION['user_email'] ?? '';
    $companyId = $_SESSION['company_id'] ?? null;
    
    if (empty($userEmail)) {
        throw new Exception('No user email in session');
    }
    
    if (empty($companyId)) {
        throw new Exception('No company ID in session');
    }
    
    // Get user settings
    $userSettings = new UserSettingsDB();
    $settings = $userSettings->loadSettings($companyId, $userEmail);
    
    if (!$settings) {
        throw new Exception('User settings not found');
    }
    
    $apiUrl = $settings['api_url'];
    $apiIdentifier = $settings['api_identifier'];
    $apiSecret = $settings['api_secret'];
    
    // Get database instance
    $db = Database::getInstance();
    
    // Get a sample domain from the database
    $domains = $db->getDomains($companyId, $userEmail, 1, 1);
    
    if (empty($domains)) {
        echo "<p>❌ No domains found in database. Please run a sync first.</p>";
        echo "<p><a href='main_page.php?view=sync'>← Back to Sync Interface</a></p>";
        exit;
    }
    
    $sampleDomain = $domains[0];
    $domainId = $sampleDomain['domain_id'];
    $domainName = $sampleDomain['domain_name'];
    
    echo "<h2>Testing Nameserver Fetch for Domain: {$domainName}</h2>";
    echo "<p><strong>Domain ID:</strong> {$domainId}</p>";
    
    // Test 1: Get nameservers from API
    echo "<h3>Test 1: API Nameserver Fetch</h3>";
    
    $nameserverResponse = curlCall($apiUrl, [
        'action' => 'DomainGetNameservers',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'domainid' => $domainId,
        'responsetype' => 'json'
    ]);
    
    echo "<p><strong>API Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($nameserverResponse, JSON_PRETTY_PRINT)) . "</pre>";
    
    if (isset($nameserverResponse['result']) && $nameserverResponse['result'] === 'success') {
        echo "<p>✅ <strong>API call successful</strong></p>";
        
        // Extract nameservers
        $nameservers = [];
        if (isset($nameserverResponse['nameservers']['nameserver'])) {
            $nsList = $nameserverResponse['nameservers']['nameserver'];
            if (is_array($nsList)) {
                foreach ($nsList as $index => $ns) {
                    $nameservers['ns' . ($index + 1)] = $ns;
                }
            } else {
                $nameservers['ns1'] = $nsList;
            }
        }
        
        echo "<p><strong>Extracted Nameservers:</strong></p>";
        echo "<pre>" . htmlspecialchars(json_encode($nameservers, JSON_PRETTY_PRINT)) . "</pre>";
        
        // Test 2: Store nameservers in database
        echo "<h3>Test 2: Database Storage</h3>";
        
        if (!empty($nameservers)) {
            if ($db->insertNameservers($companyId, $userEmail, $domainId, $nameservers)) {
                echo "<p>✅ <strong>Nameservers stored successfully</strong></p>";
            } else {
                echo "<p>❌ <strong>Failed to store nameservers</strong></p>";
            }
        } else {
            echo "<p>⚠️ <strong>No nameservers found in API response</strong></p>";
        }
        
        // Test 3: Retrieve nameservers from database
        echo "<h3>Test 3: Database Retrieval</h3>";
        
        $storedNameservers = $db->getNameservers($companyId, $userEmail, $domainId);
        
        if ($storedNameservers) {
            echo "<p>✅ <strong>Nameservers retrieved from database:</strong></p>";
            echo "<pre>" . htmlspecialchars(json_encode($storedNameservers, JSON_PRETTY_PRINT)) . "</pre>";
        } else {
            echo "<p>❌ <strong>No nameservers found in database</strong></p>";
        }
        
    } else {
        echo "<p>❌ <strong>API call failed</strong></p>";
        if (isset($nameserverResponse['message'])) {
            echo "<p><strong>Error:</strong> " . htmlspecialchars($nameserverResponse['message']) . "</p>";
        }
    }
    
    // Test 4: Check all domains for nameservers
    echo "<h3>Test 4: All Domains Nameserver Status</h3>";
    
    $allDomains = $db->getDomains($companyId, $userEmail, 1, 10);
    $domainsWithNS = 0;
    $domainsWithoutNS = 0;
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Domain</th><th>Domain ID</th><th>Nameservers</th><th>Status</th></tr>";
    
    foreach ($allDomains as $domain) {
        $ns = $db->getNameservers($companyId, $userEmail, $domain['domain_id']);
        $hasNS = !empty($ns) && (!empty($ns['ns1']) || !empty($ns['ns2']));
        
        if ($hasNS) {
            $domainsWithNS++;
            $status = "✅ Has NS";
        } else {
            $domainsWithoutNS++;
            $status = "❌ No NS";
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($domain['domain_name']) . "</td>";
        echo "<td>" . htmlspecialchars($domain['domain_id']) . "</td>";
        echo "<td>" . htmlspecialchars(json_encode($ns)) . "</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<p><strong>Summary:</strong></p>";
    echo "<ul>";
    echo "<li>Domains with nameservers: {$domainsWithNS}</li>";
    echo "<li>Domains without nameservers: {$domainsWithoutNS}</li>";
    echo "<li>Total domains checked: " . count($allDomains) . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Error $e) {
    echo "<p>❌ <strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='main_page.php?view=sync'>← Back to Sync Interface</a></p>";
?> 