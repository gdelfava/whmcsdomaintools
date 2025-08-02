<?php
// Ultra-minimal sync - no complex includes, fast execution
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set limits first
ini_set('max_execution_time', 90);
ini_set('memory_limit', '256M');

// Start output buffering to prevent any unexpected output
ob_start();

// Set up error handler to catch any PHP errors
function handleError($errno, $errstr, $errfile, $errline) {
    $error = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error);
    
    // Clean output buffer and return JSON error
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'error' => 'PHP Error: ' . $errstr,
        'details' => "File: $errfile, Line: $errline"
    ]);
    exit;
}

// Set error handler
set_error_handler('handleError');

// Set up shutdown function to catch fatal errors
function handleShutdown() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Fatal Error: ' . $error['message'],
            'details' => "File: {$error['file']}, Line: {$error['line']}"
        ]);
    }
}

register_shutdown_function('handleShutdown');

// Set JSON headers immediately
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Basic auth check
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

$action = $_POST['action'] ?? 'sync';
$batchNumber = isset($_POST['batch_number']) ? (int)$_POST['batch_number'] : 1;
$batchSize = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 10;
$userEmail = $_SESSION['user_email'] ?? '';
$companyId = $_SESSION['company_id'] ?? null;

if (empty($userEmail)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'No user email']);
    exit;
}

if (empty($companyId)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'No company ID']);
    exit;
}

