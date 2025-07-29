<?php
// Simple, direct sync endpoint - bypasses complex auth system
session_start();

// Simple check - just verify user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Set JSON headers immediately
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
    exit;
}

// Get parameters
$batchNumber = isset($_POST['batch_number']) ? (int)$_POST['batch_number'] : 1;
$batchSize = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 10;
$userEmail = $_SESSION['user_email'] ?? '';

if (empty($userEmail)) {
    echo json_encode(['success' => false, 'error' => 'No user email in session']);
    exit;
}

try {
    // Load required files
    require_once 'config.php';
    require_once 'api.php';
    require_once 'database.php';
    require_once 'user_settings.php';

    // Get user settings
    $userSettings = new UserSettings();
    $settings = $userSettings->loadSettings($userEmail);
    
    if (!$settings) {
        echo json_encode(['success' => false, 'error' => 'User settings not found']);
        exit;
    }

    // Calculate offset
    $offset = ($batchNumber - 1) * $batchSize;

    // Get domains from WHMCS API
    $response = curlCall($settings['api_url'], [
        'action' => 'GetClientsDomains',
        'identifier' => $settings['api_identifier'],
        'secret' => $settings['api_secret'],
        'limitstart' => $offset,
        'limitnum' => $batchSize,
        'responsetype' => 'json'
    ]);

    if (!isset($response['result']) || $response['result'] !== 'success') {
        echo json_encode([
            'success' => false, 
            'error' => 'API call failed: ' . ($response['message'] ?? 'Unknown error')
        ]);
        exit;
    }

    if (!isset($response['domains']['domain'])) {
        echo json_encode([
            'success' => false, 
            'error' => 'No domains found in API response'
        ]);
        exit;
    }

    $domains = $response['domains']['domain'];
    $totalDomains = $response['totalresults'] ?? count($domains);

    // Initialize database
    $db = Database::getInstance();
    
    // Create sync log
    $logId = $db->createSyncLog($userEmail, $batchNumber);
    
    $syncData = [
        'domains_found' => count($domains),
        'domains_processed' => 0,
        'domains_updated' => 0,
        'domains_added' => 0,
        'errors' => 0,
        'status' => 'running',
        'total_domains' => $totalDomains,
        'batch_start' => $offset + 1,
        'batch_end' => $offset + count($domains)
    ];

    // Process each domain
    foreach ($domains as $domain) {
        try {
            // Prepare domain data
            $domainData = [
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
            ];

            // Check if domain exists
            $existingDomain = $db->getDomains(1, 1, $domain['domainname']);
            
            // Insert/update domain
            if ($db->insertDomain($domainData)) {
                $syncData['domains_processed']++;
                
                if (empty($existingDomain)) {
                    $syncData['domains_added']++;
                } else {
                    $syncData['domains_updated']++;
                }
            } else {
                $syncData['errors']++;
            }

        } catch (Exception $e) {
            $syncData['errors']++;
            error_log("Error processing domain " . ($domain['domainname'] ?? 'unknown') . ": " . $e->getMessage());
        }
    }

    $syncData['status'] = 'completed';

    // Update sync log
    $db->updateSyncLog($logId, $syncData);

    // Return success response
    echo json_encode([
        'success' => true,
        'log_id' => $logId,
        'data' => $syncData
    ]);

} catch (Exception $e) {
    error_log("Simple sync error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Simple sync fatal error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
}
?> 