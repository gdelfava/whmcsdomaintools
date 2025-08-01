<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

echo "<h1>Nameserver API Debug</h1>";

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
    
    echo "<h2>API Configuration</h2>";
    echo "<p><strong>API URL:</strong> " . htmlspecialchars($apiUrl) . "</p>";
    echo "<p><strong>API Identifier:</strong> " . htmlspecialchars($apiIdentifier) . "</p>";
    echo "<p><strong>API Secret:</strong> " . str_repeat('*', strlen($apiSecret)) . "</p>";
    
    // Test 1: Get a sample domain first
    echo "<h2>Test 1: Get Sample Domain</h2>";
    
    $domainResponse = curlCall($apiUrl, [
        'action' => 'GetClientsDomains',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'limitnum' => 1,
        'responsetype' => 'json'
    ]);
    
    echo "<p><strong>Domain API Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($domainResponse, JSON_PRETTY_PRINT)) . "</pre>";
    
    if (isset($domainResponse['result']) && $domainResponse['result'] === 'success' && 
        isset($domainResponse['domains']['domain'])) {
        
        $sampleDomain = $domainResponse['domains']['domain'][0];
        $domainId = $sampleDomain['id'];
        $domainName = $sampleDomain['domainname'];
        
        echo "<p><strong>Sample Domain:</strong> {$domainName} (ID: {$domainId})</p>";
        
        // Test 2: Get nameservers for this domain
        echo "<h2>Test 2: Get Nameservers</h2>";
        
        echo "<p><strong>Nameserver API Request:</strong></p>";
        $nameserverRequest = [
            'action' => 'DomainGetNameservers',
            'identifier' => $apiIdentifier,
            'secret' => $apiSecret,
            'domainid' => $domainId,
            'responsetype' => 'json'
        ];
        echo "<pre>" . htmlspecialchars(json_encode($nameserverRequest, JSON_PRETTY_PRINT)) . "</pre>";
        
        $nameserverResponse = curlCall($apiUrl, $nameserverRequest);
        
        echo "<p><strong>Nameserver API Response:</strong></p>";
        echo "<pre>" . htmlspecialchars(json_encode($nameserverResponse, JSON_PRETTY_PRINT)) . "</pre>";
        
        // Test 3: Try different API actions that might return nameservers
        echo "<h2>Test 3: Alternative API Actions</h2>";
        
        // Try DomainGetDetails
        echo "<h3>DomainGetDetails</h3>";
        $detailsResponse = curlCall($apiUrl, [
            'action' => 'DomainGetDetails',
            'identifier' => $apiIdentifier,
            'secret' => $apiSecret,
            'domainid' => $domainId,
            'responsetype' => 'json'
        ]);
        
        echo "<p><strong>DomainGetDetails Response:</strong></p>";
        echo "<pre>" . htmlspecialchars(json_encode($detailsResponse, JSON_PRETTY_PRINT)) . "</pre>";
        
        // Try GetDomainNameservers (without 'Domain' prefix)
        echo "<h3>GetDomainNameservers</h3>";
        $altNameserverResponse = curlCall($apiUrl, [
            'action' => 'GetDomainNameservers',
            'identifier' => $apiIdentifier,
            'secret' => $apiSecret,
            'domainid' => $domainId,
            'responsetype' => 'json'
        ]);
        
        echo "<p><strong>GetDomainNameservers Response:</strong></p>";
        echo "<pre>" . htmlspecialchars(json_encode($altNameserverResponse, JSON_PRETTY_PRINT)) . "</pre>";
        
        // Test 4: Manual CURL test
        echo "<h2>Test 4: Manual CURL Test</h2>";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($nameserverRequest));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: DomainTools-Debug/1.0'
        ]);
        
        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        echo "<p><strong>HTTP Code:</strong> {$httpCode}</p>";
        if ($curlError) {
            echo "<p><strong>CURL Error:</strong> " . htmlspecialchars($curlError) . "</p>";
        }
        
        echo "<p><strong>Raw Response:</strong></p>";
        echo "<pre>" . htmlspecialchars($rawResponse) . "</pre>";
        
        // Test 5: Parse the raw response
        echo "<h2>Test 5: Response Analysis</h2>";
        
        $parsedResponse = json_decode($rawResponse, true);
        if ($parsedResponse) {
            echo "<p>✅ <strong>Response is valid JSON</strong></p>";
            echo "<p><strong>Parsed Response:</strong></p>";
            echo "<pre>" . htmlspecialchars(json_encode($parsedResponse, JSON_PRETTY_PRINT)) . "</pre>";
            
            // Check for nameserver data in different possible locations
            echo "<h3>Nameserver Data Search</h3>";
            
            $nameserverKeys = ['nameservers', 'nameserver', 'ns', 'dns', 'servers'];
            foreach ($nameserverKeys as $key) {
                if (isset($parsedResponse[$key])) {
                    echo "<p>✅ Found '{$key}' key: " . htmlspecialchars(json_encode($parsedResponse[$key])) . "</p>";
                }
            }
            
            // Search recursively
            function searchForNameservers($data, $path = '') {
                $results = [];
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $currentPath = $path ? $path . '.' . $key : $key;
                        if (stripos($key, 'nameserver') !== false || stripos($key, 'ns') !== false) {
                            $results[] = [$currentPath, $value];
                        }
                        if (is_array($value)) {
                            $results = array_merge($results, searchForNameservers($value, $currentPath));
                        }
                    }
                }
                return $results;
            }
            
            $foundNameservers = searchForNameservers($parsedResponse);
            if (!empty($foundNameservers)) {
                echo "<p><strong>Found nameserver-related data:</strong></p>";
                foreach ($foundNameservers as $found) {
                    echo "<p>Path: {$found[0]} - Value: " . htmlspecialchars(json_encode($found[1])) . "</p>";
                }
            } else {
                echo "<p>❌ No nameserver-related data found in response</p>";
            }
            
        } else {
            echo "<p>❌ <strong>Response is not valid JSON</strong></p>";
            echo "<p><strong>JSON Error:</strong> " . json_last_error_msg() . "</p>";
        }
        
    } else {
        echo "<p>❌ <strong>Failed to get sample domain</strong></p>";
        if (isset($domainResponse['message'])) {
            echo "<p><strong>Error:</strong> " . htmlspecialchars($domainResponse['message']) . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Error $e) {
    echo "<p>❌ <strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='main_page.php?view=sync'>← Back to Sync Interface</a></p>";
?> 