try {
    // Load database and settings
    require_once 'config.php';
    require_once 'database_v2.php';
    require_once 'user_settings_db.php';
    
    // Handle progress checking
    if ($action === 'check_progress') {
        // Get progress from session
        $progressKey = "sync_progress_{$batchNumber}_{$batchSize}";
        $progress = $_SESSION[$progressKey] ?? null;
        
        if ($progress) {
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'data' => $progress
            ]);
            exit;
        } else {
            ob_end_clean();
            echo json_encode([
                'success' => false,
                'error' => 'No progress data found'
            ]);
            exit;
        }
    }
    
    // Get user settings from database
    $userSettings = new UserSettingsDB();
    $settings = $userSettings->loadSettings($companyId, $userEmail);
    
    if (!$settings) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No settings found for user']);
        exit;
    }
    
    // Decrypt settings
    $apiUrl = $settings['api_url'];
    $apiIdentifier = $settings['api_identifier'];
    $apiSecret = $settings['api_secret'];
    
    // Calculate offset
    $offset = ($batchNumber - 1) * $batchSize;
    
    // Use the improved API call function
    require_once 'api.php';
    
    $apiResponse = curlCall($apiUrl, [
        'action' => 'GetClientsDomains',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'limitstart' => $offset,
        'limitnum' => $batchSize,
        'responsetype' => 'json'
    ]);
    
    // Check for API errors
    if (isset($apiResponse['error'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'CURL error: ' . $apiResponse['error']]);
        exit;
    }
    
    if (isset($apiResponse['result']) && $apiResponse['result'] === 'error') {
        $errorMsg = $apiResponse['message'] ?? 'Unknown API error';
        if (isset($apiResponse['raw_response'])) {
            $errorMsg .= ' (Raw response: ' . $apiResponse['raw_response'] . ')';
        }
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'API error: ' . $errorMsg]);
        exit;
    }
    
    if (!isset($apiResponse['result']) || $apiResponse['result'] !== 'success') {
        $errorMsg = 'API call failed';
        if (isset($apiResponse['message'])) {
            $errorMsg .= ': ' . $apiResponse['message'];
        }
        if (isset($apiResponse['raw_response'])) {
            $errorMsg .= ' (Raw response: ' . $apiResponse['raw_response'] . ')';
        }
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }
    
    if (!isset($apiResponse['domains']['domain'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No domains in response']);
        exit;
    }
    
    $domains = $apiResponse['domains']['domain'];
    $totalDomains = $apiResponse['totalresults'] ?? count($domains);
    
    // Use the Database class instead of direct PDO
    $db = Database::getInstance();
    
    // Ensure tables exist
    $db->createTables();
    
    // Process domains quickly
    $processed = 0;
    $added = 0;
    $updated = 0;
    $errors = 0;
    $currentIndex = 0;
    
    // Initialize progress tracking
    $progressKey = "sync_progress_{$batchNumber}_{$batchSize}";
    $_SESSION[$progressKey] = [
        'domains_found' => count($domains),
        'domains_processed' => 0,
        'domains_added' => 0,
        'domains_updated' => 0,
        'errors' => 0,
        'total_domains' => $totalDomains,
        'batch_start' => $offset + 1,
        'batch_end' => $offset + count($domains),
        'status' => 'processing',
        'current_domain' => '',
        'current_index' => 0
    ];
    
    foreach ($domains as $domain) {
        $currentIndex++;
        $domainName = $domain['domainname'] ?? 'Unknown';
        
        // Update progress with current domain
        $_SESSION[$progressKey]['current_domain'] = $domainName;
        $_SESSION[$progressKey]['current_index'] = $currentIndex;
        $_SESSION[$progressKey]['domains_processed'] = $processed;
        $_SESSION[$progressKey]['domains_added'] = $added;
        $_SESSION[$progressKey]['domains_updated'] = $updated;
        $_SESSION[$progressKey]['errors'] = $errors;
        try {
            // Extract domain data
            $domainId = $domain['id'] ?? '';
            $domainName = $domain['domainname'] ?? '';
            
            if (empty($domainId) || empty($domainName)) {
                // Skip invalid domains
                $errors++;
                continue;
            }
            
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
            
            // Check if domain already exists BEFORE inserting
            $existingDomain = $db->getDomains($companyId, $userEmail, 1, 1, $domainName);
            $isNewDomain = empty($existingDomain);
            
            // Use the Database class to insert/update domain
            if ($db->insertDomain($companyId, $userEmail, $domainData)) {
                $processed++;
                
                // Update counters based on whether it was new or existing
                if ($isNewDomain) {
                    $added++;
                } else {
                    $updated++;
                }
                
                // Fetch and store nameservers for this domain
                try {
                    error_log("Fetching nameservers for domain: {$domainName} (ID: {$domainId})");
                    
                    // Use the working method: DomainGetNameservers with domainid
                    $nameserverResponse = curlCall($apiUrl, [
                        'action' => 'DomainGetNameservers',
                        'identifier' => $apiIdentifier,
                        'secret' => $apiSecret,
                        'domainid' => $domainId,
                        'responsetype' => 'json'
                    ]);
                    
                    error_log("Nameserver API response for {$domainName}: " . json_encode($nameserverResponse));
                    
                    if (isset($nameserverResponse['result']) && $nameserverResponse['result'] === 'success') {
                        // Extract nameservers from response
                        $nameservers = [];
                        
                        // Check for direct nameserver fields (ns1, ns2, etc.)
                        for ($i = 1; $i <= 5; $i++) {
                            $nsKey = "ns{$i}";
                            if (isset($nameserverResponse[$nsKey]) && !empty($nameserverResponse[$nsKey])) {
                                $nameservers[$nsKey] = $nameserverResponse[$nsKey];
                            }
                        }
                        
                        // Also check for nested nameservers structure
                        if (empty($nameservers) && isset($nameserverResponse['nameservers']['nameserver'])) {
                            $nsList = $nameserverResponse['nameservers']['nameserver'];
                            if (is_array($nsList)) {
                                foreach ($nsList as $index => $ns) {
                                    $nameservers['ns' . ($index + 1)] = $ns;
                                }
                            } else {
                                $nameservers['ns1'] = $nsList;
                            }
                        }
                        
                        error_log("Extracted nameservers for {$domainName}: " . json_encode($nameservers));
                        
                        // Store nameservers in database
                        if (!empty($nameservers)) {
                            if ($db->insertNameservers($companyId, $userEmail, $domainId, $nameservers)) {
                                error_log("Successfully stored nameservers for domain: {$domainName}");
                            } else {
                                error_log("Failed to insert nameservers for domain: {$domainName}");
                            }
                        } else {
                            error_log("No nameservers found for domain: {$domainName}");
                        }
                    } else {
                        error_log("Failed to get nameservers for domain: {$domainName}");
                        if (isset($nameserverResponse['message'])) {
                            error_log("Nameserver API error: " . $nameserverResponse['message']);
                        }
                    }
                    
                } catch (Exception $e) {
                    error_log("Exception getting nameservers for domain {$domainName}: " . $e->getMessage());
                } catch (Error $e) {
                    error_log("Fatal error getting nameservers for domain {$domainName}: " . $e->getMessage());
                }
            } else {
                $errors++;
                error_log("Failed to insert domain: {$domainName}");
                error_log("Domain data: " . json_encode($domainData));
            }
            
        } catch (Exception $e) {
            $errors++;
            error_log("Error processing domain {$domainName}: " . $e->getMessage());
            error_log("Domain data: " . json_encode($domainData));
            error_log("Stack trace: " . $e->getTraceAsString());
        } catch (Error $e) {
            $errors++;
            error_log("Fatal error processing domain {$domainName}: " . $e->getMessage());
            error_log("Domain data: " . json_encode($domainData));
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    // Update final progress and mark as completed
    $_SESSION[$progressKey]['domains_processed'] = $processed;
    $_SESSION[$progressKey]['domains_added'] = $added;
    $_SESSION[$progressKey]['domains_updated'] = $updated;
    $_SESSION[$progressKey]['errors'] = $errors;
    $_SESSION[$progressKey]['status'] = 'completed';
    $_SESSION[$progressKey]['current_domain'] = '';
    
    // Return simple success response
    ob_end_clean();
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
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Fatal: ' . $e->getMessage()]);
}
?> 