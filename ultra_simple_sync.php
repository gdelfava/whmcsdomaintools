<?php
// Ultra-minimal sync - no complex includes, fast execution
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    // Load database and settings
    require_once 'config.php';
    require_once 'database.php';
    require_once 'user_settings_db.php';
    
    // Get user settings from database
    $userSettings = new UserSettingsDB();
    $settings = $userSettings->loadSettings($userEmail);
    
    if (!$settings) {
        echo json_encode(['success' => false, 'error' => 'No settings found for user']);
        exit;
    }
    
    // Decrypt settings
    $apiUrl = $settings['api_url'];
    $apiIdentifier = $userSettings->decrypt($settings['api_identifier']);
    $apiSecret = $userSettings->decrypt($settings['api_secret']);
    
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
            // Extract domain data
            $domainId = $domain['id'] ?? '';
            $domainName = $domain['domainname'] ?? '';
            
            if (empty($domainId) || empty($domainName)) {
                // Skip invalid domains
                $errors++;
                continue;
            }
            
            // Check if domain already exists for current user
            $checkSql = "SELECT id, domain_id, domain_name, status FROM domains WHERE user_email = :user_email AND (domain_id = :domain_id OR domain_name = :domain_name) LIMIT 1";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([
                'user_email' => $userEmail,
                'domain_id' => $domainId,
                'domain_name' => $domainName
            ]);
            $existingDomain = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Prepare domain data
            $domainData = [
                'domain_id' => $domainId,
                'domain_name' => $domainName,
                'status' => $domain['status'] ?? 'Unknown',
                'registrar' => $domain['registrar'] ?? null,
                'expiry_date' => !empty($domain['expirydate']) ? date('Y-m-d', strtotime($domain['expirydate'])) : null,
                'registration_date' => !empty($domain['registrationdate']) ? date('Y-m-d', strtotime($domain['registrationdate'])) : null,
                'next_due_date' => !empty($domain['nextduedate']) ? date('Y-m-d', strtotime($domain['nextduedate'])) : null,
                'amount' => $domain['amount'] ?? null,
                'currency' => $domain['currency'] ?? null,
                'notes' => $domain['notes'] ?? null,
                'batch_number' => $batchNumber
            ];
            
            // Insert or update based on check
            if ($existingDomain) {
                // Domain exists - update it
                $sql = "UPDATE domains SET 
                    domain_name = :domain_name,
                    status = :status,
                    registrar = :registrar,
                    expiry_date = :expiry_date,
                    registration_date = :registration_date,
                    next_due_date = :next_due_date,
                    amount = :amount,
                    currency = :currency,
                    notes = :notes,
                    batch_number = :batch_number,
                    last_synced = NOW()
                WHERE user_email = :user_email AND domain_id = :domain_id";
                
                $domainData['user_email'] = $userEmail;
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute($domainData);
                
                if ($result) {
                    $processed++;
                    $updated++;
                }
            } else {
                // New domain - insert it
                $sql = "INSERT INTO domains (
                    user_email, domain_id, domain_name, status, registrar, expiry_date, 
                    registration_date, next_due_date, amount, currency, notes, batch_number,
                    last_synced
                ) VALUES (
                    :user_email, :domain_id, :domain_name, :status, :registrar, :expiry_date,
                    :registration_date, :next_due_date, :amount, :currency, :notes, :batch_number,
                    NOW()
                )";
                
                $domainData['user_email'] = $userEmail;
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute($domainData);
                
                if ($result) {
                    $processed++;
                    $added++;
                }
            }
                
                // Fetch and store nameservers for this domain
                if (!empty($domainId) && $result) {
                    // First check if nameservers already exist for current user
                    $checkNsSql = "SELECT domain_id FROM domain_nameservers WHERE user_email = :user_email AND domain_id = :domain_id LIMIT 1";
                    $checkNsStmt = $pdo->prepare($checkNsSql);
                    $checkNsStmt->execute(['user_email' => $userEmail, 'domain_id' => $domainId]);
                    $existingNs = $checkNsStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Only fetch nameservers if we need to add or update them
                    $nsResponse = getNameservers($apiUrl, $apiIdentifier, $apiSecret, $domainId);
                    if ($nsResponse) {
                        $nsData = [
                            'domain_id' => $domainId,
                            'ns1' => $nsResponse['ns1'] ?? null,
                            'ns2' => $nsResponse['ns2'] ?? null,
                            'ns3' => $nsResponse['ns3'] ?? null,
                            'ns4' => $nsResponse['ns4'] ?? null,
                            'ns5' => $nsResponse['ns5'] ?? null
                        ];
                        
                        if ($existingNs) {
                            // Update existing nameservers
                            $nsSql = "UPDATE domain_nameservers SET 
                                ns1 = :ns1,
                                ns2 = :ns2,
                                ns3 = :ns3,
                                ns4 = :ns4,
                                ns5 = :ns5,
                                last_updated = NOW()
                            WHERE user_email = :user_email AND domain_id = :domain_id";
                        } else {
                            // Insert new nameservers
                            $nsSql = "INSERT INTO domain_nameservers (
                                user_email, domain_id, ns1, ns2, ns3, ns4, ns5, last_updated
                            ) VALUES (
                                :user_email, :domain_id, :ns1, :ns2, :ns3, :ns4, :ns5, NOW()
                            )";
                        }
                        
                        $nsData['user_email'] = $userEmail;
                        $nsStmt = $pdo->prepare($nsSql);
                        $nsStmt->execute($nsData);
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