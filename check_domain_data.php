<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

echo "<h1>Domain Data Structure Check</h1>";

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
    
    // Get a sample domain to check its data structure
    $domainResponse = curlCall($apiUrl, [
        'action' => 'GetClientsDomains',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'limitnum' => 1,
        'responsetype' => 'json'
    ]);
    
    echo "<h2>GetClientsDomains Response Structure</h2>";
    echo "<p><strong>Full Response:</strong></p>";
    echo "<pre>" . htmlspecialchars(json_encode($domainResponse, JSON_PRETTY_PRINT)) . "</pre>";
    
    if (isset($domainResponse['result']) && $domainResponse['result'] === 'success' && 
        isset($domainResponse['domains']['domain'])) {
        
        $sampleDomain = $domainResponse['domains']['domain'][0];
        
        echo "<h2>Sample Domain Data Structure</h2>";
        echo "<p><strong>Domain:</strong> " . htmlspecialchars($sampleDomain['domainname']) . "</p>";
        echo "<p><strong>Domain ID:</strong> " . htmlspecialchars($sampleDomain['id']) . "</p>";
        
        echo "<h3>All Available Fields:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field Name</th><th>Value</th><th>Type</th></tr>";
        
        foreach ($sampleDomain as $field => $value) {
            $type = is_array($value) ? 'array' : gettype($value);
            echo "<tr>";
            echo "<td>" . htmlspecialchars($field) . "</td>";
            echo "<td>" . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . "</td>";
            echo "<td>" . htmlspecialchars($type) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Check for nameserver-related fields
        echo "<h3>Nameserver-Related Fields:</h3>";
        $nameserverFields = [];
        foreach ($sampleDomain as $field => $value) {
            if (stripos($field, 'nameserver') !== false || stripos($field, 'ns') !== false || 
                stripos($field, 'dns') !== false || stripos($field, 'server') !== false) {
                $nameserverFields[$field] = $value;
            }
        }
        
        if (!empty($nameserverFields)) {
            echo "<p>✅ <strong>Found nameserver-related fields:</strong></p>";
            foreach ($nameserverFields as $field => $value) {
                echo "<p><strong>{$field}:</strong> " . htmlspecialchars(is_array($value) ? json_encode($value) : $value) . "</p>";
            }
        } else {
            echo "<p>❌ <strong>No nameserver-related fields found in domain data</strong></p>";
        }
        
        // Check if there are any additional fields that might contain nameserver info
        echo "<h3>Potential Nameserver Sources:</h3>";
        $potentialSources = [];
        foreach ($sampleDomain as $field => $value) {
            if (is_string($value) && (stripos($value, 'ns') !== false || stripos($value, 'dns') !== false)) {
                $potentialSources[$field] = $value;
            }
        }
        
        if (!empty($potentialSources)) {
            echo "<p>✅ <strong>Found potential nameserver data in these fields:</strong></p>";
            foreach ($potentialSources as $field => $value) {
                echo "<p><strong>{$field}:</strong> " . htmlspecialchars($value) . "</p>";
            }
        } else {
            echo "<p>❌ <strong>No potential nameserver data found in string fields</strong></p>";
        }
        
    } else {
        echo "<p>❌ <strong>Failed to get domain data</strong></p>";
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