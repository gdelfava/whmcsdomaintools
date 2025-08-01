<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

echo "<h1>Nameserver API Methods Test</h1>";

try {
    require_once 'config.php';
    require_once 'user_settings_db.php';
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
    
    // Get a sample domain
    $domainResponse = curlCall($apiUrl, [
        'action' => 'GetClientsDomains',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'limitnum' => 1,
        'responsetype' => 'json'
    ]);
    
    if (!isset($domainResponse['result']) || $domainResponse['result'] !== 'success' || 
        !isset($domainResponse['domains']['domain'])) {
        throw new Exception('Failed to get sample domain');
    }
    
    $sampleDomain = $domainResponse['domains']['domain'][0];
    $domainId = $sampleDomain['id'];
    $domainName = $sampleDomain['domainname'];
    
    echo "<h2>Testing Nameserver Methods for Domain: {$domainName} (ID: {$domainId})</h2>";
    
    // Method 1: DomainGetNameservers with domainid
    echo "<h3>Method 1: DomainGetNameservers (domainid)</h3>";
    $method1Response = curlCall($apiUrl, [
        'action' => 'DomainGetNameservers',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'domainid' => $domainId,
        'responsetype' => 'json'
    ]);
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($method1Response, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Method 2: DomainGetNameservers with domain name
    echo "<h3>Method 2: DomainGetNameservers (domain)</h3>";
    $method2Response = curlCall($apiUrl, [
        'action' => 'DomainGetNameservers',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'domain' => $domainName,
        'responsetype' => 'json'
    ]);
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($method2Response, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Method 3: GetDomainNameservers (without 'Domain' prefix)
    echo "<h3>Method 3: GetDomainNameservers (domainid)</h3>";
    $method3Response = curlCall($apiUrl, [
        'action' => 'GetDomainNameservers',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'domainid' => $domainId,
        'responsetype' => 'json'
    ]);
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($method3Response, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Method 4: GetDomainNameservers with domain name
    echo "<h3>Method 4: GetDomainNameservers (domain)</h3>";
    $method4Response = curlCall($apiUrl, [
        'action' => 'GetDomainNameservers',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'domain' => $domainName,
        'responsetype' => 'json'
    ]);
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($method4Response, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Method 5: DomainGetDetails
    echo "<h3>Method 5: DomainGetDetails (domainid)</h3>";
    $method5Response = curlCall($apiUrl, [
        'action' => 'DomainGetDetails',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'domainid' => $domainId,
        'responsetype' => 'json'
    ]);
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($method5Response, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Method 6: DomainGetDetails with domain name
    echo "<h3>Method 6: DomainGetDetails (domain)</h3>";
    $method6Response = curlCall($apiUrl, [
        'action' => 'DomainGetDetails',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'domain' => $domainName,
        'responsetype' => 'json'
    ]);
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($method6Response, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Method 7: Try with clientid parameter
    echo "<h3>Method 7: DomainGetNameservers with clientid</h3>";
    $method7Response = curlCall($apiUrl, [
        'action' => 'DomainGetNameservers',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'domainid' => $domainId,
        'clientid' => $sampleDomain['userid'],
        'responsetype' => 'json'
    ]);
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($method7Response, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Summary
    echo "<h2>Summary</h2>";
    $methods = [
        'Method 1 (DomainGetNameservers with domainid)' => $method1Response,
        'Method 2 (DomainGetNameservers with domain)' => $method2Response,
        'Method 3 (GetDomainNameservers with domainid)' => $method3Response,
        'Method 4 (GetDomainNameservers with domain)' => $method4Response,
        'Method 5 (DomainGetDetails with domainid)' => $method5Response,
        'Method 6 (DomainGetDetails with domain)' => $method6Response,
        'Method 7 (DomainGetNameservers with clientid)' => $method7Response
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Method</th><th>Result</th><th>Message</th></tr>";
    
    foreach ($methods as $methodName => $response) {
        $result = isset($response['result']) ? $response['result'] : 'unknown';
        $message = isset($response['message']) ? $response['message'] : '';
        $color = $result === 'success' ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($methodName) . "</td>";
        echo "<td style='color: {$color};'>" . htmlspecialchars($result) . "</td>";
        echo "<td>" . htmlspecialchars($message) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Error $e) {
    echo "<p>❌ <strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='main_page.php?view=sync'>← Back to Sync Interface</a></p>";
?> 