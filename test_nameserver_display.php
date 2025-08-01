<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

echo "<h1>Nameserver Display Test</h1>";

try {
    require_once 'config.php';
    require_once 'database_v2.php';
    
    $userEmail = $_SESSION['user_email'] ?? '';
    $companyId = $_SESSION['company_id'] ?? null;
    
    if (empty($userEmail)) {
        throw new Exception('No user email in session');
    }
    
    if (empty($companyId)) {
        throw new Exception('No company ID in session');
    }
    
    // Get database instance
    $db = Database::getInstance();
    
    // Get domains with nameservers
    $domains = $db->getDomains($companyId, $userEmail, 1, 10);
    
    echo "<h2>Domains with Nameserver Information</h2>";
    
    if (empty($domains)) {
        echo "<p>❌ No domains found in database.</p>";
        echo "<p><a href='main_page.php?view=sync'>← Back to Sync Interface</a></p>";
        exit;
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>Domain Name</th>";
    echo "<th>Status</th>";
    echo "<th>Registrar</th>";
    echo "<th>NS1</th>";
    echo "<th>NS2</th>";
    echo "<th>NS3</th>";
    echo "<th>NS4</th>";
    echo "<th>NS5</th>";
    echo "<th>Nameserver Updated</th>";
    echo "</tr>";
    
    foreach ($domains as $domain) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($domain['domain_name']) . "</td>";
        echo "<td>" . htmlspecialchars($domain['status'] ?? 'Unknown') . "</td>";
        echo "<td>" . htmlspecialchars($domain['registrar'] ?? 'Unknown') . "</td>";
        echo "<td>" . htmlspecialchars($domain['ns1'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($domain['ns2'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($domain['ns3'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($domain['ns4'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($domain['ns5'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($domain['nameserver_updated'] ?? 'Never') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Summary
    $domainsWithNS = 0;
    $domainsWithoutNS = 0;
    
    foreach ($domains as $domain) {
        if (!empty($domain['ns1']) || !empty($domain['ns2'])) {
            $domainsWithNS++;
        } else {
            $domainsWithoutNS++;
        }
    }
    
    echo "<h3>Summary</h3>";
    echo "<ul>";
    echo "<li>Domains with nameservers: {$domainsWithNS}</li>";
    echo "<li>Domains without nameservers: {$domainsWithoutNS}</li>";
    echo "<li>Total domains checked: " . count($domains) . "</li>";
    echo "</ul>";
    
    if ($domainsWithNS > 0) {
        echo "<p>✅ <strong>Nameservers are being fetched and displayed correctly!</strong></p>";
    } else {
        echo "<p>❌ <strong>No nameservers found. You may need to run a sync first.</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Error $e) {
    echo "<p>❌ <strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='main_page.php?view=sync'>← Back to Sync Interface</a></p>";
?> 