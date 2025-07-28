<?php
// Ultra-minimal sync - no complex includes, fast execution
session_start();

// Set limits first
ini_set('max_execution_time', 90);
ini_set('memory_limit', '256M');

// Set JSON headers immediately
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Basic auth check
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

$batchNumber = isset($_POST['batch_number']) ? (int)$_POST['batch_number'] : 1;
$batchSize = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 10;
$userEmail = $_SESSION['user_email'] ?? '';

if (empty($userEmail)) {
    echo json_encode(['success' => false, 'error' => 'No user email']);
    exit;
}

try {
    // Minimal config loading
    require_once 'config.php';
    
    // Get user settings manually (avoid complex includes)
    $settingsFile = 'user_settings/' . md5($userEmail) . '.json';
    if (!file_exists($settingsFile)) {
        echo json_encode(['success' => false, 'error' => 'No settings file found']);
        exit;
    }
    
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings) {
        echo json_encode(['success' => false, 'error' => 'Could not load settings']);
        exit;
    }
    
    // Simple decryption function
    function simpleDecrypt($data) {
        $key = hash('sha256', ($_SERVER['SERVER_NAME'] ?? 'default_server') . (defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_encryption_key_2024'));
        $cipher = "AES-256-CBC";
        $data = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($data, 0, $ivlen);
        $encrypted = substr($data, $ivlen);
        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    }
    
    // Decrypt settings
    $apiUrl = $settings['api_url'];
    $apiIdentifier = simpleDecrypt($settings['api_identifier']);
    $apiSecret = simpleDecrypt($settings['api_secret']);
    
    // Calculate offset
    $offset = ($batchNumber - 1) * $batchSize;
    
    // Simple CURL call
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'GetClientsDomains',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'limitstart' => $offset,
        'limitnum' => $batchSize,
        'responsetype' => 'json'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Longer timeout
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo json_encode(['success' => false, 'error' => 'CURL error: ' . $error]);
        exit;
    }
    
    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'HTTP error: ' . $httpCode]);
        exit;
    }
    
    $apiResponse = json_decode($response, true);
    if (!$apiResponse) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON response']);
        exit;
    }
    
    if ($apiResponse['result'] !== 'success') {
        echo json_encode(['success' => false, 'error' => 'API error: ' . ($apiResponse['message'] ?? 'Unknown')]);
        exit;
    }
    
    if (!isset($apiResponse['domains']['domain'])) {
        echo json_encode(['success' => false, 'error' => 'No domains in response']);
        exit;
    }
    
    $domains = $apiResponse['domains']['domain'];
    $totalDomains = $apiResponse['totalresults'] ?? count($domains);
    
    // Simple database connection
    $dbHost = getEnvVar('DB_HOST', 'localhost');
    $dbPort = getEnvVar('DB_PORT', '3306');
    $dbName = getEnvVar('DB_NAME', 'domain_tools');
    $dbUser = getEnvVar('DB_USER', 'root');
    $dbPassword = getEnvVar('DB_PASSWORD', '');
    
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Simple function to get nameservers
    function getNameservers($apiUrl, $apiIdentifier, $apiSecret, $domainId) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'action' => 'DomainGetNameservers',
            'identifier' => $apiIdentifier,
            'secret' => $apiSecret,
            'domainid' => $domainId,
            'responsetype' => 'json'
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['result']) && $decoded['result'] === 'success') {
                return $decoded;
            }
        }
        return null;
    }
    
    // Process domains quickly
    $processed = 0;
    $added = 0;
    $updated = 0;
    $errors = 0;
    
    foreach ($domains as $domain) {
        try {
            // Quick domain insert/update
            $sql = "INSERT INTO domains (
                domain_id, domain_name, status, registrar, expiry_date, 
                registration_date, next_due_date, amount, currency, notes, batch_number,
                last_synced
            ) VALUES (
                :domain_id, :domain_name, :status, :registrar, :expiry_date,
                :registration_date, :next_due_date, :amount, :currency, :notes, :batch_number,
                NOW()
            ) ON DUPLICATE KEY UPDATE
                domain_name = VALUES(domain_name),
                status = VALUES(status),
                registrar = VALUES(registrar),
                expiry_date = VALUES(expiry_date),
                registration_date = VALUES(registration_date),
                next_due_date = VALUES(next_due_date),
                amount = VALUES(amount),
                currency = VALUES(currency),
                notes = VALUES(notes),
                batch_number = VALUES(batch_number),
                last_synced = NOW()
            ";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                'domain_id' => $domain['id'] ?? '',
                'domain_name' => $domain['domainname'] ?? '',
                'status' => $domain['status'] ?? 'Unknown',
                'registrar' => $domain['registrar'] ?? null,
                'expiry_date' => !empty($domain['expirydate']) ? date('Y-m-d', strtotime($domain['expirydate'])) : null,
                'registration_date' => !empty($domain['registrationdate']) ? date('Y-m-d', strtotime($domain['registrationdate'])) : null,
                'next_due_date' => !empty($domain['nextduedate']) ? date('Y-m-d', strtotime($domain['nextduedate'])) : null,
                'amount' => $domain['amount'] ?? null,
                'currency' => $domain['currency'] ?? null,
                'notes' => $domain['notes'] ?? null,
                'batch_number' => $batchNumber
            ]);
            
            if ($result) {
                $processed++;
                // Simple way to determine if added or updated
                if ($stmt->rowCount() == 1) {
                    $added++;
                } else {
                    $updated++;
                }
                
                // Fetch and store nameservers for this domain
                if (!empty($domain['id'])) {
                    $nsResponse = getNameservers($apiUrl, $apiIdentifier, $apiSecret, $domain['id']);
                    if ($nsResponse) {
                        // Insert/update nameservers
                        $nsSql = "INSERT INTO domain_nameservers (
                            domain_id, ns1, ns2, ns3, ns4, ns5
                        ) VALUES (
                            :domain_id, :ns1, :ns2, :ns3, :ns4, :ns5
                        ) ON DUPLICATE KEY UPDATE
                            ns1 = VALUES(ns1),
                            ns2 = VALUES(ns2),
                            ns3 = VALUES(ns3),
                            ns4 = VALUES(ns4),
                            ns5 = VALUES(ns5),
                            last_updated = CURRENT_TIMESTAMP
                        ";
                        
                        $nsStmt = $pdo->prepare($nsSql);
                        $nsStmt->execute([
                            'domain_id' => $domain['id'],
                            'ns1' => $nsResponse['ns1'] ?? null,
                            'ns2' => $nsResponse['ns2'] ?? null,
                            'ns3' => $nsResponse['ns3'] ?? null,
                            'ns4' => $nsResponse['ns4'] ?? null,
                            'ns5' => $nsResponse['ns5'] ?? null
                        ]);
                    }
                }
            }
            
        } catch (Exception $e) {
            $errors++;
        }
    }
    
    // Return simple success response
    echo json_encode([
        'success' => true,
        'data' => [
            'domains_found' => count($domains),
            'domains_processed' => $processed,
            'domains_added' => $added,
            'domains_updated' => $updated,
            'errors' => $errors,
            'total_domains' => $totalDomains,
            'batch_start' => $offset + 1,
            'batch_end' => $offset + count($domains),
            'status' => 'completed'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'error' => 'Fatal: ' . $e->getMessage()]);
}
?> 