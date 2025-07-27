<?php
require_once 'config.php';
require_once 'cache.php';

function curlCall($url, $postData) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }

    curl_close($ch);

    return json_decode($response, true);
}

function getAllDomains($url, $identifier, $secret) {
    // Use caching to improve performance
    $userEmail = $_SESSION['user_email'] ?? 'unknown';
    $cacheKey = 'all_domains_' . md5($url . $identifier);
    
    return getCachedApiResponse($cacheKey, $userEmail, function() use ($url, $identifier, $secret) {
        // Try direct GetClientsDomains call first (should return all domains)
        $response = curlCall($url, [
            'action'      => 'GetClientsDomains',
            'identifier'  => $identifier,
            'secret'      => $secret,
            'responsetype'=> 'json',
            'limitnum'    => 1000
        ]);
    
    // Add debug information to see what we're getting
    if (isset($_POST['export_csv'])) {
        echo '<p>Direct GetClientsDomains API call result: ' . ($response['result'] ?? 'no result field') . '</p>';
        echo '<p>Raw response keys: ' . implode(', ', array_keys($response)) . '</p>';
        flush();
    }
    
    // If direct call works, return it
    if (isset($response['domains']['domain']) && !empty($response['domains']['domain'])) {
        return $response;
    }
    
    // If direct call fails, try the client-by-client approach as fallback
    if (isset($_POST['export_csv'])) {
        echo '<p>Direct call returned no domains, trying client-by-client approach...</p>';
        flush();
    }
    
    // Fallback: Get all clients first, then their domains
    $clientsResponse = curlCall($url, [
        'action'      => 'GetClients',
        'identifier'  => $identifier,
        'secret'      => $secret,
        'responsetype'=> 'json',
        'limitnum'    => 1000
    ]);
    
    if (isset($_POST['export_csv'])) {
        echo '<p>GetClients result: ' . ($clientsResponse['result'] ?? 'no result field') . '</p>';
        echo '<p>Number of clients found: ' . (isset($clientsResponse['clients']['client']) ? count($clientsResponse['clients']['client']) : 0) . '</p>';
        flush();
    }
    
    $allDomains = [];
    
    if (isset($clientsResponse['clients']['client']) && is_array($clientsResponse['clients']['client'])) {
        // For each client, get their domains
        foreach ($clientsResponse['clients']['client'] as $client) {
            $clientId = $client['id'];
            
            if (isset($_POST['export_csv'])) {
                echo '<p>Getting domains for client ID: ' . $clientId . '</p>';
                flush();
            }
            
            $domainsResponse = curlCall($url, [
                'action'      => 'GetClientsDomains',
                'identifier'  => $identifier,
                'secret'      => $secret,
                'clientid'    => $clientId,
                'responsetype'=> 'json',
                'limitnum'    => 1000
            ]);
            
            if (isset($domainsResponse['domains']['domain']) && is_array($domainsResponse['domains']['domain'])) {
                $domainCount = count($domainsResponse['domains']['domain']);
                if (isset($_POST['export_csv'])) {
                    echo '<p>Found ' . $domainCount . ' domains for client ' . $clientId . '</p>';
                    flush();
                }
                $allDomains = array_merge($allDomains, $domainsResponse['domains']['domain']);
            }
        }
    }
        
    return ['domains' => ['domain' => $allDomains]];
    }, 300); // Cache for 5 minutes
}

function updateNameservers($url, $identifier, $secret, $domain, $ns1 = null, $ns2 = null) {
    // Get default nameservers from config if not provided
    if ($ns1 === null || $ns2 === null) {
        global $defaultNs1, $defaultNs2;
        $ns1 = $ns1 ?? $defaultNs1;
        $ns2 = $ns2 ?? $defaultNs2;
    }

    $postData = [
        'action'      => 'DomainUpdateNameservers',
        'identifier'  => $identifier,
        'secret'      => $secret,
        'domain'      => $domain,
        'ns1'         => $ns1,
        'ns2'         => $ns2,
        'responsetype'=> 'json'
    ];

    return curlCall($url, $postData);
}

function getDomainNameservers($url, $identifier, $secret, $domainId) {
    return curlCall($url, [
        'action' => 'DomainGetNameservers',
        'identifier' => $identifier,
        'secret' => $secret,
        'domainid' => $domainId,
        'responsetype' => 'json'
    ]);
}

function getDomainsForExport($url, $identifier, $secret, $batchSize, $offset) {
    return curlCall($url, [
        'action' => 'GetClientsDomains',
        'identifier' => $identifier,
        'secret' => $secret,
        'limitstart' => $offset,
        'limitnum' => $batchSize,
        'responsetype' => 'json'
    ]);
}

function testApiConnection($url, $identifier, $secret) {
    // Test the API connection by making a simple call
    $response = curlCall($url, [
        'action' => 'GetClients',
        'identifier' => $identifier,
        'secret' => $secret,
        'responsetype' => 'json',
        'limitnum' => 1
    ]);
    
    // Check if the response indicates success
    if (isset($response['result']) && $response['result'] === 'success') {
        return [
            'result' => 'success',
            'message' => 'API connection successful'
        ];
    } else {
        return [
            'result' => 'error',
            'message' => isset($response['message']) ? $response['message'] : 'Unknown API error'
        ];
    }
}

function getRegistrars($url, $identifier, $secret) {
    return curlCall($url, [
        'action' => 'GetRegistrars',
        'identifier' => $identifier,
        'secret' => $secret,
        'responsetype' => 'json'
    ]);
}

function getHealthStatus($url, $identifier, $secret) {
    return curlCall($url, [
        'action' => 'GetHealthStatus',
        'identifier' => $identifier,
        'secret' => $secret,
        'fetchStatus' => true,
        'responsetype' => 'json'
    ]);
}

function getServers($url, $identifier, $secret) {
    return curlCall($url, [
        'action' => 'GetServers',
        'identifier' => $identifier,
        'secret' => $secret,
        'fetchStatus' => true,
        'responsetype' => 'json'
    ]);
}
?> 