<?php
require_once 'auth.php';
require_once 'api.php';
require_once 'database.php';
require_once 'user_settings_db.php';

class DomainSync {
    private $db;
    private $userSettings;
    private $userEmail;
    
    public function __construct($userEmail) {
        $this->db = Database::getInstance();
        $this->userEmail = $userEmail;
        $this->userSettings = getUserSettingsDB();
        
        if (!$this->userSettings) {
            throw new Exception("User settings not found");
        }
    }
    
    public function syncBatch($batchNumber = 1, $batchSize = 200) {
        $offset = ($batchNumber - 1) * $batchSize;
        
        // Create sync log entry
        $logId = $this->db->createSyncLog($this->userEmail, $batchNumber);
        if (!$logId) {
            throw new Exception("Failed to create sync log");
        }
        
        $syncData = [
            'domains_found' => 0,
            'domains_processed' => 0,
            'domains_updated' => 0,
            'domains_added' => 0,
            'errors' => 0,
            'status' => 'running'
        ];
        
        try {
            // Get domains from WHMCS API for this batch
            $domainsResponse = getDomainsForExport(
                $this->userSettings['api_url'],
                $this->userSettings['api_identifier'],
                $this->userSettings['api_secret'],
                $batchSize,
                $offset
            );
            
            // Log the raw response for debugging
            error_log("WHMCS API Response: " . json_encode($domainsResponse));
            
            if (!isset($domainsResponse['domains']['domain']) || $domainsResponse['result'] !== 'success') {
                $errorMessage = "Failed to fetch domains from WHMCS API";
                if (isset($domainsResponse['message'])) {
                    $errorMessage .= ": " . $domainsResponse['message'];
                } elseif (isset($domainsResponse['result'])) {
                    $errorMessage .= ": API returned result '" . $domainsResponse['result'] . "'";
                } else {
                    $errorMessage .= ": No valid response from API";
                }
                throw new Exception($errorMessage);
            }
            
            $domains = $domainsResponse['domains']['domain'];
            $syncData['domains_found'] = count($domains);
            $syncData['total_domains'] = $domainsResponse['totalresults'] ?? count($domains);
            $syncData['batch_start'] = $offset + 1;
            $syncData['batch_end'] = $offset + count($domains);
            
            // Process each domain
            foreach ($domains as $domain) {
                try {
                    $this->processDomain($domain, $batchNumber);
                    $syncData['domains_processed']++;
                    
                    // Check if domain was added or updated
                    $existingDomain = $this->db->getDomains(1, 1, $domain['domainname']);
                    if (empty($existingDomain)) {
                        $syncData['domains_added']++;
                    } else {
                        $syncData['domains_updated']++;
                    }
                    
                } catch (Exception $e) {
                    $syncData['errors']++;
                    error_log("Error processing domain {$domain['domainname']}: " . $e->getMessage());
                }
            }
            
            $syncData['status'] = 'completed';
            
        } catch (Exception $e) {
            $syncData['status'] = 'failed';
            $syncData['error_message'] = $e->getMessage();
            error_log("Sync failed: " . $e->getMessage());
        }
        
        // Update sync log
        $this->db->updateSyncLog($logId, $syncData);
        
        return [
            'success' => $syncData['status'] === 'completed',
            'log_id' => $logId,
            'data' => $syncData
        ];
    }
    
    private function processDomain($domain, $batchNumber) {
        // Prepare domain data for database
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
        
        // Insert/update domain in database
        if (!$this->db->insertDomain($this->userEmail, $domainData)) {
            throw new Exception("Failed to insert domain: " . $domain['domainname']);
        }
        
        // Get nameservers for the domain
        if (!empty($domain['id'])) {
            $this->fetchAndStoreNameservers($domain['id']);
        }
    }
    
    private function fetchAndStoreNameservers($domainId) {
        try {
            $nsResponse = getDomainNameservers(
                $this->userSettings['api_url'],
                $this->userSettings['api_identifier'],
                $this->userSettings['api_secret'],
                $domainId
            );
            
            if (isset($nsResponse['result']) && $nsResponse['result'] === 'success') {
                $nameservers = [
                    'ns1' => $nsResponse['ns1'] ?? null,
                    'ns2' => $nsResponse['ns2'] ?? null,
                    'ns3' => $nsResponse['ns3'] ?? null,
                    'ns4' => $nsResponse['ns4'] ?? null,
                    'ns5' => $nsResponse['ns5'] ?? null
                ];
                
                $this->db->insertNameservers($this->userEmail, $domainId, $nameservers);
            }
        } catch (Exception $e) {
            // Log error but don't fail the entire sync
            error_log("Failed to fetch nameservers for domain $domainId: " . $e->getMessage());
        }
    }
    
    public function getSyncStatus($logId) {
        $sql = "SELECT * FROM sync_logs WHERE id = :log_id";
        
        try {
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute(['log_id' => $logId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting sync status: " . $e->getMessage());
            return null;
        }
    }
    
    public function getLastSyncInfo() {
        return $this->db->getLastSyncInfo($this->userEmail);
    }
    
    public function clearOldData($daysOld = 30) {
        return $this->db->clearOldData($daysOld);
    }
}

// Handle AJAX requests for sync operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Increase limits for large dataset processing
    ini_set('memory_limit', '512M');
    ini_set('max_execution_time', 300); // 5 minutes
    
    // Set proper JSON headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    requireAuth();
    
    $userEmail = $_SESSION['user_email'] ?? '';
    if (empty($userEmail)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    try {
        $sync = new DomainSync($userEmail);
        
        switch ($_POST['action']) {
            case 'sync_batch':
                $batchNumber = isset($_POST['batch_number']) ? (int)$_POST['batch_number'] : 1;
                $result = $sync->syncBatch($batchNumber);
                echo json_encode($result);
                break;
                
            case 'get_sync_status':
                $logId = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
                $status = $sync->getSyncStatus($logId);
                echo json_encode($status);
                break;
                
            case 'get_last_sync':
                $lastSync = $sync->getLastSyncInfo();
                echo json_encode($lastSync);
                break;
                
            case 'clear_old_data':
                $daysOld = isset($_POST['days_old']) ? (int)$_POST['days_old'] : 30;
                $result = $sync->clearOldData($daysOld);
                echo json_encode(['success' => $result]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
        
    } catch (Exception $e) {
        // Log the full error for debugging
        error_log("Domain Sync Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB'
            ]
        ]);
    } catch (Error $e) {
        // Catch PHP fatal errors
        error_log("Domain Sync Fatal Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $e->getMessage(),
            'debug' => [
                'type' => 'Fatal Error',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
    }
    
    exit;
}
?> 