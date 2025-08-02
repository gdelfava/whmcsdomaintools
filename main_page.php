<?php
// Increase PHP timeout limits for long-running operations
ini_set('max_execution_time', 1200); // 20 minutes
ini_set('memory_limit', '1024M'); // Increase memory limit
set_time_limit(1200); // Set script timeout to 20 minutes

// Set FastCGI timeout headers to prevent 30-second timeout
if (function_exists('fastcgi_finish_request')) {
    // This is a FastCGI environment
    // Try to set timeout via headers
    header('X-FastCGI-Timeout: 1200');
}

// Additional timeout prevention
ignore_user_abort(true);

require_once 'auth_v2.php';
require_once 'api.php';
require_once 'user_settings_db.php';
require_once 'cache.php';
require_once 'database_v2.php';

// Require authentication
requireAuth();

// Initialize database connection
$db = Database::getInstance();

// Check if user has settings configured
$hasSettings = userHasSettingsDB();
$settingsValidation = validateSettingsCompletenessDB();

// Handle logout
if (isset($_POST['logout'])) {
    logoutUser();
}

// Handle settings save
$message = '';
$messageType = '';
if (isset($_POST['save_settings']) && isServerAdmin()) {
    error_log("Save settings form submitted by user: " . ($_SESSION['user_email'] ?? 'unknown'));
    error_log("Company ID: " . ($_SESSION['company_id'] ?? 'not set'));
    error_log("POST data: " . print_r($_POST, true));
    
    $requiredFields = ['api_url', 'api_identifier', 'api_secret', 'default_ns1', 'default_ns2'];
    $allFieldsProvided = true;
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            error_log("Missing required field: " . $field);
            $allFieldsProvided = false;
            break;
        }
    }
    
    if ($allFieldsProvided) {
        $settings = [
            'api_url' => trim($_POST['api_url']),
            'api_identifier' => trim($_POST['api_identifier']),
            'api_secret' => trim($_POST['api_secret']),
            'default_ns1' => trim($_POST['default_ns1']),
            'default_ns2' => trim($_POST['default_ns2'])
        ];
        
        error_log("Settings to save: " . print_r($settings, true));
        
        $userSettings = new UserSettingsDB();
        

        
        if ($userSettings->saveSettings($_SESSION['company_id'], $_SESSION['user_email'], $settings)) {
            $message = 'Settings saved successfully!';
            $messageType = 'success';
            error_log("Settings saved successfully for user: " . $_SESSION['user_email']);
            
            // Clear user cache when settings change
            clearUserCache($_SESSION['user_email']);
            
            // Refresh settings status
            $hasSettings = userHasSettingsDB();
            $settingsValidation = validateSettingsCompletenessDB();
        } else {
            $message = 'Failed to save settings. Please try again.';
            $messageType = 'error';
            error_log("Failed to save settings for user: " . $_SESSION['user_email']);
        }
    } else {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
        error_log("Not all required fields provided");
    }
}

// Handle settings test
if (isset($_POST['test_settings']) && isServerAdmin()) {
    $userSettings = getUserSettingsDB();
    if ($userSettings) {
        // Test API connection
        $testResponse = curlCall($userSettings['api_url'], [
            'action' => 'GetClients',
            'identifier' => $userSettings['api_identifier'],
            'secret' => $userSettings['api_secret'],
            'responsetype' => 'json',
            'limitnum' => 1
        ]);
        
        if (isset($testResponse['result']) && $testResponse['result'] === 'success') {
            $message = 'API connection test successful!';
            $messageType = 'success';
        } else {
            $error = $testResponse['message'] ?? 'Unknown error';
            $message = 'API connection test failed: ' . htmlspecialchars($error);
            $messageType = 'error';
        }
    } else {
        $message = 'No settings found. Please save your settings first.';
        $messageType = 'error';
    }
}

// Handle user profile save
if (isset($_POST['save_profile'])) {
    $user = $db->getUserByEmail($_SESSION['user_email']);
    
    if ($user) {
        $userData = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? '')
        ];
        
        if ($db->updateUser($user['id'], $userData)) {
            $message = 'User profile updated successfully!';
            $messageType = 'success';
            error_log('User profile updated successfully for user: ' . $_SESSION['user_email']);
        } else {
            $message = 'Failed to update user profile. Please try again.';
            $messageType = 'error';
            error_log('Failed to update user profile for user: ' . $_SESSION['user_email']);
        }
    } else {
        $message = 'User not found. Please log in again.';
        $messageType = 'error';
    }
}

// Handle company settings save
if (isset($_POST['save_company'])) {
    $companyData = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'company_address' => trim($_POST['company_address'] ?? ''),
        'contact_number' => trim($_POST['contact_number'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'logo_url' => trim($_POST['company_logo_url'] ?? '')
    ];
    
    if ($db->updateCompany($_SESSION['company_id'], $companyData)) {
        $message = 'Company settings updated successfully!';
        $messageType = 'success';
        error_log('Company settings updated successfully for company: ' . $_SESSION['company_id']);
    } else {
        $message = 'Failed to update company settings. Please try again.';
        $messageType = 'error';
        error_log('Failed to update company settings for company: ' . $_SESSION['company_id']);
    }
}

// Load existing settings
$currentSettings = getUserSettingsDB();

// Load user profile and company data
$currentUser = $db->getUserByEmail($_SESSION['user_email'] ?? '');
$currentCompany = $db->getCompany($_SESSION['company_id'] ?? 0);

// Determine current view
$currentView = $_GET['view'] ?? 'dashboard';

// Initialize uniqueRegistrars for all views
$uniqueRegistrars = [];
try {
    $db = Database::getInstance();
    $userEmail = $_SESSION['user_email'] ?? '';
    $companyId = $_SESSION['company_id'] ?? null;
    if (!empty($userEmail) && !empty($companyId)) {
        $uniqueRegistrars = $db->getUniqueRegistrars($companyId, $userEmail);
    } else {
        $uniqueRegistrars = [];
    }
} catch (Exception $e) {
    // Fallback to empty array if database fails
    $uniqueRegistrars = [];
}

// Handle nameserver updates
$updateMessage = '';
$updateResults = [];
$allDomains = [];

if ($currentView === 'nameservers') {
    // Clear cache if user requests cache clear
    if (isset($_GET['clear_cache'])) {
        $cache = new SimpleCache();
        // Clear all domain-related cache for this user
        $cleared = $cache->clearUserCache($_SESSION['user_email']);
        $updateMessage = "Cache cleared ($cleared files removed). Refreshing...";
        header('Location: ?view=nameservers');
        exit;
    }
    
    // Check if user has configured their API settings
    if (!userHasSettingsDB()) {
        $updateMessage = 'Please configure your API settings first.';
    } else {
        // Load user settings
        $userSettings = getUserSettingsDB();
        if (!$userSettings) {
            $updateMessage = 'Unable to load your API settings. Please configure them first.';
        } else {
            // Force refresh bypass cache
            if (isset($_GET['force_refresh'])) {
                $cache = new SimpleCache();
                $cleared = $cache->clearUserCache($_SESSION['user_email']);
                // Force a fresh API call by temporarily disabling cache
                $response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
                $allDomains = $response['domains']['domain'] ?? [];
                $updateMessage = "Forced refresh completed (bypassed cache). Found " . count($allDomains) . " domains.";
            } else {
                // Get all domains
                $response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
                $allDomains = $response['domains']['domain'] ?? [];
            }
            
            // Sort domains alphabetically by domainname (case-insensitive)
            // Ensure we have valid domain data before sorting
            if (!empty($allDomains) && is_array($allDomains)) {
                // First, ensure all domains have valid domainname field
                $allDomains = array_filter($allDomains, function($domain) {
                    return isset($domain['domainname']) && !empty($domain['domainname']);
                });
                
                // Sort domains alphabetically by domainname (case-insensitive)
                usort($allDomains, function($a, $b) {
                    $domainA = strtolower($a['domainname'] ?? '');
                    $domainB = strtolower($b['domainname'] ?? '');
                    return strcmp($domainA, $domainB);
                });
            }
        }
    }
    
    // Handle nameserver update form submission
    if (isset($_POST['update']) && !empty($_POST['domain']) && is_array($_POST['domain']) && $userSettings) {
        $domainsToUpdate = $_POST['domain'];
        $successCount = 0;
        $failureCount = 0;
        
        // Create user-specific log file
        $logFile = 'logs/ns_update_log_' . md5($_SESSION['user_email']) . '.txt';
        
        // Create logs directory if it doesn't exist
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        foreach ($domainsToUpdate as $domainToUpdate) {
            $result = updateNameservers($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $domainToUpdate, $userSettings['default_ns1'], $userSettings['default_ns2']);
            
            $status = isset($result['result']) && $result['result'] == 'success' ? 'SUCCESS' : 'FAILED';
            $logEntry = date('Y-m-d H:i:s') . " - $domainToUpdate - $status - " . ($result['message'] ?? '') . "\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            
            $updateResults[] = [
                'domain' => $domainToUpdate,
                'status' => $status,
                'message' => $result['message'] ?? ''
            ];
            
            if ($status === 'SUCCESS') {
                $successCount++;
            } else {
                $failureCount++;
            }
            
            // Small delay between requests to avoid overwhelming the API
            usleep(500000); // 0.5 second delay
        }
        
                 $totalUpdated = count($domainsToUpdate);
         $updateMessage = "Batch update completed: $successCount successful, $failureCount failed out of $totalUpdated total domains.";
     }
 }

// Handle database setup
$dbMessage = '';
$dbMessageType = '';
$dbTestResult = null;

if ($currentView === 'database_setup') {
    // Function to test database connection
    function testDatabaseConnection($host, $port, $database, $username, $password) {
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return ['success' => true, 'message' => 'Database connection successful'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    // Function to create database if it doesn't exist
    function createDatabaseIfNotExists($host, $port, $username, $password, $database) {
        try {
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return ['success' => true, 'message' => "Database '$database' created successfully"];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to create database: ' . $e->getMessage()];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = trim($_POST['db_host'] ?? '');
        $port = trim($_POST['db_port'] ?? '3306');
        $database = trim($_POST['db_name'] ?? '');
        $username = trim($_POST['db_user'] ?? '');
        $password = trim($_POST['db_password'] ?? '');
        
        if (empty($host) || empty($database) || empty($username)) {
            $dbMessage = 'Please fill in all required fields.';
            $dbMessageType = 'error';
        } else {
            // First try to create database if it doesn't exist
            $createResult = createDatabaseIfNotExists($host, $port, $username, $password, $database);
            
            if ($createResult['success']) {
                // Test connection to the specific database
                $dbTestResult = testDatabaseConnection($host, $port, $database, $username, $password);
                
                if ($dbTestResult['success']) {
                    // Save to .env file
                    $envContent = file_get_contents('.env') ?: '';
                    $envLines = explode("\n", $envContent);
                    
                    $envVars = [
                        'DB_HOST' => $host,
                        'DB_PORT' => $port,
                        'DB_NAME' => $database,
                        'DB_USER' => $username,
                        'DB_PASSWORD' => $password
                    ];
                    
                    foreach ($envVars as $key => $value) {
                        $found = false;
                        foreach ($envLines as $i => $line) {
                            if (strpos($line, $key . '=') === 0) {
                                $envLines[$i] = "$key=$value";
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $envLines[] = "$key=$value";
                        }
                    }
                    
                    $newEnvContent = implode("\n", $envLines);
                    if (file_put_contents('.env', $newEnvContent)) {
                        $dbMessage = 'Database configuration saved successfully! You can now use the database features.';
                        $dbMessageType = 'success';
                    } else {
                        $dbMessage = 'Database connection successful, but failed to save configuration. Please check file permissions.';
                        $dbMessageType = 'warning';
                    }
                } else {
                    $dbMessage = $dbTestResult['message'];
                    $dbMessageType = 'error';
                }
            } else {
                $dbMessage = $createResult['message'];
                $dbMessageType = 'error';
            }
        }
    }

    // Get current database settings
    $currentHost = getEnvVar('DB_HOST', 'localhost');
    $currentPort = getEnvVar('DB_PORT', '3306');
    $currentDatabase = getEnvVar('DB_NAME', 'domain_tools');
    $currentUser = getEnvVar('DB_USER', 'root');
    $currentPassword = getEnvVar('DB_PASSWORD', '');
}

// Handle create tables
$createTablesMessage = '';
$createTablesMessageType = '';

if ($currentView === 'create_tables') {
    try {
        // Get database instance
        $db = Database::getInstance();
        
        // Create tables
        if ($db->createTables()) {
            $createTablesMessage = '✅ SUCCESS: Database tables created successfully!';
            $createTablesMessageType = 'success';
        } else {
            $createTablesMessage = '❌ ERROR: Failed to create database tables.';
            $createTablesMessageType = 'error';
        }
    } catch (Exception $e) {
        $createTablesMessage = '❌ ERROR: ' . $e->getMessage();
        $createTablesMessageType = 'error';
    }
}

// Handle export domains
$exportMessage = '';
$exportResults = [];

if ($currentView === 'export') {
    // Check if user has configured their API settings
    if (!userHasSettingsDB()) {
        $exportMessage = 'Please configure your API settings first.';
    } else {
        // Load user settings
        $userSettings = getUserSettingsDB();
        if (!$userSettings) {
            $exportMessage = 'Unable to load your API settings. Please configure them first.';
        }
    }
    
    // Handle CSV export
    if (isset($_POST['export_csv'])) {
        // Get batch parameters
        $batchSize = 50; // Reduced from 200 to 50 to prevent timeouts
        $batchNumber = isset($_POST['batch_number']) ? (int)$_POST['batch_number'] : 1;
        $offset = ($batchNumber - 1) * $batchSize;
        
        // Get domains with offset (limit to 200 per batch)
        $domainsResponse = getDomainsForExport($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $batchSize, $offset);
        
        if (!isset($domainsResponse['domains']['domain']) || $domainsResponse['result'] !== 'success') {
            $exportMessage = 'Error: Could not fetch domains from WHMCS API';
            if (isset($domainsResponse['message'])) {
                $exportMessage .= ' - ' . $domainsResponse['message'];
            }
        } else {
            $domains = $domainsResponse['domains']['domain'];
            $totalInBatch = count($domains);
            
            // Filter for active domains
            $activeDomains = [];
            foreach ($domains as $domain) {
                $status = $domain['status'] ?? 'Unknown';
                if (strtolower($status) === 'active') {
                    $activeDomains[] = $domain;
                }
            }
            
            if (count($activeDomains) === 0) {
                $exportMessage = 'Warning: No active domains found in this batch to export.';
            } else {
                // Create CSV file with batch number
                $filename = 'exports/domains_active_batch' . $batchNumber . '_' . date('Y-m-d_H-i-s') . '.csv';
                $file = fopen($filename, 'w');
                fputcsv($file, ['Domain Name', 'Domain ID', 'Status', 'NS1', 'NS2', 'NS3', 'NS4', 'NS5', 'Notes', 'Batch Number']);
                
                $processed = 0;
                $successful = 0;
                $errors = 0;
                
                foreach ($activeDomains as $domain) {
                    // Check if we're approaching timeout and extend if needed
                    if (function_exists('set_time_limit')) {
                        set_time_limit(1200); // Reset timeout to 20 minutes
                    }
                    
                    $domainName = $domain['domainname'] ?? 'Unknown';
                    $domainId = $domain['id'] ?? null;
                    $domainStatus = $domain['status'] ?? 'Unknown';
                    
                    if (!$domainId) {
                        fputcsv($file, [$domainName, 'N/A', $domainStatus, 'ERROR', 'No domain ID found', '', '', '', 'Could not fetch nameservers', $batchNumber]);
                        $errors++;
                    } else {
                        $nsResponse = getDomainNameservers($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $domainId);
                        
                        if (isset($nsResponse['result']) && $nsResponse['result'] === 'success') {
                            fputcsv($file, [
                                $domainName, $domainId, $domainStatus,
                                $nsResponse['ns1'] ?? '', $nsResponse['ns2'] ?? '', $nsResponse['ns3'] ?? '',
                                $nsResponse['ns4'] ?? '', $nsResponse['ns5'] ?? '', 'Success', $batchNumber
                            ]);
                            $successful++;
                        } else {
                            $errorMsg = $nsResponse['message'] ?? 'Unknown error';
                            // Check for timeout errors specifically
                            if (isset($nsResponse['http_code']) && $nsResponse['http_code'] == 0) {
                                $errorMsg = 'API Timeout - Server took too long to respond';
                            }
                            fputcsv($file, [$domainName, $domainId, $domainStatus, 'ERROR', $errorMsg, '', '', '', 'Failed to get nameservers', $batchNumber]);
                            $errors++;
                        }
                    }
                    
                    $processed++;
                    usleep(100000); // Reduced to 0.1 second delay for faster processing
                }
                
                fclose($file);
                
                $exportMessage = "Batch $batchNumber export completed: $successful successful, $errors errors out of $processed total domains. File: $filename";
                $exportResults = [
                    'filename' => $filename,
                    'processed' => $processed,
                    'successful' => $successful,
                    'errors' => $errors,
                    'batch_number' => $batchNumber
                ];
            }
        }
    }
 }

// Get real domain statistics for dashboard
$dashboardStats = [
    'total_projects' => 0,
    'ended_projects' => 0,
    'running_projects' => 0,
    'pending_projects' => 0
];

// Get recent domains for dashboard
$recentProjects = [];

// Cache domains data to avoid multiple API calls
$allDomains = [];
$userSettings = null;

if (userHasSettingsDB()) {
    $userSettings = getUserSettingsDB();
    if ($userSettings) {
        // Single API call to get all domains
        $response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
        if (isset($response['domains']['domain']) && is_array($response['domains']['domain'])) {
            $allDomains = $response['domains']['domain'];
            
            // Calculate dashboard stats
            $dashboardStats['total_projects'] = count($allDomains);
            
            // Count domains by status
            foreach ($allDomains as $domain) {
                $status = strtolower($domain['status'] ?? 'unknown');
                switch ($status) {
                    case 'active':
                        $dashboardStats['running_projects']++;
                        break;
                    case 'expired':
                    case 'terminated':
                    case 'cancelled':
                        $dashboardStats['ended_projects']++;
                        break;
                    case 'pending':
                    case 'pendingtransfer':
                    case 'pendingregistration':
                        $dashboardStats['pending_projects']++;
                        break;
                    default:
                        // Count other statuses as pending
                        $dashboardStats['pending_projects']++;
                        break;
                }
            }
            
            // Process recent domains from the same data
            if (!empty($allDomains)) {
                // Sort domains by registration date (most recent first)
                usort($allDomains, function($a, $b) {
                    $dateA = strtotime($a['regdate'] ?? '1970-01-01');
                    $dateB = strtotime($b['regdate'] ?? '1970-01-01');
                    return $dateB - $dateA;
                });
                
                // Take the 5 most recent domains
                $recentDomains = array_slice($allDomains, 0, 5);
                
                foreach ($recentDomains as $domain) {
                    $status = strtolower($domain['status'] ?? 'unknown');
                    $icon = 'globe'; // default icon
                    
                    // Set icon based on status
                    switch ($status) {
                        case 'active':
                            $icon = 'check-circle';
                            break;
                        case 'expired':
                            $icon = 'alert-triangle';
                            break;
                        case 'pending':
                            $icon = 'clock';
                            break;
                        default:
                            $icon = 'globe';
                            break;
                    }
                    
                    $recentProjects[] = [
                        'name' => $domain['domainname'] ?? 'Unknown Domain',
                        'due_date' => 'Reg: ' . date('M j, Y', strtotime($domain['regdate'] ?? 'now')),
                        'icon' => $icon,
                        'status' => $status
                    ];
                }
            }
        }
    }
}

// Fallback to mock data if no domains found
if (empty($recentProjects)) {
    $recentProjects = [
        ['name' => 'No domains found', 'due_date' => 'Configure API settings', 'icon' => 'alert-circle', 'status' => 'none']
    ];
}

// Check setup completion status
function checkDatabaseSetupComplete() {
    $host = getEnvVar('DB_HOST', '');
    $port = getEnvVar('DB_PORT', '');
    $database = getEnvVar('DB_NAME', '');
    $user = getEnvVar('DB_USER', '');
    
    return !empty($host) && !empty($port) && !empty($database) && !empty($user);
}

function checkTablesCreated() {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Check if the domains table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'domains'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function checkApiSettingsComplete() {
    $userSettings = getUserSettingsDB();
    if (!$userSettings) {
        return false;
    }
    
    $requiredFields = ['api_url', 'api_identifier', 'api_secret', 'default_ns1', 'default_ns2'];
    foreach ($requiredFields as $field) {
        if (empty($userSettings[$field])) {
            return false;
        }
    }
    
    return true;
}

// Get setup completion status
$setupStatus = [
    'database_setup' => checkDatabaseSetupComplete(),
    'tables_created' => checkTablesCreated(),
    'api_configured' => checkApiSettingsComplete()
];

// Calculate setup progress
$completedSteps = array_sum($setupStatus);
$totalSteps = count($setupStatus);
$progressPercentage = ($totalSteps > 0) ? ($completedSteps / $totalSteps) * 100 : 0;

// Get system status information
$systemStatus = [];



// Get server health data
$serverHealth = [];

if (userHasSettingsDB()) {
    $userSettings = getUserSettingsDB();
    if ($userSettings) {
        // Test API connection
        $testResponse = testApiConnection($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
        
        $systemStatus[] = [
            'name' => 'API Connection',
            'task' => $testResponse['result'] === 'success' ? 'Connected to WHMCS API' : 'Connection failed',
            'status' => $testResponse['result'] === 'success' ? 'completed' : 'failed'
        ];
        
        // Check if nameservers are configured
        if (!empty($userSettings['default_ns1']) && !empty($userSettings['default_ns2'])) {
            $systemStatus[] = [
                'name' => 'Nameservers',
                'task' => 'Primary: ' . $userSettings['default_ns1'] . ', Secondary: ' . $userSettings['default_ns2'],
                'status' => 'completed'
            ];
        } else {
            $systemStatus[] = [
                'name' => 'Nameservers',
                'task' => 'Not configured - please set in Settings',
                'status' => 'pending'
            ];
        }
        
        // Get server health status
        $healthResponse = getHealthStatus($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
        if (isset($healthResponse['result']) && $healthResponse['result'] === 'success') {
            $serverHealth[] = [
                'name' => 'WHMCS System',
                'status' => 'operational',
                'message' => 'All systems operational'
            ];
        } else {
            $serverHealth[] = [
                'name' => 'WHMCS System',
                'status' => 'error',
                'message' => 'Health check failed'
            ];
        }

        // Get servers data
        $serversData = [];
        $serversResponse = getServers($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
        if (isset($serversResponse['result']) && $serversResponse['result'] === 'success' && isset($serversResponse['servers'])) {
            $serversData = $serversResponse['servers'];
        }
        
        // Check domain count
        $response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
        if (isset($response['domains']['domain'])) {
            $domainCount = count($response['domains']['domain']);
            $systemStatus[] = [
                'name' => 'Domain Count',
                'task' => $domainCount . ' domains found in WHMCS',
                'status' => 'completed'
            ];
            
            
        } else {
            $systemStatus[] = [
                'name' => 'Domain Count',
                'task' => 'Unable to fetch domains',
                'status' => 'failed'
            ];
        }
    }
} else {
    $systemStatus[] = [
        'name' => 'Configuration',
        'task' => 'Please configure API settings first',
        'status' => 'pending'
    ];
}

// Get income statistics from WHMCS GetStats API
$incomeStats = [
    'income_today' => '$0.00',
    'income_thismonth' => '$0.00', 
    'income_thisyear' => '$0.00',
    'income_alltime' => '$0.00'
];

// Get orders statistics from WHMCS GetStats API
$ordersStats = [
    'orders_today_total' => '0',
    'orders_yesterday_total' => '0',
    'orders_thismonth_total' => '0',
    'orders_thisyear_total' => '0'
];

// Get domain status distribution for analytics from database
$domainStatusStats = [
    'Active' => 0,
    'Pending' => 0,
    'Suspended' => 0,
    'Cancelled' => 0,
    'Fraud' => 0,
    'Other' => 0
];

// Get registrars data from database
$registrarsData = [];

try {
    $db = Database::getInstance();
    $userEmail = $_SESSION['user_email'] ?? '';
    
    // Get domain status distribution from database for current user
    $statusQuery = "SELECT status, COUNT(*) as count FROM domains WHERE user_email = ? GROUP BY status";
    $statusStmt = $db->getConnection()->prepare($statusQuery);
    $statusStmt->execute([$userEmail]);
    
    while ($row = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
        $status = ucfirst(strtolower($row['status'])); // Normalize status
        $count = (int)$row['count'];
        
        if (isset($domainStatusStats[$status])) {
            $domainStatusStats[$status] = $count;
        } else {
            $domainStatusStats['Other'] += $count;
        }
    }
    
    // Get registrars data from database for current user
    $registrarQuery = "SELECT registrar, COUNT(*) as count FROM domains WHERE user_email = ? GROUP BY registrar ORDER BY count DESC LIMIT 5";
    $registrarStmt = $db->getConnection()->prepare($registrarQuery);
    $registrarStmt->execute([$userEmail]);
    
    $totalDomains = array_sum($domainStatusStats);
    
    while ($row = $registrarStmt->fetch(PDO::FETCH_ASSOC)) {
        $registrar = $row['registrar'] ?: 'Unknown';
        $count = (int)$row['count'];
        $percentage = $totalDomains > 0 ? round(($count / $totalDomains) * 100, 1) : 0;
        
        $registrarsData[] = [
            'name' => $registrar,
            'count' => $count,
            'percentage' => $percentage
        ];
    }
    
} catch (Exception $e) {
    // If database is not available, keep empty stats
    error_log("Database error in domain analytics: " . $e->getMessage());
}

// Handle database view
$databaseViewDomains = [];
$databaseViewTotalDomains = 0;
$databaseViewDomainStats = [];
$databaseViewError = null;
$databaseViewLastSync = null;

if ($currentView === 'database_view') {
    try {
        $db = Database::getInstance();
        
        // Get search and filter parameters
        $dbSearch = $_GET['search'] ?? '';
        $dbStatus = $_GET['status'] ?? '';
        $dbRegistrar = $_GET['registrar'] ?? '';
        $dbPage = max(1, intval($_GET['page'] ?? 1));
        $dbPerPage = 25;
        $dbOrderBy = $_GET['order_by'] ?? 'domain_name';
        $dbOrderDir = $_GET['order_dir'] ?? 'ASC';

        // Validate order by field
        $allowedOrderBy = ['domain_name', 'status', 'registrar', 'expiry_date', 'last_synced'];
        if (!in_array($dbOrderBy, $allowedOrderBy)) {
            $dbOrderBy = 'domain_name';
        }

        // Validate order direction
        $dbOrderDir = strtoupper($dbOrderDir) === 'DESC' ? 'DESC' : 'ASC';

        // Get domains from database
        $userEmail = $_SESSION['user_email'] ?? '';
        $companyId = $_SESSION['company_id'] ?? null;
        if (!empty($userEmail) && !empty($companyId)) {
            $databaseViewDomains = $db->getDomains($companyId, $userEmail, $dbPage, $dbPerPage, $dbSearch, $dbStatus, $dbOrderBy, $dbOrderDir, $dbRegistrar);
            $databaseViewTotalDomains = $db->getDomainCount($companyId, $userEmail, $dbSearch, $dbStatus, $dbRegistrar);
            $databaseViewDomainStats = $db->getDomainStats($companyId, $userEmail);
            
            // Get last sync information
            $databaseViewLastSync = $db->getLastSyncInfo($userEmail);
            
            // Get unique registrars for the edit form
            $databaseViewUniqueRegistrars = $db->getUniqueRegistrars($companyId, $userEmail);
        } else {
            $databaseViewDomains = [];
            $databaseViewTotalDomains = 0;
            $databaseViewDomainStats = [];
            $databaseViewLastSync = null;
            $databaseViewUniqueRegistrars = [];
        }
        
    } catch (Exception $e) {
        $databaseViewError = "Failed to fetch domains: " . $e->getMessage();
    }
}

// Handle sync view
$syncViewError = null;
$syncViewLastSync = null;
$syncViewDomainStats = [];

if ($currentView === 'sync') {
    // Check if user has configured their API settings
    if (!userHasSettingsDB()) {
        $syncViewError = "Please configure API settings first";
    } else {
        try {
            $db = Database::getInstance();
            $db->createTables(); // Ensure tables exist
            
            // Get last sync information
            $syncViewLastSync = $db->getLastSyncInfo($_SESSION['user_email'] ?? '');
            
            // Get domain statistics
            $userEmail = $_SESSION['user_email'] ?? '';
            $companyId = $_SESSION['company_id'] ?? null;
            if (!empty($userEmail) && !empty($companyId)) {
                $syncViewDomainStats = $db->getDomainStats($companyId, $userEmail);
            } else {
                $syncViewDomainStats = [];
            }
            
        } catch (Exception $e) {
            $syncViewError = "Database connection failed: " . $e->getMessage();
        }
    }
}

// Get income and orders stats from API (keeping this for now)
if (userHasSettingsDB()) {
    $userSettings = getUserSettingsDB();
    if ($userSettings) {
        // Use caching for stats data (refresh every 5 minutes)
        $userEmail = $_SESSION['user_email'] ?? 'unknown';
        $cacheKey = 'income_stats_' . md5($userSettings['api_url'] . $userSettings['api_identifier']);
        
        $statsResponse = getCachedApiResponse($cacheKey, $userEmail, function() use ($userSettings) {
            return curlCall($userSettings['api_url'], [
                'action' => 'GetStats',
                'identifier' => $userSettings['api_identifier'],
                'secret' => $userSettings['api_secret'],
                'responsetype' => 'json'
            ]);
        }, 300); // Cache for 5 minutes
        
        if (isset($statsResponse['result']) && $statsResponse['result'] === 'success') {
            $incomeStats = [
                'income_today' => $statsResponse['income_today'] ?? '$0.00',
                'income_thismonth' => $statsResponse['income_thismonth'] ?? '$0.00',
                'income_thisyear' => $statsResponse['income_thisyear'] ?? '$0.00',
                'income_alltime' => $statsResponse['income_alltime'] ?? '$0.00'
            ];
            
            $ordersStats = [
                'orders_today_total' => $statsResponse['orders_today_total'] ?? '0',
                'orders_yesterday_total' => $statsResponse['orders_yesterday_total'] ?? '0',
                'orders_thismonth_total' => $statsResponse['orders_thismonth_total'] ?? '0',
                'orders_thisyear_total' => $statsResponse['orders_thisyear_total'] ?? '0'
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Dashboard</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
               <!-- Fonts -->
           <link rel="preconnect" href="https://fonts.googleapis.com">
           <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
           <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
           
           <!-- Chart.js -->
           <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Critical CSS inline -->
    <style>
    .loading-spinner{display:inline-block;width:20px;height:20px;border:3px solid rgba(255,255,255,.3);border-radius:50%;border-top-color:#fff;animation:spin 1s ease-in-out infinite}
    @keyframes spin{to{transform:rotate(360deg)}}

    .animate-spin{animation:spin 1s linear infinite}
    
    /* Mobile menu fixes */
    @media (max-width: 1023px) {
        .w-80 {
            position: fixed !important;
            left: 0 !important;
            top: 0 !important;
            height: 100vh !important;
            z-index: 50 !important;
            transform: translateX(-100%);
        }
        .w-80:not(.-translate-x-full) {
            transform: translateX(0) !important;
        }
    }
    </style>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Sora', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen">
        <!-- Mobile overlay -->
        <div class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-30 hidden" id="sidebar-overlay"></div>
        
        <!-- Sidebar -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col lg:translate-x-0 -translate-x-full transition-transform duration-300 fixed lg:relative z-40 h-full overflow-y-auto">
            <!-- Logo -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                        <i data-lucide="globe" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">Domain Tools</h1>
                        <p class="text-xs text-gray-500">Management Suite</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="flex-1 p-4 flex flex-col">
                <div class="flex-1">
                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">MENU</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="?view=dashboard" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'dashboard' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="layout-dashboard" class="w-4 h-4 <?= $currentView === 'dashboard' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'dashboard' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Dashboard</span>
                                </a>
                            </li>
                            <li>
                                <a href="?view=billing" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'billing' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="credit-card" class="w-4 h-4 <?= $currentView === 'billing' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'billing' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Billing</span>
                                </a>
                            </li>
                            <li>
                                <a href="?view=orders" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'orders' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="shopping-cart" class="w-4 h-4 <?= $currentView === 'orders' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'orders' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Orders</span>
                                </a>
                            </li>

                        </ul>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">DOMAIN ACTIONS</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="?view=sync" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'sync' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="refresh-cw" class="w-4 h-4 <?= $currentView === 'sync' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'sync' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Domain Sync</span>
                                </a>
                            </li>
                            <li>
                                <a href="?view=nameservers" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'nameservers' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="server" class="w-4 h-4 <?= $currentView === 'nameservers' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'nameservers' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Update Nameservers</span>
                                </a>
                            </li>
                            <li>
                                <a href="?view=export" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'export' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="download" class="w-4 h-4 <?= $currentView === 'export' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'export' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Export Domains</span>
                                </a>
                            </li>
                            <li>
                                <a href="?view=database_view" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'database_view' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="database" class="w-4 h-4 <?= $currentView === 'database_view' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'database_view' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Domains Table</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <?php if (isServerAdmin()): ?>
                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">SERVER SETUP</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="?view=database_setup" class="flex items-center justify-between px-3 py-2 <?= $currentView === 'database_setup' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <div class="flex items-center space-x-3">
                                        <i data-lucide="database" class="w-4 h-4 <?= $currentView === 'database_setup' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                        <span class="text-sm <?= $currentView === 'database_setup' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Database Setup</span>
                                    </div>
                                    <?php if ($setupStatus['database_setup']): ?>
                                        <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li>
                                <a href="?view=create_tables" class="flex items-center justify-between px-3 py-2 <?= $currentView === 'create_tables' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <div class="flex items-center space-x-3">
                                        <i data-lucide="table" class="w-4 h-4 <?= $currentView === 'create_tables' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                        <span class="text-sm <?= $currentView === 'create_tables' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Create Tables</span>
                                    </div>
                                    <?php if ($setupStatus['tables_created']): ?>
                                        <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                                    <?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-auto">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">GENERAL</h3>
                    <ul class="space-y-1">
                        <li>
                            <a href="?view=settings" class="flex items-center justify-between px-3 py-2 <?= $currentView === 'settings' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                <div class="flex items-center space-x-3">
                                    <i data-lucide="settings" class="w-4 h-4 <?= $currentView === 'settings' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'settings' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Settings</span>
                                </div>
                                <?php if ($setupStatus['api_configured']): ?>
                                    <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php if (isServerAdmin()): ?>
                        <li>
                            <a href="?view=debug" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'debug' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                <i data-lucide="bug" class="w-4 h-4 <?= $currentView === 'debug' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                <span class="text-sm <?= $currentView === 'debug' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Debug Tools</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="?view=help" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'help' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                <i data-lucide="help-circle" class="w-4 h-4 <?= $currentView === 'help' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                <span class="text-sm <?= $currentView === 'help' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Help</span>
                            </a>
                        </li>
                        <li>
                            <form method="POST" class="w-full">
                                <button type="submit" name="logout" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors w-full text-left">
                                    <i data-lucide="log-out" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Logout</span>
                                </button>
                    </form>
                        </li>
                    </ul>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Mobile Menu Button -->
                    <button class="lg:hidden p-2 text-gray-400 hover:text-gray-600 transition-colors" id="mobile-menu-button">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    
                    <!-- Logo -->
                    <div class="flex items-center">
                        <img src="<?= htmlspecialchars(getLogoUrlDB()) ?>" 
                             alt="Logo" 
                             class="h-8 max-w-full object-contain"
                             onerror="this.style.display='none';">
                    </div>
                    
                    <!-- User Menu -->
                    <div class="flex items-center space-x-4">
                        <button class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                            <i data-lucide="mail" class="w-5 h-5"></i>
                        </button>
                        <button class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                        </button>
                        <div class="flex items-center space-x-3">
                            <?php
                            $userEmail = getCurrentUserEmail() ?? '';
                            $gravatarHash = md5(strtolower(trim($userEmail)));
                            $gravatarUrl = "https://www.gravatar.com/avatar/{$gravatarHash}?s=32&d=mp&r=g";
                            ?>
                            <img src="<?= htmlspecialchars($gravatarUrl) ?>" 
                                 alt="User Avatar" 
                                 class="w-8 h-8 rounded-full object-cover border-2 border-gray-200"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center hidden">
                                <span class="text-white text-sm font-medium"><?= strtoupper(substr($userEmail, 0, 1)) ?></span>
                    </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($userEmail ?: 'User') ?></p>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars(getCurrentUserRoleDisplay()) ?></p>
                </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <?php if ($currentView === 'dashboard'): ?>
                <!-- Dashboard Content -->
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Dashboard</h1>
                    <p class="text-gray-600">Plan, prioritize, and manage your domains with ease.</p>
                </div>

                <!-- Domain Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-primary-600 text-white p-6 rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-primary-100">Total Domains</h3>
                            <div class="w-8 h-8 bg-primary-500 rounded-full flex items-center justify-center">
                                <i data-lucide="trending-up" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <div class="text-4xl font-bold"><?= $dashboardStats['total_projects'] ?></div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-medium text-gray-500">Active Domains</h3>
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <i data-lucide="trending-up" class="w-4 h-4 text-green-600"></i>
                            </div>
                        </div>
                        <div class="text-4xl font-bold text-gray-900"><?= $dashboardStats['running_projects'] ?></div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm font-medium text-gray-500">Expired Domains</h3>
                                <div class="relative group">
                                    <i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10 w-48">
                                        Counts domains with 'expired', 'terminated', or 'cancelled' status.
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                <i data-lucide="trending-up" class="w-4 h-4 text-red-600"></i>
                            </div>
                        </div>
                        <div class="text-4xl font-bold text-gray-900"><?= $dashboardStats['ended_projects'] ?></div>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm font-medium text-gray-500">Pending Domains</h3>
                                <div class="relative group">
                                    <i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i>
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10 w-48">
                                        Counts domains with 'pending', 'pendingtransfer', 'pendingregistration' or other statuses
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i data-lucide="clock" class="w-4 h-4 text-yellow-600"></i>
                            </div>
                        </div>
                        <div class="text-4xl font-bold text-gray-900"><?= $dashboardStats['pending_projects'] ?></div>
                    </div>
                </div>

                <!-- Info Message -->
                <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
                        <div>
                            <div class="font-semibold">WHMCS API Data</div>
                            <div class="text-sm mt-1">The domain counts and data shown on this page are from the WHMCS API. This reflects the most current data from your WHMCS system.</div>
                        </div>
                    </div>
                </div>



                <!-- Settings Warning -->
                    <?php if (!$hasSettings || !empty($settingsValidation['missing'])): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start space-x-3">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600 mt-0.5"></i>
                                <div class="flex-1">
                                <h3 class="font-semibold text-yellow-800 mb-2">WHMCS API Configuration Required</h3>
                                <p class="text-yellow-700 text-sm mb-3">
                                        <?php if (!$hasSettings): ?>
                                            Please configure your WHMCS API credentials before using the domain management tools.
                                        <?php else: ?>
                                            Your WHMCS API configuration is incomplete. The following information is missing:
                                        <?php endif; ?>
                                </p>
                                    
                                    <?php if (!empty($settingsValidation['missing'])): ?>
                                    <div class="bg-yellow-100 border border-yellow-300 rounded-md p-3 mb-3">
                                            <div class="text-sm font-medium text-yellow-800 mb-2">Missing Configuration:</div>
                                            <ul class="text-sm text-yellow-700 space-y-1">
                                                <?php foreach ($settingsValidation['missing'] as $missing): ?>
                                                <li class="flex items-center space-x-2">
                                                    <span class="text-red-500">•</span>
                                                        <?= htmlspecialchars($missing) ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    
                                <a href="?view=settings" class="inline-flex items-center space-x-2 bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                        <span>Configure API Settings</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>





                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 gap-6">
                    <!-- Domain Analytics and Registrars Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Project Analytics -->
                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Domain Analytics</h3>
                                    <p class="text-sm text-gray-500 mt-1"><?= $totalDomains ?> total domains</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <?php
                                $totalDomains = array_sum($domainStatusStats);
                                $hasData = $totalDomains > 0;
                                ?>
                                
                                <?php if ($hasData): ?>
                                    <?php foreach ($domainStatusStats as $status => $count): ?>
                                        <?php if ($count > 0): ?>
                                            <?php
                                            $percentage = round(($count / $totalDomains) * 100, 1);
                                            $colors = [
                                                'Active' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'icon' => 'check-circle'],
                                                'Pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'icon' => 'clock'],
                                                'Suspended' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'pause-circle'],
                                                'Cancelled' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'icon' => 'x-circle'],
                                                'Fraud' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'icon' => 'alert-triangle'],
                                                'Other' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'icon' => 'help-circle']
                                            ];
                                            $statusColors = $colors[$status] ?? $colors['Other'];
                                            ?>
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 <?= $statusColors['bg'] ?> rounded-full flex items-center justify-center">
                                                        <i data-lucide="<?= $statusColors['icon'] ?>" class="w-4 h-4 <?= $statusColors['text'] ?>"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($status) ?></p>
                                                        <p class="text-xs text-gray-500"><?= $count ?> domains</p>
                                                    </div>
                                                </div>
                                                                                    <div class="text-right">
                                        <div class="text-sm font-semibold text-gray-900"><?= $percentage ?>%</div>
                                        <div class="w-16 bg-gray-200 rounded-full h-1.5 mt-1">
                                            <div class="bg-primary-600 h-1.5 rounded-full" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                    </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- No Data State -->
                                    <div class="flex items-center justify-center py-8">
                                        <div class="text-center">
                                            <i data-lucide="bar-chart-3" class="w-12 h-12 text-gray-400 mx-auto mb-2"></i>
                                            <p class="text-gray-500 text-sm">No domain data available</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Registrars -->
                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900">Registrars</h3>
                                <p class="text-sm text-gray-500 mt-1">Top registrars by domain count</p>
                            </div>
                            <?php if (!empty($registrarsData)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($registrarsData as $registrar): ?>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                                                    <i data-lucide="building" class="w-4 h-4 text-primary-600"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($registrar['name']) ?></p>
                                                    <p class="text-xs text-gray-500"><?= $registrar['count'] ?> domains</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-semibold text-gray-900"><?= $registrar['percentage'] ?>%</div>
                                                <div class="w-16 bg-gray-200 rounded-full h-2 mt-1">
                                                    <div class="bg-primary-600 h-2 rounded-full" style="width: <?= $registrar['percentage'] ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="h-64 bg-gray-50 rounded-lg flex items-center justify-center">
                                    <div class="text-center">
                                        <i data-lucide="building" class="w-12 h-12 text-gray-400 mx-auto mb-2"></i>
                                        <p class="text-gray-500">No registrar data available</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Domains and System Status/Health Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Recent Projects -->
                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-900">Recent Domains</h3>
                                <p class="text-sm text-gray-500 mt-1">5 most recently registered domains</p>
                            </div>
                            <div class="space-y-3">
                                <?php foreach ($recentProjects as $project): ?>
                                    <div class="flex items-center space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                        <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center">
                                            <i data-lucide="<?= $project['icon'] ?>" class="w-4 h-4 text-primary-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900"><?= $project['name'] ?></p>
                                            <p class="text-xs text-gray-500">Due: <?= $project['due_date'] ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- System Status and Server Health Column -->
                        <div class="space-y-4">
                            <!-- System Status -->
                            <div class="bg-white p-4 rounded-xl border border-gray-200">
                                <div class="mb-3">
                                    <h3 class="text-lg font-semibold text-gray-900">System Status</h3>
                                    <p class="text-sm text-gray-500 mt-1">Shows connection status to WHMCS API</p>
                                </div>
                                <div class="space-y-2">
                                    <?php foreach (array_slice($systemStatus, 0, 2) as $status): ?>
                                        <div class="flex items-center space-x-3">
                                            <div class="w-5 h-5 <?= $status['status'] === 'completed' ? 'bg-green-100' : ($status['status'] === 'pending' ? 'bg-yellow-100' : 'bg-red-100') ?> rounded-full flex items-center justify-center">
                                                <i data-lucide="<?= $status['status'] === 'completed' ? 'check-circle' : ($status['status'] === 'pending' ? 'clock' : 'x-circle') ?>" class="w-3 h-3 <?= $status['status'] === 'completed' ? 'text-green-600' : ($status['status'] === 'pending' ? 'text-yellow-600' : 'text-red-600') ?>"></i>
                                            </div>
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-900"><?= $status['name'] ?></p>
                                                <p class="text-xs text-gray-500 truncate"><?= $status['task'] ?></p>
                                            </div>
                                            <span class="px-2 py-1 text-xs rounded-full <?= $status['status'] === 'completed' ? 'bg-green-100 text-green-800' : ($status['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                                <?= ucfirst($status['status']) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Server Health -->
                            <div class="bg-white p-4 rounded-xl border border-gray-200">
                                <div class="mb-3">
                                    <h3 class="text-lg font-semibold text-gray-900">Server Health</h3>
                                    <p class="text-sm text-gray-500 mt-1">Real-time system health monitoring</p>
                                </div>
                                <?php if (!empty($serverHealth)): ?>
                                    <div class="space-y-2">
                                        <?php foreach ($serverHealth as $health): ?>
                                            <div class="flex items-center space-x-3 p-2 bg-gray-50 rounded-lg">
                                                <div class="w-6 h-6 <?= $health['status'] === 'operational' ? 'bg-green-100' : 'bg-red-100' ?> rounded-full flex items-center justify-center">
                                                    <i data-lucide="<?= $health['status'] === 'operational' ? 'check-circle' : 'x-circle' ?>" class="w-3 h-3 <?= $health['status'] === 'operational' ? 'text-green-600' : 'text-red-600' ?>"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <p class="text-sm font-medium text-gray-900"><?= $health['name'] ?></p>
                                                    <p class="text-xs text-gray-500"><?= $health['message'] ?></p>
                                                </div>
                                                <span class="px-2 py-1 text-xs rounded-full <?= $health['status'] === 'operational' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= ucfirst($health['status']) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i data-lucide="activity" class="w-8 h-8 text-gray-400 mx-auto mb-2"></i>
                                        <p class="text-xs text-gray-500">No health data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Servers -->
                            <div class="bg-white p-4 rounded-xl border border-gray-200">
                                <div class="mb-3">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900">Servers</h3>
                                        <?php if (!empty($serversData)): ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                                <?= count($serversData) ?> server<?= count($serversData) !== 1 ? 's' : '' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-500 mt-1">Server infrastructure overview</p>
                                </div>
                                <?php if (!empty($serversData)): ?>
                                    <div class="space-y-2">
                                        <?php foreach ($serversData as $server): ?>
                                            <div class="flex items-center space-x-3 p-2 bg-gray-50 rounded-lg">
                                                <div class="w-6 h-6 <?= $server['active'] ? 'bg-green-100' : 'bg-red-100' ?> rounded-full flex items-center justify-center">
                                                    <i data-lucide="<?= $server['active'] ? 'server' : 'server-off' ?>" class="w-3 h-3 <?= $server['active'] ? 'text-green-600' : 'text-red-600' ?>"></i>
                                                </div>
                                                <div class="flex-1">
                                                    <div class="flex items-center space-x-2">
                                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($server['name']) ?></p>
                                                        <?php if (!empty($server['module'])): ?>
                                                            <span class="text-xs text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full">
                                                                <?= htmlspecialchars(ucfirst($server['module'])) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($server['hostname']) ?> • <?= htmlspecialchars($server['ipaddress']) ?></p>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-xs font-medium text-gray-900"><?= $server['activeServices'] ?>/<?= $server['maxAllowedServices'] ?></div>
                                                    <div class="w-12 bg-gray-200 rounded-full h-1 mt-1">
                                                        <div class="bg-primary-600 h-1 rounded-full" style="width: <?= $server['percentUsed'] ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i data-lucide="server" class="w-8 h-8 text-gray-400 mx-auto mb-2"></i>
                                        <p class="text-xs text-gray-500">No server data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    


                 </div>
                 
                 <?php elseif ($currentView === 'billing'): ?>
                 <!-- Billing Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">Billing & Revenue</h1>
                     <p class="text-gray-600">Track your income and financial performance.</p>
                 </div>

                 <!-- Income Statistics -->
                 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                     <!-- Today's Income -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">Today's Income</h3>
                             <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="dollar-sign" class="w-4 h-4 text-green-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($incomeStats['income_today']) ?></div>
                         <p class="text-green-600 text-sm">Revenue generated today</p>
                     </div>

                     <!-- This Month's Income -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">This Month</h3>
                             <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($incomeStats['income_thismonth']) ?></div>
                         <p class="text-blue-600 text-sm">Monthly revenue</p>
                     </div>

                     <!-- This Year's Income -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">This Year</h3>
                             <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="trending-up" class="w-4 h-4 text-purple-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($incomeStats['income_thisyear']) ?></div>
                         <p class="text-purple-600 text-sm">Annual revenue</p>
                     </div>

                     <!-- All Time Income -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">All Time</h3>
                             <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="trophy" class="w-4 h-4 text-amber-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($incomeStats['income_alltime']) ?></div>
                         <p class="text-amber-600 text-sm">Total lifetime revenue</p>
                     </div>
                 </div>

                 <!-- Additional Billing Content Placeholder -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4">Billing Overview</h3>
                     <p class="text-gray-600">Additional billing features and analytics will be available here.</p>
                 </div>

                 <?php elseif ($currentView === 'orders'): ?>
                 <!-- Orders Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">Orders & Sales</h1>
                     <p class="text-gray-600">Track your order volume and sales performance.</p>
                 </div>

                 <!-- Orders Statistics -->
                 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                     <!-- Today's Orders -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">Today's Orders</h3>
                             <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="shopping-bag" class="w-4 h-4 text-green-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($ordersStats['orders_today_total']) ?></div>
                         <p class="text-green-600 text-sm">Orders received today</p>
                     </div>

                     <!-- Yesterday's Orders -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">Yesterday</h3>
                             <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="calendar-days" class="w-4 h-4 text-blue-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($ordersStats['orders_yesterday_total']) ?></div>
                         <p class="text-blue-600 text-sm">Orders from yesterday</p>
                     </div>

                     <!-- This Month's Orders -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">This Month</h3>
                             <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="trending-up" class="w-4 h-4 text-purple-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($ordersStats['orders_thismonth_total']) ?></div>
                         <p class="text-purple-600 text-sm">Monthly order volume</p>
                     </div>

                     <!-- This Year's Orders -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">This Year</h3>
                             <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="bar-chart-3" class="w-4 h-4 text-amber-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($ordersStats['orders_thisyear_total']) ?></div>
                         <p class="text-amber-600 text-sm">Annual order volume</p>
                     </div>
                 </div>

                 <!-- Additional Orders Content Placeholder -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4">Orders Overview</h3>
                     <p class="text-gray-600">Additional order management features and analytics will be available here.</p>
                 </div>

                 <?php elseif ($currentView === 'settings'): ?>
                 <!-- Settings Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">Settings & Profile</h1>
                     <p class="text-gray-600">Configure your API credentials, user profile, and company settings.</p>
                 </div>

                 <!-- Setup Progress -->
                 <div id="setupProgressSection" class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <div class="flex items-center justify-between mb-4">
                         <h3 class="text-lg font-semibold text-gray-900 flex items-center space-x-2">
                             <i data-lucide="check-square" class="w-5 h-5 text-primary-600"></i>
                             <span>Setup Progress</span>
                         </h3>
                         <?php if ($completedSteps === $totalSteps && $totalSteps > 0): ?>
                             <button type="button" id="hideSetupProgress" class="text-gray-500 hover:text-gray-700 transition-colors flex items-center space-x-2">
                                 <i data-lucide="eye-off" class="w-4 h-4"></i>
                                 <span class="text-sm">Hide</span>
                             </button>
                         <?php endif; ?>
                     </div>
                     
                     <div class="space-y-3">
                         <div class="flex items-center justify-between">
                             <div class="flex items-center space-x-3">
                                 <i data-lucide="database" class="w-4 h-4 <?= $setupStatus['database_setup'] ? 'text-green-600' : 'text-gray-400' ?>"></i>
                                 <span class="text-sm font-medium text-gray-900">Database Setup</span>
                             </div>
                             <?php if ($setupStatus['database_setup']): ?>
                                 <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                             <?php else: ?>
                                 <i data-lucide="circle" class="w-4 h-4 text-gray-300"></i>
                             <?php endif; ?>
                         </div>
                         
                         <div class="flex items-center justify-between">
                             <div class="flex items-center space-x-3">
                                 <i data-lucide="table" class="w-4 h-4 <?= $setupStatus['tables_created'] ? 'text-green-600' : 'text-gray-400' ?>"></i>
                                 <span class="text-sm font-medium text-gray-900">Database Tables</span>
                             </div>
                             <?php if ($setupStatus['tables_created']): ?>
                                 <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                             <?php else: ?>
                                 <i data-lucide="circle" class="w-4 h-4 text-gray-300"></i>
                             <?php endif; ?>
                         </div>
                         
                         <div class="flex items-center justify-between">
                             <div class="flex items-center space-x-3">
                                 <i data-lucide="settings" class="w-4 h-4 <?= $setupStatus['api_configured'] ? 'text-green-600' : 'text-gray-400' ?>"></i>
                                 <span class="text-sm font-medium text-gray-900">API Configuration</span>
                             </div>
                             <?php if ($setupStatus['api_configured']): ?>
                                 <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                             <?php else: ?>
                                 <i data-lucide="circle" class="w-4 h-4 text-gray-300"></i>
                             <?php endif; ?>
                         </div>
                     </div>
                     
                     <div class="mt-4">
                         <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                             <span>Overall Progress</span>
                             <span><?= $completedSteps ?>/<?= $totalSteps ?> completed</span>
                         </div>
                         <div class="w-full bg-gray-200 rounded-full h-2">
                             <div class="bg-primary-600 h-2 rounded-full transition-all duration-300" style="width: <?= $progressPercentage ?>%"></div>
                         </div>
                     </div>
                 </div>

                 <!-- Show Setup Progress Button (hidden by default) -->
                 <div id="showSetupProgressButton" class="hidden mb-6">
                     <button type="button" id="showSetupProgress" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                         <i data-lucide="eye" class="w-4 h-4"></i>
                         <span>Show Setup Progress</span>
                     </button>
                 </div>

                 <!-- Messages -->
                 <?php if ($message): ?>
                     <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'x-circle' ?>" class="w-5 h-5"></i>
                             <span><?= htmlspecialchars($message) ?></span>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Security Notice -->
                 <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                     <div class="flex items-start space-x-3">
                         <i data-lucide="shield" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                         <div>
                             <h3 class="font-semibold text-blue-800 mb-1">Security Notice</h3>
                             <p class="text-sm text-blue-700">Your API credentials are encrypted using AES-256-CBC and stored securely. They are only accessible to your account and never shared with third parties.</p>
                         </div>
                     </div>
                 </div>

                 <!-- Settings Form -->
                 <form method="POST" class="space-y-6">
                     <?php if (isServerAdmin()): ?>
                     <!-- WHMCS API Configuration Section -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                             <i data-lucide="link" class="w-5 h-5 text-primary-600"></i>
                             <span>WHMCS API Configuration</span>
                            </h3>
                         
                         <div class="space-y-6">
                             <div>
                                 <label for="api_url" class="block text-sm font-medium text-gray-700 mb-2">WHMCS API URL *</label>
                                 <input 
                                     type="url" 
                                     id="api_url" 
                                     name="api_url" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     required 
                                     value="<?= htmlspecialchars($currentSettings['api_url'] ?? '') ?>"
                                     placeholder="https://yourdomain.com/includes/api.php"
                                 >
                                 <p class="text-xs text-gray-500 mt-1">The complete URL to your WHMCS API endpoint</p>
                                </div>

                             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                 <div>
                                     <label for="api_identifier" class="block text-sm font-medium text-gray-700 mb-2">API Identifier *</label>
                                     <input 
                                         type="text" 
                                         id="api_identifier" 
                                         name="api_identifier" 
                                         class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                         required
                                         value="<?= htmlspecialchars($currentSettings['api_identifier'] ?? '') ?>"
                                         placeholder="Your API Identifier"
                                     >
                                     <p class="text-xs text-gray-500 mt-1">From WHMCS Admin → System Settings → API Credentials</p>
                                </div>

                                 <div>
                                     <label for="api_secret" class="block text-sm font-medium text-gray-700 mb-2">API Secret *</label>
                                     <input 
                                         type="password" 
                                         id="api_secret" 
                                         name="api_secret" 
                                         class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                         required
                                         value="<?= htmlspecialchars($currentSettings['api_secret'] ?? '') ?>"
                                         placeholder="Your API Secret"
                                     >
                                     <p class="text-xs text-gray-500 mt-1">The secret key associated with your API identifier</p>
                                </div>
                            </div>
                        </div>
                     </div>

                     <!-- Default Nameservers Section -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                             <i data-lucide="globe" class="w-5 h-5 text-primary-600"></i>
                             <span>Default Nameservers</span>
                         </h3>
                         
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                 <label for="default_ns1" class="block text-sm font-medium text-gray-700 mb-2">Primary Nameserver *</label>
                                 <input 
                                     type="text" 
                                     id="default_ns1" 
                                     name="default_ns1" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     required
                                     value="<?= htmlspecialchars($currentSettings['default_ns1'] ?? '') ?>"
                                     placeholder="ns1.yourdomain.com"
                                 >
                                 <p class="text-xs text-gray-500 mt-1">The primary nameserver for domain updates</p>
                             </div>

                             <div>
                                 <label for="default_ns2" class="block text-sm font-medium text-gray-700 mb-2">Secondary Nameserver *</label>
                                 <input 
                                     type="text" 
                                     id="default_ns2" 
                                     name="default_ns2" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     required
                                     value="<?= htmlspecialchars($currentSettings['default_ns2'] ?? '') ?>"
                                     placeholder="ns2.yourdomain.com"
                                 >
                                 <p class="text-xs text-gray-500 mt-1">The secondary nameserver for domain updates</p>
                             </div>
                         </div>
                     </div>
                     <?php endif; ?>



                     <?php if (isServerAdmin()): ?>
                     <!-- Action Buttons -->
                     <div class="flex flex-col sm:flex-row gap-3">
                         <button type="submit" name="save_settings" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                             <i data-lucide="save" class="w-4 h-4"></i>
                             <span>Save Settings</span>
                         </button>
                         <?php if ($currentSettings): ?>
                             <button type="submit" name="test_settings" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                                 <i data-lucide="test-tube" class="w-4 h-4"></i>
                                 <span>Test Connection</span>
                             </button>
                    <?php endif; ?>
                     </div>
                     <?php endif; ?>
                 </form>

                 <!-- User Profile Section -->
                 <div class="mt-8 bg-white p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="user" class="w-5 h-5 text-primary-600"></i>
                         <span>User Profile</span>
                     </h3>
                     
                     <form method="POST" class="space-y-6">
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                 <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                 <input 
                                     type="text" 
                                     id="first_name" 
                                     name="first_name" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>"
                                     placeholder="Enter your first name"
                                 >
                                 <p class="text-xs text-gray-500 mt-1">Your first name for display purposes</p>
                             </div>

                             <div>
                                 <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                 <input 
                                     type="text" 
                                     id="last_name" 
                                     name="last_name" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>"
                                     placeholder="Enter your last name"
                                 >
                                 <p class="text-xs text-gray-500 mt-1">Your last name for display purposes</p>
                             </div>
                         </div>

                         <div>
                             <label for="user_email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                             <input 
                                 type="email" 
                                 id="user_email" 
                                 name="user_email" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50"
                                 value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>"
                                 disabled
                             >
                             <p class="text-xs text-gray-500 mt-1">Your email address (cannot be changed)</p>
                         </div>

                         <div class="flex flex-col sm:flex-row gap-3">
                             <button type="submit" name="save_profile" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                                 <i data-lucide="save" class="w-4 h-4"></i>
                                 <span>Save Profile</span>
                             </button>
                         </div>
                     </form>
                 </div>

                 <!-- Company Settings Section -->
                 <div class="mt-8 bg-white p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="building" class="w-5 h-5 text-primary-600"></i>
                         <span>Company Settings</span>
                     </h3>
                     
                     <form method="POST" class="space-y-6">
                         <div>
                             <label for="company_name" class="block text-sm font-medium text-gray-700 mb-2">Company Name *</label>
                             <input 
                                 type="text" 
                                 id="company_name" 
                                 name="company_name" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                 required
                                 value="<?= htmlspecialchars($currentCompany['company_name'] ?? '') ?>"
                                 placeholder="Enter your company name"
                             >
                             <p class="text-xs text-gray-500 mt-1">The name of your company or organization</p>
                         </div>

                         <div>
                             <label for="company_address" class="block text-sm font-medium text-gray-700 mb-2">Company Address</label>
                             <textarea 
                                 id="company_address" 
                                 name="company_address" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                 rows="3"
                                 placeholder="Enter your company address"
                             ><?= htmlspecialchars($currentCompany['company_address'] ?? '') ?></textarea>
                             <p class="text-xs text-gray-500 mt-1">Your company's physical address</p>
                         </div>

                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                 <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                                 <input 
                                     type="tel" 
                                     id="contact_number" 
                                     name="contact_number" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     value="<?= htmlspecialchars($currentCompany['contact_number'] ?? '') ?>"
                                     placeholder="+1 (555) 123-4567"
                                 >
                                 <p class="text-xs text-gray-500 mt-1">Primary contact phone number</p>
                             </div>

                             <div>
                                 <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                                 <input 
                                     type="email" 
                                     id="contact_email" 
                                     name="contact_email" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     value="<?= htmlspecialchars($currentCompany['contact_email'] ?? '') ?>"
                                     placeholder="contact@yourcompany.com"
                                 >
                                 <p class="text-xs text-gray-500 mt-1">Primary contact email address</p>
                             </div>
                         </div>

                         <div>
                             <label for="company_logo_url" class="block text-sm font-medium text-gray-700 mb-2">Company Logo URL</label>
                             <input 
                                 type="url" 
                                 id="company_logo_url" 
                                 name="company_logo_url" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                 value="<?= htmlspecialchars($currentCompany['logo_url'] ?? '') ?>"
                                 placeholder="https://yourcompany.com/logo.png"
                                 oninput="updateCompanyLogoPreview()"
                                 onblur="updateCompanyLogoPreview()"
                             >
                             <p class="text-xs text-gray-500 mt-1">Optional: Enter a URL to your company logo. Recommended size: 200x60 pixels.</p>
                             
                             <div id="company_logo_preview_container" class="mt-3 p-3 bg-gray-50 rounded-lg" style="display: <?= !empty($currentCompany['logo_url']) ? 'block' : 'none' ?>;">
                                 <div class="text-sm font-medium text-gray-700 mb-2">Company Logo Preview:</div>
                                 <img id="company_logo_preview" 
                                      src="<?= htmlspecialchars($currentCompany['logo_url'] ?? '') ?>" 
                                      alt="Company Logo" 
                                      class="max-h-12 max-w-full object-contain"
                                      onerror="showCompanyLogoError()"
                                      onload="hideCompanyLogoError()">
                                 <div id="company_logo_error" class="text-sm text-red-600" style="display: none;">⚠️ Logo not accessible</div>
                             </div>
                         </div>

                         <div class="flex flex-col sm:flex-row gap-3">
                             <button type="submit" name="save_company" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                                 <i data-lucide="save" class="w-4 h-4"></i>
                                 <span>Save Company Settings</span>
                             </button>
                         </div>
                     </form>
                 </div>

                 <!-- Settings Status -->
                 <?php if ($currentSettings): ?>
                     <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
                         <div class="flex items-center space-x-4">
                             <i data-lucide="check-circle" class="w-8 h-8 text-green-600"></i>
                             <div>
                                 <h4 class="font-semibold text-green-800 mb-1">Settings Configured Successfully</h4>
                                 <div class="text-sm text-green-700">
                                     Last updated: <?= htmlspecialchars($currentSettings['updated_at'] ?? 'Unknown') ?>
                                 </div>
                                 <div class="text-sm text-green-700 mt-1">
                                     API URL: <?= htmlspecialchars(parse_url($currentSettings['api_url'], PHP_URL_HOST) ?? 'N/A') ?>
                                 </div>
                             </div>
                         </div>
                     </div>
                 <?php else: ?>
                     <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                         <div class="flex items-center space-x-4">
                             <i data-lucide="alert-triangle" class="w-8 h-8 text-yellow-600"></i>
                             <div>
                                 <h4 class="font-semibold text-yellow-800 mb-1">No Settings Configured</h4>
                                 <div class="text-sm text-yellow-700">
                                     Please fill out the form above to configure your WHMCS API settings.
                                 </div>
                             </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Help Section -->
                 <div class="mt-6 bg-white p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="help-circle" class="w-5 h-5 text-primary-600"></i>
                         <span>Need Help?</span>
                        </h3>
                        
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div class="bg-gray-50 p-4 rounded-lg">
                             <h4 class="font-semibold text-gray-900 mb-2 flex items-center space-x-2">
                                 <i data-lucide="key" class="w-4 h-4 text-primary-600"></i>
                                 <span>Finding API Credentials</span>
                             </h4>
                             <p class="text-sm text-gray-600 mb-3">API credentials can be found in your WHMCS admin area under System Settings → API Credentials.</p>
                             <a href="https://docs.whmcs.com/API_Authentication" target="_blank" class="text-primary-600 text-sm font-medium">View Documentation →</a>
                                </div>
                         <div class="bg-gray-50 p-4 rounded-lg">
                             <h4 class="font-semibold text-gray-900 mb-2 flex items-center space-x-2">
                                 <i data-lucide="globe" class="w-4 h-4 text-primary-600"></i>
                                 <span>Nameserver Setup</span>
                             </h4>
                             <p class="text-sm text-gray-600 mb-3">Configure your default nameservers that will be used when updating domain DNS settings.</p>
                             <a href="https://docs.whmcs.com/Domains" target="_blank" class="text-primary-600 text-sm font-medium">Domain Documentation →</a>
                         </div>
                     </div>
                 </div>
                 
                                  <?php elseif ($currentView === 'domains'): ?>
                 <!-- Domains Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <div class="flex items-center justify-between">
                         <div>
                             <h1 class="text-2xl font-bold text-gray-900 mb-2">Domains</h1>
                             <p class="text-gray-600">View and manage all your registered domains from local database.</p>
                         </div>
                         <div class="flex items-center space-x-4">
                             <button type="button" id="addDomainBtn" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                 <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                 Add Domain
                             </button>
                         </div>
                     </div>
                 </div>

                 <?php
                                  // Initialize database for domains view
                try {
                    $db = Database::getInstance();
                    
                    // Get search and filter parameters from URL
                    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
                    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
                    $registrarFilter = isset($_GET['registrar']) ? $_GET['registrar'] : '';
                    
                    // Helper function to build query string
                    function buildQueryString($page = null, $search = null, $status = null, $registrar = null) {
                        global $searchTerm, $statusFilter, $registrarFilter, $currentPage;
                        $params = ['view' => 'domains'];
                        if ($page !== null) $params['page'] = $page;
                        if ($search !== null) $params['search'] = $search;
                        elseif (!empty($searchTerm)) $params['search'] = $searchTerm;
                        if ($status !== null) $params['status'] = $status;
                        elseif (!empty($statusFilter)) $params['status'] = $statusFilter;
                        if ($registrar !== null) $params['registrar'] = $registrar;
                        elseif (!empty($registrarFilter)) $params['registrar'] = $registrarFilter;
                        return '?' . http_build_query($params);
                    }
                    
                    // Pagination logic
                    $domainsPerPage = 25; // Number of domains per page
                    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                    
                                         // Get domains from database with search and filters
                    $userEmail = $_SESSION['user_email'] ?? '';
                    $companyId = $_SESSION['company_id'] ?? null;
                    if (!empty($userEmail) && !empty($companyId)) {
                        $domainsForPage = $db->getDomains($companyId, $userEmail, $currentPage, $domainsPerPage, $searchTerm, $statusFilter, 'domain_name', 'ASC', $registrarFilter);
                        $totalDomains = $db->getDomainCount($companyId, $userEmail, $searchTerm, $statusFilter, $registrarFilter);
                        $totalPages = ceil($totalDomains / $domainsPerPage);
                        $offset = ($currentPage - 1) * $domainsPerPage;
                         
                         // Get domain statistics from database
                         $dbStats = $db->getDomainStats($companyId, $userEmail);
                    } else {
                        $domainsForPage = [];
                        $totalDomains = 0;
                        $totalPages = 0;
                        $offset = 0;
                        $dbStats = [];
                    }
                     $domainStats = [
                         'total_projects' => $dbStats['total_domains'] ?? 0,
                         'running_projects' => $dbStats['active_domains'] ?? 0,
                         'ended_projects' => $dbStats['expired_domains'] ?? 0,
                         'pending_projects' => $dbStats['pending_domains'] ?? 0
                     ];
                     
                                         // uniqueRegistrars is already set globally
                     
                     // Convert database format to API format for compatibility
                    $allDomains = [];
                    if (!empty($userEmail) && !empty($companyId)) {
                        $allDomainsFromDb = $db->getDomains($companyId, $userEmail, 1, 9999, '', '', 'domain_name', 'ASC', ''); // Get all for filters
                    } else {
                        $allDomainsFromDb = [];
                    }
                     foreach ($allDomainsFromDb as $domain) {
                         $allDomains[] = [
                             'id' => $domain['domain_id'],
                             'domainname' => $domain['domain_name'],
                             'status' => $domain['status'],
                             'registrar' => $domain['registrar'],
                             'expirydate' => $domain['expiry_date'],
                             'regdate' => $domain['registration_date'],
                             'nextduedate' => $domain['next_due_date'],
                             'amount' => $domain['amount'],
                             'currency' => $domain['currency']
                         ];
                     }
                     
                 } catch (Exception $e) {
                     // Fallback to empty data if database fails
                     $domainsForPage = [];
                     $totalDomains = 0;
                     $totalPages = 0;
                     $offset = 0;
                     $allDomains = [];
                     $domainStats = [
                         'total_projects' => 0,
                         'running_projects' => 0,
                         'ended_projects' => 0,
                         'pending_projects' => 0
                     ];
                 }
                 ?>

                 <!-- Domain Statistics Cards -->
                 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                     <div class="bg-primary-600 text-white p-6 rounded-xl">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-primary-100">Total Domains</h3>
                             <div class="w-8 h-8 bg-primary-500 rounded-full flex items-center justify-center">
                                 <i data-lucide="trending-up" class="w-4 h-4"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold"><?= $domainStats['total_projects'] ?></div>
                     </div>

                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-sm font-medium text-gray-500">Active Domains</h3>
                             <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="trending-up" class="w-4 h-4 text-green-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900"><?= $domainStats['running_projects'] ?></div>
                     </div>

                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <div class="flex items-center space-x-2">
                                 <h3 class="text-sm font-medium text-gray-500">Expired Domains</h3>
                                 <div class="relative group">
                                     <i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i>
                                     <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10 w-48">
                                         Counts domains with 'expired', 'terminated', or 'cancelled' status.
                                         <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                     </div>
                                 </div>
                             </div>
                             <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="trending-up" class="w-4 h-4 text-red-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900"><?= $domainStats['ended_projects'] ?></div>
                     </div>

                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <div class="flex items-center space-x-2">
                                 <h3 class="text-sm font-medium text-gray-500">Pending Domains</h3>
                                 <div class="relative group">
                                     <i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i>
                                     <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10 w-48">
                                         Counts domains with 'pending', 'pendingtransfer', 'pendingregistration' or other statuses
                                         <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                     </div>
                                 </div>
                             </div>
                             <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="clock" class="w-4 h-4 text-yellow-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900"><?= $domainStats['pending_projects'] ?></div>
                     </div>
                 </div>

                 <!-- Domains Table -->
                 <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                     <div class="px-6 py-4 border-b border-gray-200">
                         <!-- Search and Filters Form -->
                         <form method="GET" id="domainSearchForm">
                             <input type="hidden" name="view" value="domains">
                             
                             <div class="flex items-center justify-between mb-4">
                                 <h3 class="text-lg font-semibold text-gray-900">All Domains</h3>
                             </div>
                             
                             <!-- Search and Filters -->
                             <div class="flex items-center space-x-4 mb-4">
                                 <!-- Search Field -->
                                 <div class="relative">
                                     <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                         <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                                     </div>
                                     <input 
                                         type="text" 
                                         name="search"
                                         id="domainSearch" 
                                         placeholder="Search domains..." 
                                         value="<?= htmlspecialchars($searchTerm) ?>"
                                         class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm w-64"
                                     >
                                 </div>
                                 <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm">
                                     Search
                                 </button>
                                 <a href="?view=domains" class="px-4 py-2 text-primary-600 hover:text-primary-700 font-medium border border-primary-200 rounded-lg hover:bg-primary-50 transition-colors text-sm">
                                     Clear Filters
                                 </a>
                                 <div class="text-sm font-medium text-gray-700">Filters:</div>
                             
                                 <!-- Registrar Filter -->
                                 <div class="relative">
                                     <select name="registrar" id="registrarFilter" onchange="this.form.submit()" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white">
                                         <option value=""<?= (empty($registrarFilter) || $registrarFilter === '') ? ' selected' : '' ?>>All Registrars</option>
                                         <?php
                                         $registrars = array_unique(array_map(function($domain) {
                                             return $domain['registrar'] ?? 'Unknown';
                                         }, $allDomainsFromDb));
                                         sort($registrars);
                                         foreach ($registrars as $registrar): ?>
                                             <option value="<?= htmlspecialchars($registrar) ?>"<?= ($registrarFilter === $registrar) ? ' selected' : '' ?>><?= htmlspecialchars($registrar) ?></option>
                                         <?php endforeach; ?>
                                     </select>
                                 </div>
                                 
                                 <!-- Status Filter -->
                                 <div class="relative">
                                     <select name="status" id="statusFilter" onchange="this.form.submit()" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white">
                                         <option value="" <?= empty($statusFilter) ? 'selected' : '' ?>>All Statuses</option>
                                         <?php
                                         $statuses = array_unique(array_map(function($domain) {
                                             return $domain['status'] ?? 'Unknown';
                                         }, $allDomainsFromDb));
                                         sort($statuses);
                                         foreach ($statuses as $status): ?>
                                             <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                         <?php endforeach; ?>
                                     </select>
                                 </div>
                             </div>
                         </form>
                     </div>
                     
                     <?php if (!empty($domainsForPage)): ?>
                         <div class="overflow-x-auto">
                             <!-- Header -->
                             <div class="grid grid-cols-12 gap-0 bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                 <div class="col-span-3 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain Name</div>
                                 <div class="col-span-2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registrar</div>
                                 <div class="col-span-2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nameservers</div>
                                 <div class="col-span-2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</div>
                                 <div class="col-span-2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</div>
                                 <div class="col-span-1 px-6 py-3"></div>
                             </div>
                             <!-- Body -->
                             <div id="domainsTableBody" class="bg-white divide-y divide-gray-200">
                                 <?php foreach ($domainsForPage as $domain): ?>
                                     <?php
                                     // Handle both database and API format
                                     $domainName = $domain['domain_name'] ?? $domain['domainname'] ?? '';
                                     $registrar = $domain['registrar'] ?? 'Unknown';
                                     $expiryDate = $domain['expiry_date'] ?? $domain['expirydate'] ?? '';
                                     $status = $domain['status'] ?? 'Unknown';
                                     $ns1 = $domain['ns1'] ?? '';
                                     $ns2 = $domain['ns2'] ?? '';
                                     $ns3 = $domain['ns3'] ?? '';
                                     $ns4 = $domain['ns4'] ?? '';
                                     $ns5 = $domain['ns5'] ?? '';
                                     ?>
                                     <div class="grid grid-cols-12 gap-0 hover:bg-gray-50 transition-colors border-b border-gray-200" data-domain="<?= htmlspecialchars(strtolower($domainName)) ?>" data-registrar="<?= htmlspecialchars(strtolower($registrar)) ?>" data-expiry="<?= htmlspecialchars(strtolower(!empty($expiryDate) ? date('M j, Y', strtotime($expiryDate)) : 'n/a')) ?>" data-status="<?= htmlspecialchars(strtolower($status)) ?>">
                                         <div class="col-span-3 px-6 py-4 flex items-center">
                                             <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                 <i data-lucide="globe" class="w-4 h-4 text-primary-600"></i>
                                             </div>
                                             <div class="min-w-0 flex-1">
                                                 <div class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($domainName) ?></div>
                                             </div>
                                         </div>
                                         <div class="col-span-2 px-6 py-4 flex items-center">
                                             <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 truncate">
                                                 <?= htmlspecialchars($registrar) ?>
                                             </span>
                                         </div>
                                         <div class="col-span-2 px-6 py-4">
                                             <?php if (!empty($ns1)): ?>
                                                 <div class="text-xs space-y-0.5">
                                                     <div class="font-medium text-gray-900 truncate"><?= htmlspecialchars($ns1) ?></div>
                                                     <?php if (!empty($ns2)): ?>
                                                         <div class="text-gray-600 truncate"><?= htmlspecialchars($ns2) ?></div>
                                                     <?php endif; ?>
                                                     <?php if (!empty($ns3)): ?>
                                                         <div class="text-gray-600 truncate"><?= htmlspecialchars($ns3) ?></div>
                                                     <?php endif; ?>
                                                     <?php if (!empty($ns4)): ?>
                                                         <div class="text-gray-600 truncate"><?= htmlspecialchars($ns4) ?></div>
                                                     <?php endif; ?>
                                                     <?php if (!empty($ns5)): ?>
                                                         <div class="text-gray-600 truncate"><?= htmlspecialchars($ns5) ?></div>
                                                     <?php endif; ?>
                                                 </div>
                                             <?php else: ?>
                                                 <span class="text-xs text-gray-400">Not available</span>
                                             <?php endif; ?>
                                         </div>
                                         <div class="col-span-2 px-6 py-4 flex items-center text-sm text-gray-900">
                                             <?php 
                                             if (!empty($expiryDate)) {
                                                 $expiryTimestamp = strtotime($expiryDate);
                                                 $daysUntilExpiry = ceil(($expiryTimestamp - time()) / (60 * 60 * 24));
                                                 $expiryClass = $daysUntilExpiry <= 30 ? 'text-red-600' : ($daysUntilExpiry <= 90 ? 'text-yellow-600' : 'text-gray-900');
                                                 echo '<span class="' . $expiryClass . '">' . date('M j, Y', $expiryTimestamp) . '</span>';
                                             } else {
                                                 echo 'N/A';
                                             }
                                             ?>
                                         </div>
                                         <div class="col-span-2 px-6 py-4 flex items-center">
                                             <?php
                                             $statusColors = [
                                                 'Active' => 'bg-green-100 text-green-800',
                                                 'Pending' => 'bg-yellow-100 text-yellow-800',
                                                 'Suspended' => 'bg-red-100 text-red-800',
                                                 'Cancelled' => 'bg-gray-100 text-gray-800',
                                                 'Expired' => 'bg-red-100 text-red-800',
                                                 'Terminated' => 'bg-red-100 text-red-800'
                                             ];
                                             $statusColor = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                             ?>
                                             <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusColor ?>">
                                                 <?= htmlspecialchars($status) ?>
                                             </span>
                                         </div>
                                         <div class="col-span-1 px-6 py-4 flex items-center justify-end space-x-1">
                                             <button type="button" 
                                                     class="edit-domain-btn inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                                     data-domain-id="<?= htmlspecialchars($domain['domain_id'] ?? $domain['id'] ?? '') ?>"
                                                     data-domain-name="<?= htmlspecialchars($domainName) ?>"
                                                     data-registrar="<?= htmlspecialchars($registrar) ?>"
                                                     data-status="<?= htmlspecialchars($status) ?>"
                                                     data-expiry-date="<?= htmlspecialchars($expiryDate) ?>"
                                                     data-nameservers="<?= htmlspecialchars($ns1 . ($ns2 ? ', ' . $ns2 : '') . ($ns3 ? ', ' . $ns3 : '') . ($ns4 ? ', ' . $ns4 : '') . ($ns5 ? ', ' . $ns5 : '')) ?>">
                                                 <i data-lucide="edit-3" class="w-3 h-3 mr-1"></i>
                                                 Edit
                                             </button>
                                             <button type="button" 
                                                     class="delete-domain-btn inline-flex items-center px-2 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors"
                                                     data-domain-id="<?= htmlspecialchars($domain['domain_id'] ?? $domain['id'] ?? '') ?>"
                                                     data-domain-name="<?= htmlspecialchars($domainName) ?>">
                                                 <i data-lucide="trash-2" class="w-3 h-3 mr-1"></i>
                                                 Delete
                                             </button>
                                         </div>
                                     </div>
                                 <?php endforeach; ?>
                             </div>
                         </div>
                         
                         <!-- Pagination -->
                         <?php if ($totalPages > 1): ?>
                             <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                                 <div class="flex items-center justify-between">
                                     <div class="text-sm text-gray-700">
                                         Showing <?= $offset + 1 ?> to <?= min($offset + $domainsPerPage, $totalDomains) ?> of <?= $totalDomains ?> domains
                                     </div>
                                     <div class="flex items-center space-x-2">
                                         <!-- Previous Page -->
                                         <?php if ($currentPage > 1): ?>
                                             <a href="<?= buildQueryString($currentPage - 1) ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                 Previous
                                             </a>
                                         <?php else: ?>
                                             <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                                                 Previous
                                             </span>
                                         <?php endif; ?>
                                         
                                         <!-- Page Numbers -->
                                         <?php
                                         $startPage = max(1, $currentPage - 2);
                                         $endPage = min($totalPages, $currentPage + 2);
                                         
                                         if ($startPage > 1): ?>
                                             <a href="<?= buildQueryString(1) ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                 1
                                             </a>
                                             <?php if ($startPage > 2): ?>
                                                 <span class="px-3 py-2 text-sm text-gray-500">...</span>
                                             <?php endif; ?>
                                         <?php endif; ?>
                                         
                                         <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                             <?php if ($i == $currentPage): ?>
                                                 <span class="px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-primary-600 rounded-lg">
                                                     <?= $i ?>
                                                 </span>
                                             <?php else: ?>
                                                 <a href="<?= buildQueryString($i) ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                     <?= $i ?>
                                                 </a>
                                             <?php endif; ?>
                                         <?php endfor; ?>
                                         
                                         <?php if ($endPage < $totalPages): ?>
                                             <?php if ($endPage < $totalPages - 1): ?>
                                                 <span class="px-3 py-2 text-sm text-gray-500">...</span>
                                             <?php endif; ?>
                                             <a href="<?= buildQueryString($totalPages) ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                 <?= $totalPages ?>
                                             </a>
                                         <?php endif; ?>
                                         
                                         <!-- Next Page -->
                                         <?php if ($currentPage < $totalPages): ?>
                                             <a href="<?= buildQueryString($currentPage + 1) ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                 Next
                                             </a>
                                         <?php else: ?>
                                             <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                                                 Next
                                             </span>
                                         <?php endif; ?>
                                     </div>
                                 </div>
                             </div>
                         <?php endif; ?>
                     <?php else: ?>
                         <div class="text-center py-12">
                             <i data-lucide="database" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                             <h3 class="text-lg font-medium text-gray-900 mb-2">No domains in database</h3>
                             <p class="text-gray-500 mb-4">No domains have been synced to the local database yet. Sync your domains to view them here.</p>
                             <a href="sync_interface.php" class="btn btn-primary">
                                 <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
                                 Sync Domains Now
                             </a>
                         </div>
                     <?php endif; ?>
                 </div>

                 <?php elseif ($currentView === 'nameservers'): ?>
                 <!-- Nameservers Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">Update Nameservers</h1>
                     <p class="text-gray-600">Batch update nameservers for multiple domains simultaneously.</p>
                                </div>

                                 <!-- Status Message -->
                <?php if ($updateMessage): ?>
                    <div class="mb-6 p-4 rounded-lg <?= strpos($updateMessage, 'successful') !== false ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-yellow-50 border border-yellow-200 text-yellow-800' ?>">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="<?= strpos($updateMessage, 'successful') !== false ? 'check-circle' : 'alert-triangle' ?>" class="w-5 h-5"></i>
                            <span><?= htmlspecialchars($updateMessage) ?></span>
                        </div>
                    </div>
                <?php endif; ?>



                 <!-- Configuration Info -->
                 <?php if ($currentSettings): ?>
                     <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                         <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                             <i data-lucide="info" class="w-5 h-5 text-primary-600"></i>
                             <span>Current Configuration</span>
                         </h3>
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div class="bg-gray-50 p-4 rounded-lg">
                                 <div class="text-sm text-gray-500">Primary Nameserver</div>
                                 <div class="font-semibold text-gray-900"><?= htmlspecialchars($currentSettings['default_ns1']) ?></div>
                            </div>
                             <div class="bg-gray-50 p-4 rounded-lg">
                                 <div class="text-sm text-gray-500">Secondary Nameserver</div>
                                 <div class="font-semibold text-gray-900"><?= htmlspecialchars($currentSettings['default_ns2']) ?></div>
                             </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Detailed Results (if any) -->
                 <?php if (!empty($updateResults)): ?>
                     <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                         <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                             <i data-lucide="list" class="w-5 h-5 text-primary-600"></i>
                             <span>Update Results (<?= count($updateResults) ?> domains processed)</span>
                         </h3>
                         <div class="space-y-3">
                             <?php foreach ($updateResults as $result): ?>
                                 <div class="flex items-center justify-between p-3 rounded-lg <?= $result['status'] === 'SUCCESS' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?>">
                                     <div class="font-medium text-gray-900"><?= htmlspecialchars($result['domain']) ?></div>
                                     <div class="flex items-center space-x-3">
                                         <span class="px-2 py-1 text-xs rounded-full <?= $result['status'] === 'SUCCESS' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                             <?= $result['status'] ?>
                                         </span>
                                         <?php if (!empty($result['message'])): ?>
                                             <span class="text-xs text-gray-500" title="<?= htmlspecialchars($result['message']) ?>">
                                                 <?= htmlspecialchars($result['message']) ?>
                                             </span>
                                         <?php endif; ?>
                                </div>
                            </div>
                             <?php endforeach; ?>
                        </div>
                    </div>
                 <?php endif; ?>

                 <!-- Domain Selection Form -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="target" class="w-5 h-5 text-primary-600"></i>
                         <span>Select Domains to Update</span>
                        </h3>

                     <form method="POST" class="space-y-6">
                         <div>
                                                         <div class="flex justify-between items-center mb-3">
                                <label for="domain" class="block text-sm font-medium text-gray-700">Available Domains (<?= count($allDomains) ?> total, sorted alphabetically)</label>
                                <div class="flex gap-2">
                                    <button type="button" id="selectAllBtn" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3 py-1 rounded-md text-sm font-medium transition-colors">Select All</button>
                                    <button type="button" id="clearAllBtn" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3 py-1 rounded-md text-sm font-medium transition-colors">Clear All</button>
                                    <a href="?view=nameservers&clear_cache=1" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 border border-yellow-300 px-3 py-1 rounded-md text-sm font-medium transition-colors">Refresh List</a>
                                    <a href="?view=nameservers&force_refresh=1" class="bg-red-100 hover:bg-red-200 text-red-800 border border-red-300 px-3 py-1 rounded-md text-sm font-medium transition-colors">Force Refresh</a>
                                    <button type="button" onclick="showCacheModal()" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 border border-yellow-300 px-3 py-1 rounded-md text-sm font-medium transition-colors">Clear Cache</button>
                                </div>
                            </div>
                             
                             <select name="domain[]" id="domain" required multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent" style="min-height: 300px;">
                                 <?php foreach ($allDomains as $d): ?>
                                     <option value="<?= htmlspecialchars($d['domainname']) ?>">
                                         <?= htmlspecialchars($d['domainname']) ?>
                                     </option>
                                 <?php endforeach; ?>
                             </select>
                             
                             <div class="mt-2 flex items-center justify-between text-xs text-gray-500">
                                 <span>Hold <strong>Ctrl/Cmd</strong> to select multiple domains</span>
                                 <span id="selectionCount" class="font-medium">0 domains selected</span>
                        </div>
                    </div>

                         <div class="flex justify-center">
                             <button type="submit" name="update" id="updateDomainsBtn" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center space-x-2">
                                 <i data-lucide="rocket" class="w-5 h-5"></i>
                                 <span>Update Selected Domains</span>
                             </button>
                </div>
                         
                         <div class="text-center">
                             <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                 <div class="flex items-center justify-center gap-2 mb-2">
                                     <i data-lucide="clock" class="w-5 h-5 text-blue-600"></i>
                                     <span class="font-semibold text-blue-800">Important Information</span>
            </div>
                                 <p class="text-sm text-blue-700 leading-relaxed">
                                     After clicking the button, please <strong>wait for the page to refresh</strong> and do not close your browser tab. 
                                     The nameserver update process may take a few moments to complete, especially for multiple domains. 
                                     You'll see the results displayed on this page once the process finishes.
                                 </p>
        </div>
                         </div>
                     </form>
    </div>

                 <!-- Activity Log -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="file-text" class="w-5 h-5 text-primary-600"></i>
                         <span>Activity Log</span>
                     </h3>
                     <div class="overflow-x-auto">
                         <table class="w-full">
                             <thead class="bg-gray-50 border-b border-gray-200">
                                 <tr>
                                     <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date & Time</th>
                                     <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Domain</th>
                                     <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                 <?php
                                 // Show user-specific log file
                                 $logFile = 'logs/ns_update_log_' . md5($_SESSION['user_email']) . '.txt';
                                 
                                 if (file_exists($logFile)) {
                                     $logLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                                     if (!empty($logLines)) {
                                         // Reverse array to show newest entries first
                                         $logLines = array_reverse($logLines);
                                         
                                         foreach ($logLines as $line) {
                                             // Parse log line: "2025-07-25 18:01:52 - domain.com - SUCCESS - "
                                             if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) - (.+?) - (.+?)(?:\s*-\s*(.+))?$/', trim($line), $matches)) {
                                                 $datetime = $matches[1];
                                                 $domain = trim($matches[2]);
                                                 $status = trim($matches[3]);
                                                 $message = isset($matches[4]) ? trim($matches[4]) : '';
                                                 
                                                 // Clean up status (remove trailing dash and spaces)
                                                 $status = rtrim($status, ' -');
                                                 
                                                 // Determine status styling
                                                 $statusClass = $status === 'SUCCESS' ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50';
                                                 $statusIcon = $status === 'SUCCESS' ? '✅' : '❌';
                                                 
                                                 echo '<tr class="hover:bg-gray-50">';
                                                 echo '<td class="px-4 py-3 text-sm text-gray-900 font-mono">' . htmlspecialchars($datetime) . '</td>';
                                                 echo '<td class="px-4 py-3 text-sm text-gray-900 font-medium">' . htmlspecialchars($domain) . '</td>';
                                                 echo '<td class="px-4 py-3 text-sm">';
                                                 echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $statusClass . '">';
                                                 echo '<span class="mr-1">' . $statusIcon . '</span>';
                                                 echo htmlspecialchars($status);
                                                 echo '</span>';
                                                 if (!empty($message)) {
                                                     echo '<div class="text-xs text-gray-500 mt-1">' . htmlspecialchars($message) . '</div>';
                                                 }
                                                 echo '</td>';
                                                 echo '</tr>';
                                             }
                                         }
                                     } else {
                                         echo '<tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No updates logged yet. Start by selecting domains and clicking "Update Selected Domains".</td></tr>';
                                     }
                                 } else {
                                     echo '<tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No updates logged yet. Start by selecting domains and clicking "Update Selected Domains".</td></tr>';
                                 }
                                 ?>
                             </tbody>
                         </table>
                     </div>
                 </div>
                 
                 <?php elseif ($currentView === 'export'): ?>
                 <!-- Export Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">📁 Export Domain Data</h1>
                     <p class="text-gray-600">Export your domain information to CSV format with progress tracking and timeout protection.</p>
                 </div>

                 <!-- Progress-Based Export System -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="download" class="w-5 h-5 text-primary-600"></i>
                         <span>Progress-Based Export</span>
                     </h3>
                     
                     <!-- Helper Information -->
                     <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                         <div class="flex items-start space-x-3">
                             <i data-lucide="lightbulb" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                             <div>
                                 <h4 class="font-semibold text-blue-800 mb-1">How It Works</h4>
                                 <p class="text-sm text-blue-700 mb-2">This system processes domains one by one to avoid timeout errors. Each batch processes 50 domains and creates a separate CSV file.</p>
                                 <div class="text-xs text-blue-600 space-y-1">
                                     <div>• <strong>Step 1:</strong> Enter batch number and click "Start Export"</div>
                                     <div>• <strong>Step 2:</strong> Watch the progress bar as domains are processed</div>
                                     <div>• <strong>Step 3:</strong> Download the completed CSV file</div>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <!-- Export Controls -->
                     <div id="export-controls">
                         <form id="export-form" class="space-y-4">
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                 <div>
                                     <label for="batch_number" class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                         Batch Number
                                         <div class="relative group">
                                             <i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i>
                                             <div class="absolute bottom-full left-0 mb-2 px-3 py-2 bg-gray-800 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 w-64 z-10">
                                                 Batch 1 = domains 1-50<br>Batch 2 = domains 51-100, etc.
                                                 <div class="absolute top-full left-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-800"></div>
                                             </div>
                                         </div>
                                     </label>
                                     <input 
                                         type="number" 
                                         id="batch_number" 
                                         name="batch_number" 
                                         value="1" 
                                         min="1"
                                         class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                         required
                                     >
                                 </div>
                                 <div class="flex items-end">
                                     <button type="submit" id="start-btn" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                                         <i data-lucide="play" class="w-4 h-4"></i>
                                         <span>Start Export</span>
                                     </button>
                                 </div>
                             </div>
                         </form>
                     </div>

                     <!-- Export Progress -->
                     <div id="export-progress" style="display: none;" class="mt-6">
                         <h4 class="font-semibold text-gray-900 mb-4">Export Progress</h4>
                         
                         <!-- Progress Bar -->
                         <div class="mb-4">
                             <div class="flex justify-between text-sm text-gray-600 mb-2">
                                 <span>Progress</span>
                                 <span id="progress-text">0%</span>
                             </div>
                             <div class="w-full bg-gray-200 rounded-full h-3">
                                 <div id="progress-fill" class="bg-primary-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                             </div>
                         </div>

                         <!-- Current Status -->
                         <div id="current-domain" class="text-sm text-gray-600 mb-4">Ready to start...</div>

                         <!-- Recent Results -->
                         <div id="export-results" class="space-y-2"></div>
                     </div>
                 </div>

                 <!-- Available CSV Files -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="folder" class="w-5 h-5 text-primary-600"></i>
                         <span>Available CSV Export Files</span>
                     </h3>
                     <div id="csv-files-list" class="csv-files-container">
                         <!-- CSV files will be loaded here -->
                     </div>
                 </div>

                 <!-- Export Information -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mt-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="info" class="w-5 h-5 text-primary-600"></i>
                         <span>Export Details</span>
                     </h3>
                     
                     <div class="bg-gray-50 rounded-lg p-6">
                         <h4 class="font-semibold text-gray-900 mb-4">CSV File Contents</h4>
                         <p class="text-gray-600 text-sm mb-4">Each exported CSV file will contain the following information for all domains:</p>
                         
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div class="space-y-2">
                                 <div class="flex items-center space-x-2">
                                     <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                     <span class="text-sm">Domain Name</span>
                                 </div>
                                 <div class="flex items-center space-x-2">
                                     <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                     <span class="text-sm">Domain ID</span>
                                 </div>
                                 <div class="flex items-center space-x-2">
                                     <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                     <span class="text-sm">Status</span>
                                 </div>
                             </div>
                             <div class="space-y-2">
                                 <div class="flex items-center space-x-2">
                                     <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                     <span class="text-sm">Nameservers (NS1-NS5)</span>
                                 </div>
                                 <div class="flex items-center space-x-2">
                                     <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                     <span class="text-sm">Domain Notes</span>
                                 </div>
                             </div>
                         </div>

                         <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                             <div class="text-sm text-yellow-800">
                                 <strong>Note:</strong> All domains (Active, Expired, Pending, etc.) will be included in the export.
                             </div>
                         </div>
                     </div>
                 </div>

                 <!-- Progress Export JavaScript -->
                 <script>
                     let currentDomain = 0;
                     let totalDomains = 0;
                     let batchNumber = 1;
                     let results = [];

                     // Export form submission
                     document.getElementById('export-form').addEventListener('submit', function(e) {
                         e.preventDefault();
                         
                         batchNumber = parseInt(document.getElementById('batch_number').value);
                         
                         // Show progress section
                         document.getElementById('export-progress').style.display = 'block';
                         document.getElementById('export-controls').style.display = 'none';
                         
                         // Reset progress
                         document.getElementById('progress-fill').style.width = '0%';
                         document.getElementById('progress-text').textContent = '0%';
                         document.getElementById('current-domain').textContent = 'Starting export...';
                         document.getElementById('export-results').innerHTML = '';
                         
                         results = [];
                         currentDomain = 0;
                         
                         // Start export
                         startExport();
                     });

                     function startExport() {
                         fetch('export_progress.php', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/x-www-form-urlencoded',
                             },
                             body: 'action=start_export&batch_number=' + batchNumber
                         })
                         .then(response => response.json())
                         .then(data => {
                             if (data.error) {
                                 console.error('Error:', data.error);
                                 document.getElementById('current-domain').textContent = 'Error: ' + data.error;
                                 return;
                             }
                             
                             totalDomains = data.total_domains;
                             currentDomain = 0;
                             
                             // Start processing domains
                             processNextDomain();
                         })
                         .catch(error => {
                             console.error('Error:', error);
                             document.getElementById('current-domain').textContent = 'Error starting export: ' + error.message;
                         });
                     }

                     function processNextDomain() {
                         if (currentDomain >= totalDomains) {
                             return;
                         }

                         fetch('export_progress.php', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/x-www-form-urlencoded',
                             },
                             body: 'action=progress&batch_number=' + batchNumber + '&current_domain=' + currentDomain + '&total_domains=' + totalDomains
                         })
                         .then(response => response.json())
                         .then(data => {
                             if (data.error) {
                                 console.error('Error:', data.error);
                             }
                             
                             // Check if export is complete
                             if (data.status === 'complete') {
                                 document.getElementById('progress-fill').style.width = '100%';
                                 document.getElementById('progress-text').textContent = '100%';
                                 document.getElementById('current-domain').innerHTML = `
                                     <strong class="text-green-600">✅ Export completed successfully!</strong><br>
                                     <small class="text-gray-600">Processed ${data.total_processed} domains</small>
                                 `;
                                 
                                 // Show download button
                                 showDownloadButton(data.filename);
                                 
                                 // Reload CSV files list
                                 setTimeout(loadCsvFiles, 1000);
                                 return;
                             }
                             
                             // Update progress
                             const progress = data.progress || 0;
                             document.getElementById('progress-fill').style.width = progress + '%';
                             document.getElementById('progress-text').textContent = progress + '%';
                             
                             // Update current domain info
                             document.getElementById('current-domain').textContent = 
                                 `Processing: ${data.domain_name} (${data.current_domain}/${data.total_domains})`;
                             
                             // Store result
                             results.push({
                                 domain: data.domain_name,
                                 success: data.success || false,
                                 error: data.error || null,
                                 nameservers: data.nameservers || null
                             });
                             
                             // Update results display
                             updateResultsDisplay();
                             
                             currentDomain++;
                             
                             // Process next domain after a short delay
                             setTimeout(processNextDomain, 100);
                         })
                         .catch(error => {
                             console.error('Error:', error);
                             document.getElementById('current-domain').textContent = 'Error processing domain: ' + error.message;
                         });
                     }

                     function updateResultsDisplay() {
                         const resultsDiv = document.getElementById('export-results');
                         let html = '<h5 class="font-medium text-gray-900 mb-2">Recent Results:</h5>';
                         
                         const recentResults = results.slice(-5);
                         recentResults.forEach(result => {
                             const statusClass = result.success ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50';
                             const statusText = result.success ? '✅ Success' : '❌ ' + (result.error || 'Error');
                             
                             html += `
                                 <div class="p-3 border rounded-lg ${statusClass}">
                                     <strong class="text-sm">${result.domain}</strong> - <span class="text-sm">${statusText}</span>
                                 </div>
                             `;
                         });
                         
                         resultsDiv.innerHTML = html;
                     }

                     function loadCsvFiles() {
                         fetch('export_progress.php', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/x-www-form-urlencoded',
                             },
                             body: 'action=get_csv_files'
                         })
                         .then(response => response.json())
                         .then(data => {
                             const csvFilesList = document.getElementById('csv-files-list');
                             
                             if (data.total_files === 0) {
                                 csvFilesList.innerHTML = `
                                     <div class="text-center py-8 text-gray-500">
                                         <i data-lucide="folder-open" class="w-12 h-12 mx-auto mb-4 text-gray-300"></i>
                                         <p>No CSV files found. Export some domains to see files here.</p>
                                     </div>
                                 `;
                                 return;
                             }
                             
                             let html = `<p class="text-sm text-gray-600 mb-4">Found ${data.total_files} CSV file(s):</p>`;
                             
                             data.files.forEach(file => {
                                 html += `
                                     <div class="border border-gray-200 rounded-lg p-4 mb-3 bg-gray-50">
                                         <div class="flex justify-between items-center">
                                             <div class="flex-1">
                                                 <h4 class="font-medium text-gray-900 text-sm mb-1">📄 ${file.filename}</h4>
                                                 <p class="text-xs text-gray-500 mb-1"><strong>Size:</strong> ${file.size_formatted} bytes</p>
                                                 <p class="text-xs text-gray-500"><strong>Created:</strong> ${file.date}</p>
                                             </div>
                                             <div>
                                                 <a href="${file.filename}" download class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                                     📥 Download
                                                 </a>
                                             </div>
                                         </div>
                                     </div>
                                 `;
                             });
                             
                             csvFilesList.innerHTML = html;
                         })
                         .catch(error => {
                             console.error('Error loading CSV files:', error);
                             document.getElementById('csv-files-list').innerHTML = `
                                 <div class="text-center py-8 text-red-500">
                                     <p>Error loading CSV files. Please refresh the page.</p>
                                 </div>
                             `;
                         });
                     }

                     function showDownloadButton(filename) {
                         const resultsDiv = document.getElementById('export-results');
                         const downloadHtml = `
                             <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                 <h4 class="font-semibold text-green-800 mb-2">📁 Export Complete!</h4>
                                 <p class="text-sm text-green-700 mb-3">Your CSV file has been generated successfully.</p>
                                 <div class="flex space-x-3">
                                     <a href="${filename}" download class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                         📥 Download CSV File
                                     </a>
                                     <button onclick="location.reload()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                         🔄 Export Another Batch
                                     </button>
                                 </div>
                             </div>
                         `;
                         resultsDiv.innerHTML += downloadHtml;
                     }

                     // Load CSV files when page loads
                     document.addEventListener('DOMContentLoaded', function() {
                         loadCsvFiles();
                     });
                 </script>

                 <!-- CSV Files Container Styles -->
                 <style>
                     .csv-files-container {
                         max-height: 600px;
                         overflow-y: auto;
                         border: 1px solid #ddd;
                         border-radius: 8px;
                         background-color: #fff;
                         padding: 20px;
                     }
                     .csv-files-container::-webkit-scrollbar {
                         width: 8px;
                     }
                     .csv-files-container::-webkit-scrollbar-track {
                         background: #f1f1f1;
                         border-radius: 4px;
                     }
                     .csv-files-container::-webkit-scrollbar-thumb {
                         background: #c1c1c1;
                         border-radius: 4px;
                     }
                     .csv-files-container::-webkit-scrollbar-thumb:hover {
                         background: #a8a8a8;
                     }
                 </style>
                 
                 <?php elseif ($currentView === 'database_setup'): ?>
                 <?php if (!isServerAdmin()): ?>
                     <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600"></i>
                             <div>
                                 <h3 class="text-lg font-semibold text-red-900">Access Denied</h3>
                                 <p class="text-red-800 mt-1">You don't have permission to access Database Setup. Only server administrators can configure the database.</p>
                             </div>
                         </div>
                     </div>
                 <?php else: ?>
                 <!-- Database Setup Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">🗄️ Database Setup</h1>
                     <p class="text-gray-600">Configure MySQL database for domain data storage</p>
                 </div>

                 <!-- Messages -->
                 <?php if ($dbMessage): ?>
                     <div class="mb-6 p-4 rounded-lg <?= $dbMessageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : ($dbMessageType === 'warning' ? 'bg-yellow-50 border border-yellow-200 text-yellow-800' : 'bg-red-50 border border-red-200 text-red-800') ?>">
                         <div class="flex items-center space-x-3">
                             <span style="font-size: 1.5rem;">
                                 <?= $dbMessageType === 'success' ? '✅' : ($dbMessageType === 'warning' ? '⚠️' : '❌') ?>
                             </span>
                             <div>
                                 <div class="font-semibold">
                                     <?= $dbMessageType === 'success' ? 'Success' : ($dbMessageType === 'warning' ? 'Warning' : 'Error') ?>
                                 </div>
                                 <div class="text-sm mt-1"><?= htmlspecialchars($dbMessage) ?></div>
                             </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Database Configuration Form -->
                 <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                     <div class="flex items-center justify-between mb-4">
                         <h3 class="text-lg font-semibold text-gray-900 flex items-center space-x-2">
                             <i data-lucide="database" class="w-5 h-5 text-primary-600"></i>
                             <span>MySQL Database Configuration</span>
                         </h3>
                         <?php if ($setupStatus['database_setup']): ?>
                             <div class="flex items-center space-x-2 text-green-600">
                                 <i data-lucide="check-circle" class="w-5 h-5"></i>
                                 <span class="text-sm font-medium">Completed</span>
                             </div>
                         <?php endif; ?>
                     </div>
                     
                     <form method="POST" class="space-y-6">
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                 <label for="db_host" class="block text-sm font-medium text-gray-700 mb-2">Database Host *</label>
                                 <input 
                                     type="text" 
                                     name="db_host" 
                                     id="db_host" 
                                     value="<?= htmlspecialchars($currentHost) ?>"
                                     placeholder="localhost"
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     required
                                 >
                                 <p class="text-xs text-gray-500 mt-1">Usually 'localhost' for local installations</p>
                             </div>
                             
                             <div>
                                 <label for="db_port" class="block text-sm font-medium text-gray-700 mb-2">Database Port *</label>
                                 <input 
                                     type="number" 
                                     name="db_port" 
                                     id="db_port" 
                                     value="<?= htmlspecialchars($currentPort) ?>"
                                     placeholder="3306"
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     required
                                 >
                                 <p class="text-xs text-gray-500 mt-1">Default MySQL port is 3306</p>
                             </div>
                         </div>
                         
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                             <div>
                                 <label for="db_name" class="block text-sm font-medium text-gray-700 mb-2">Database Name *</label>
                                 <input 
                                     type="text" 
                                     name="db_name" 
                                     id="db_name" 
                                     value="<?= htmlspecialchars($currentDatabase) ?>"
                                     placeholder="domain_tools"
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     required
                                 >
                                 <p class="text-xs text-gray-500 mt-1">Database will be created if it doesn't exist</p>
                             </div>
                             
                             <div>
                                 <label for="db_user" class="block text-sm font-medium text-gray-700 mb-2">Database Username *</label>
                                 <input 
                                     type="text" 
                                     name="db_user" 
                                     id="db_user" 
                                     value="<?= htmlspecialchars($currentUser) ?>"
                                     placeholder="root"
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     required
                                 >
                                 <p class="text-xs text-gray-500 mt-1">MySQL user with CREATE/DROP privileges</p>
                             </div>
                         </div>
                         
                         <div>
                             <label for="db_password" class="block text-sm font-medium text-gray-700 mb-2">Database Password</label>
                             <input 
                                 type="password" 
                                 name="db_password" 
                                 id="db_password" 
                                 value="<?= htmlspecialchars($currentPassword) ?>"
                                 placeholder="Enter database password"
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                             >
                             <p class="text-xs text-gray-500 mt-1">Leave empty if no password is set</p>
                         </div>
                         
                         <div class="flex items-center space-x-4">
                             <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                                 <i data-lucide="database" class="w-4 h-4"></i>
                                 <span>Test & Save Configuration</span>
                             </button>
                         </div>
                     </form>
                 </div>

                 <!-- Information Section -->
                 <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
                     <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
                         <span>Database Requirements</span>
                     </h3>
                     
                     <div class="space-y-3 text-sm text-blue-800">
                         <div class="flex items-start gap-2">
                             <i data-lucide="check-circle" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                             <div>MySQL 5.7+ or MariaDB 10.2+ with InnoDB support</div>
                         </div>
                         <div class="flex items-start gap-2">
                             <i data-lucide="check-circle" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                             <div>Database user with CREATE, DROP, INSERT, UPDATE, DELETE, and SELECT privileges</div>
                         </div>
                         <div class="flex items-start gap-2">
                             <i data-lucide="check-circle" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                             <div>UTF8MB4 character set support for proper domain name storage</div>
                         </div>
                         <div class="flex items-start gap-2">
                             <i data-lucide="check-circle" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                             <div>PHP PDO MySQL extension enabled</div>
                         </div>
                     </div>
                 </div>

                 <!-- Features Section -->
                 <div class="bg-green-50 border border-green-200 rounded-xl p-6">
                     <h3 class="text-lg font-semibold text-green-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="zap" class="w-5 h-5 text-green-600"></i>
                         <span>Database Features</span>
                     </h3>
                     
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                         <div class="space-y-2">
                             <div class="flex items-center gap-2">
                                 <i data-lucide="database" class="w-4 h-4 text-green-600"></i>
                                 <span class="text-sm font-medium text-green-800">Local Domain Storage</span>
                             </div>
                             <div class="flex items-center gap-2">
                                 <i data-lucide="search" class="w-4 h-4 text-green-600"></i>
                                 <span class="text-sm font-medium text-green-800">Fast Search & Filtering</span>
                             </div>
                             <div class="flex items-center gap-2">
                                 <i data-lucide="refresh-cw" class="w-4 h-4 text-green-600"></i>
                                 <span class="text-sm font-medium text-green-800">Batch Sync Operations</span>
                             </div>
                         </div>
                         <div class="space-y-2">
                             <div class="flex items-center gap-2">
                                 <i data-lucide="bar-chart-3" class="w-4 h-4 text-green-600"></i>
                                 <span class="text-sm font-medium text-green-800">Domain Statistics</span>
                             </div>
                             <div class="flex items-center gap-2">
                                 <i data-lucide="clock" class="w-4 h-4 text-green-600"></i>
                                 <span class="text-sm font-medium text-green-800">Sync History Tracking</span>
                             </div>
                             <div class="flex items-center gap-2">
                                 <i data-lucide="shield" class="w-4 h-4 text-green-600"></i>
                                 <span class="text-sm font-medium text-green-800">Data Integrity</span>
                             </div>
                         </div>
                     </div>
                 </div>
                 <?php endif; ?>
                 
                 <?php elseif ($currentView === 'database_view'): ?>
                 <!-- Database View Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">🌐 Domains Table</h1>
                     <p class="text-gray-600">View and manage domains from local database</p>
                 </div>

                 <!-- Error Message -->
                 <?php if ($databaseViewError): ?>
                     <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                             <div>
                                 <div class="font-semibold">Database Error</div>
                                 <div class="text-sm mt-1"><?= htmlspecialchars($databaseViewError) ?></div>
                             </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Sync Status -->
                 <?php if ($databaseViewLastSync): ?>
                     <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="refresh-cw" class="w-5 h-5 text-blue-600"></i>
                             <div>
                                 <div class="font-semibold">Last Sync: <?= date('M j, Y g:i A', strtotime($databaseViewLastSync['sync_started'])) ?></div>
                                 <div class="text-sm mt-1">
                                     Batch <?= $databaseViewLastSync['batch_number'] ?> - 
                                     <?= $databaseViewLastSync['domains_processed'] ?> domains processed
                                     (<?= $databaseViewLastSync['domains_added'] ?> added, <?= $databaseViewLastSync['domains_updated'] ?> updated)
                                 </div>
                             </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Domain Statistics Cards -->
                 <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                     <div class="bg-primary-600 text-white p-4 rounded-xl">
                         <div class="flex items-center justify-between mb-3">
                             <h3 class="text-sm font-medium text-primary-100">Total Domains</h3>
                             <div class="w-6 h-6 bg-primary-500 rounded-full flex items-center justify-center">
                                 <i data-lucide="globe" class="w-3 h-3"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold"><?= $databaseViewDomainStats['total_domains'] ?? 0 ?></div>
                     </div>

                     <div class="bg-white p-4 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-3">
                             <h3 class="text-sm font-medium text-gray-500">Active Domains</h3>
                             <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="check-circle" class="w-3 h-3 text-green-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900"><?= $databaseViewDomainStats['active_domains'] ?? 0 ?></div>
                     </div>

                     <div class="bg-white p-4 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-3">
                             <h3 class="text-sm font-medium text-gray-500">Expired Domains</h3>
                             <div class="w-6 h-6 bg-red-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="alert-triangle" class="w-3 h-3 text-red-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900"><?= $databaseViewDomainStats['expired_domains'] ?? 0 ?></div>
                     </div>

                     <div class="bg-white p-4 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-3">
                             <h3 class="text-sm font-medium text-gray-500">Pending Domains</h3>
                             <div class="w-6 h-6 bg-yellow-100 rounded-full flex items-center justify-center">
                                 <i data-lucide="clock" class="w-3 h-3 text-yellow-600"></i>
                             </div>
                         </div>
                         <div class="text-4xl font-bold text-gray-900"><?= $databaseViewDomainStats['pending_domains'] ?? 0 ?></div>
                     </div>
                 </div>

                 <!-- Info Message -->
                 <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800">
                     <div class="flex items-center space-x-3">
                         <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
                         <div>
                             <div class="font-semibold">Local Database Data</div>
                             <div class="text-sm mt-1">The domain counts and data shown on this page are from your local database. To sync the latest data from WHMCS, use the "Sync Data" feature.</div>
                         </div>
                     </div>
                 </div>

                 <!-- Search and Filter Controls -->
                 <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                     <form method="GET" class="space-y-4">
                         <input type="hidden" name="view" value="database_view">
                         
                         <!-- Search Row -->
                         <div class="flex flex-col sm:flex-row items-end gap-4">
                             <div class="flex-1 min-w-0">
                                 <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Domains</label>
                                 <input 
                                     type="text" 
                                     name="search" 
                                     id="search" 
                                     value="<?= htmlspecialchars($dbSearch ?? '') ?>"
                                     placeholder="Enter domain name..."
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                 >
                             </div>
                             <div class="flex flex-wrap gap-2">
                                 <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 h-10 text-sm">
                                     <i data-lucide="search" class="w-4 h-4"></i>
                                     <span>Search</span>
                                 </button>
                                 <a href="?view=database_view" class="bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 px-3 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 h-10 text-sm">
                                     <i data-lucide="x-circle" class="w-4 h-4"></i>
                                     <span>Clear Filters</span>
                                 </a>
                             </div>
                         </div>
                         
                         <!-- Filters Row -->
                         <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                             <div>
                                 <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status Filter</label>
                                 <select name="status" id="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                     <option value="">All Statuses</option>
                                     <option value="Active" <?= ($dbStatus ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                                     <option value="Pending" <?= ($dbStatus ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                     <option value="Expired" <?= ($dbStatus ?? '') === 'Expired' ? 'selected' : '' ?>>Expired</option>
                                     <option value="Suspended" <?= ($dbStatus ?? '') === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                                     <option value="Cancelled" <?= ($dbStatus ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                     <option value="Transferred Away" <?= ($dbStatus ?? '') === 'Transferred Away' ? 'selected' : '' ?>>Transferred Away</option>
                                 </select>
                             </div>
                             
                             <div>
                                 <label for="registrar" class="block text-sm font-medium text-gray-700 mb-2">Registrar Filter</label>
                                 <select name="registrar" id="registrar" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                     <option value="">All Registrars</option>
                                     <?php if (isset($databaseViewUniqueRegistrars) && !empty($databaseViewUniqueRegistrars)): ?>
                                         <?php foreach ($databaseViewUniqueRegistrars as $registrar): ?>
                                             <option value="<?= htmlspecialchars($registrar) ?>" <?= ($dbRegistrar ?? '') === $registrar ? 'selected' : '' ?>><?= htmlspecialchars($registrar) ?></option>
                                         <?php endforeach; ?>
                                     <?php endif; ?>
                                 </select>
                             </div>
                             
                             <div>
                                 <label for="order_by" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                                 <select name="order_by" id="order_by" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                     <option value="domain_name" <?= ($dbOrderBy ?? '') === 'domain_name' ? 'selected' : '' ?>>Domain Name</option>
                                     <option value="status" <?= ($dbOrderBy ?? '') === 'status' ? 'selected' : '' ?>>Status</option>
                                     <option value="registrar" <?= ($dbOrderBy ?? '') === 'registrar' ? 'selected' : '' ?>>Registrar</option>
                                     <option value="expiry_date" <?= ($dbOrderBy ?? '') === 'expiry_date' ? 'selected' : '' ?>>Expiry Date</option>
                                     <option value="last_synced" <?= ($dbOrderBy ?? '') === 'last_synced' ? 'selected' : '' ?>>Last Synced</option>
                                 </select>
                             </div>
                             
                             <div>
                                 <label for="order_dir" class="block text-sm font-medium text-gray-700 mb-2">Sort Direction</label>
                                 <select name="order_dir" id="order_dir" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                     <option value="ASC" <?= ($dbOrderDir ?? '') === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                     <option value="DESC" <?= ($dbOrderDir ?? '') === 'DESC' ? 'selected' : '' ?>>Descending</option>
                                 </select>
                             </div>
                         </div>
                     </form>
                 </div>

                 <!-- Domains Table -->
                 <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                     <div class="px-6 py-4 border-b border-gray-200">
                         <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                             <h3 class="text-lg font-semibold text-gray-900">Domains (<?= $databaseViewTotalDomains ?> total)</h3>
                             <div class="flex flex-wrap items-center gap-2">
                                 <button type="button" id="addDomainBtn" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 text-sm">
                                     <i data-lucide="plus" class="w-4 h-4"></i>
                                     <span>Add Domain</span>
                                 </button>
                                 <a href="?view=export" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 text-sm">
                                     <i data-lucide="download" class="w-4 h-4"></i>
                                     <span>Export CSV</span>
                                 </a>
                                 <a href="?view=sync" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 text-sm">
                                     <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                     <span>Sync Data</span>
                                 </a>
                             </div>
                         </div>
                     </div>
                     
                     <?php if (!empty($databaseViewDomains)): ?>
                         <div class="overflow-x-auto max-w-full">
                             <table class="w-full divide-y divide-gray-200">
                                 <thead class="bg-gray-50">
                                     <tr>
                                         <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Domain Name</th>
                                         <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Registrar</th>
                                         <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/8">Status</th>
                                         <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Expiry Date</th>
                                         <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Nameservers</th>
                                         <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Last Synced</th>
                                         <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/8">Actions</th>
                                     </tr>
                                 </thead>
                                 <tbody class="bg-white divide-y divide-gray-200">
                                     <?php foreach ($databaseViewDomains as $domain): ?>
                                         <tr class="hover:bg-gray-50">
                                             <td class="px-3 py-4">
                                                 <div class="flex items-center">
                                                     <div class="w-6 h-6 bg-primary-100 rounded-lg flex items-center justify-center mr-2 flex-shrink-0">
                                                         <i data-lucide="globe" class="w-3 h-3 text-primary-600"></i>
                                                     </div>
                                                     <div class="text-sm font-medium text-gray-900 truncate">
                                                         <?= htmlspecialchars($domain['domain_name']) ?>
                                                     </div>
                                                 </div>
                                             </td>
                                             <td class="px-3 py-4">
                                                 <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 truncate max-w-full" title="<?= htmlspecialchars($domain['registrar'] ?? 'Unknown') ?>">
                                                     <?= htmlspecialchars($domain['registrar'] ?? 'Unknown') ?>
                                                 </span>
                                             </td>
                                             <td class="px-3 py-4">
                                                 <?php
                                                 $status = $domain['status'] ?? 'Unknown';
                                                 $statusColors = [
                                                     'Active' => 'bg-green-100 text-green-800',
                                                     'Pending' => 'bg-yellow-100 text-yellow-800',
                                                     'Suspended' => 'bg-red-100 text-red-800',
                                                     'Cancelled' => 'bg-gray-100 text-gray-800',
                                                     'Expired' => 'bg-red-100 text-red-800'
                                                 ];
                                                 $statusColor = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                                 ?>
                                                 <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusColor ?>">
                                                     <?= htmlspecialchars($status) ?>
                                                 </span>
                                             </td>
                                             <td class="px-3 py-4 text-xs text-gray-900">
                                                 <?php 
                                                 if (!empty($domain['expiry_date'])) {
                                                     echo date('M j, Y', strtotime($domain['expiry_date']));
                                                 } else {
                                                     echo 'N/A';
                                                 }
                                                 ?>
                                             </td>
                                             <td class="px-3 py-4 text-sm text-gray-900">
                                                 <?php if (!empty($domain['ns1'])): ?>
                                                     <div class="text-xs">
                                                         <div class="truncate"><?= htmlspecialchars($domain['ns1']) ?></div>
                                                         <?php if (!empty($domain['ns2'])): ?>
                                                             <div class="text-gray-500 truncate"><?= htmlspecialchars($domain['ns2']) ?></div>
                                                         <?php endif; ?>
                                                     </div>
                                                 <?php else: ?>
                                                     <span class="text-gray-400">Not available</span>
                                                 <?php endif; ?>
                                             </td>
                                             <td class="px-3 py-4 text-xs text-gray-500">
                                                 <?= date('M j, Y g:i A', strtotime($domain['last_synced'])) ?>
                                             </td>
                                             <td class="px-3 py-4 text-sm text-gray-500">
                                                 <div class="flex items-center space-x-1">
                                                     <button type="button" 
                                                             class="edit-domain-btn inline-flex items-center px-1.5 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                                             data-domain-id="<?= htmlspecialchars($domain['domain_id'] ?? '') ?>"
                                                             data-domain-name="<?= htmlspecialchars($domain['domain_name']) ?>"
                                                             data-registrar="<?= htmlspecialchars($domain['registrar'] ?? 'Unknown') ?>"
                                                             data-status="<?= htmlspecialchars($domain['status'] ?? 'Unknown') ?>"
                                                             data-expiry-date="<?= htmlspecialchars($domain['expiry_date'] ?? '') ?>"
                                                             data-nameservers="<?= htmlspecialchars(($domain['ns1'] ?? '') . ($domain['ns2'] ? ', ' . $domain['ns2'] : '') . ($domain['ns3'] ? ', ' . $domain['ns3'] : '') . ($domain['ns4'] ? ', ' . $domain['ns4'] : '') . ($domain['ns5'] ? ', ' . $domain['ns5'] : '')) ?>">
                                                         <i data-lucide="edit-3" class="w-3 h-3 mr-1"></i>
                                                         Edit
                                                     </button>
                                                     <button type="button" 
                                                             class="delete-domain-btn inline-flex items-center px-1.5 py-1 text-xs font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors"
                                                             data-domain-id="<?= htmlspecialchars($domain['domain_id'] ?? '') ?>"
                                                             data-domain-name="<?= htmlspecialchars($domain['domain_name']) ?>">
                                                         <i data-lucide="trash-2" class="w-3 h-3 mr-1"></i>
                                                         Delete
                                                     </button>
                                                 </div>
                                             </td>
                                         </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                         </div>
                         
                         <!-- Pagination -->
                         <?php 
                         $dbTotalPages = ceil($databaseViewTotalDomains / $dbPerPage);
                         $dbOffset = ($dbPage - 1) * $dbPerPage;
                         ?>
                         <?php if ($dbTotalPages > 1): ?>
                             <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                                 <div class="flex items-center justify-between">
                                     <div class="text-sm text-gray-700">
                                         Showing <?= $dbOffset + 1 ?> to <?= min($dbOffset + $dbPerPage, $databaseViewTotalDomains) ?> of <?= $databaseViewTotalDomains ?> domains
                                     </div>
                                     <div class="flex items-center space-x-2">
                                         <!-- Previous Page -->
                                         <?php if ($dbPage > 1): ?>
                                             <a href="?view=database_view&page=<?= $dbPage - 1 ?>&search=<?= urlencode($dbSearch ?? '') ?>&status=<?= urlencode($dbStatus ?? '') ?>&registrar=<?= urlencode($dbRegistrar ?? '') ?>&order_by=<?= urlencode($dbOrderBy ?? '') ?>&order_dir=<?= urlencode($dbOrderDir ?? '') ?>" 
                                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                 Previous
                                             </a>
                                         <?php else: ?>
                                             <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                                                 Previous
                                             </span>
                                         <?php endif; ?>
                                         
                                         <!-- Page Numbers -->
                                         <?php
                                         // Always show first page
                                         if ($dbPage == 1): ?>
                                             <span class="px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-primary-600 rounded-lg">1</span>
                                         <?php else: ?>
                                             <a href="?view=database_view&page=1&search=<?= urlencode($dbSearch ?? '') ?>&status=<?= urlencode($dbStatus ?? '') ?>&registrar=<?= urlencode($dbRegistrar ?? '') ?>&order_by=<?= urlencode($dbOrderBy ?? '') ?>&order_dir=<?= urlencode($dbOrderDir ?? '') ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">1</a>
                                         <?php endif; ?>

                                         <?php if ($dbPage > 4): ?>
                                             <span class="px-3 py-2 text-sm font-medium text-gray-400">...</span>
                                         <?php endif; ?>

                                         <?php
                                         // Show 2 pages before and after current page
                                         for ($i = max(2, $dbPage - 2); $i <= min($dbTotalPages - 1, $dbPage + 2); $i++):
                                             if ($i == 1 || $i == $dbTotalPages) continue;
                                         ?>
                                             <?php if ($i == $dbPage): ?>
                                                 <span class="px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-primary-600 rounded-lg"><?= $i ?></span>
                                             <?php else: ?>
                                                 <a href="?view=database_view&page=<?= $i ?>&search=<?= urlencode($dbSearch ?? '') ?>&status=<?= urlencode($dbStatus ?? '') ?>&registrar=<?= urlencode($dbRegistrar ?? '') ?>&order_by=<?= urlencode($dbOrderBy ?? '') ?>&order_dir=<?= urlencode($dbOrderDir ?? '') ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors"><?= $i ?></a>
                                             <?php endif; ?>
                                         <?php endfor; ?>

                                         <?php if ($dbPage < $dbTotalPages - 3): ?>
                                             <span class="px-3 py-2 text-sm font-medium text-gray-400">...</span>
                                         <?php endif; ?>

                                         <?php if ($dbTotalPages > 1): ?>
                                             <?php if ($dbPage == $dbTotalPages): ?>
                                                 <span class="px-3 py-2 text-sm font-medium text-white bg-primary-600 border border-primary-600 rounded-lg"><?= $dbTotalPages ?></span>
                                             <?php else: ?>
                                                 <a href="?view=database_view&page=<?= $dbTotalPages ?>&search=<?= urlencode($dbSearch ?? '') ?>&status=<?= urlencode($dbStatus ?? '') ?>&registrar=<?= urlencode($dbRegistrar ?? '') ?>&order_by=<?= urlencode($dbOrderBy ?? '') ?>&order_dir=<?= urlencode($dbOrderDir ?? '') ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors"><?= $dbTotalPages ?></a>
                                             <?php endif; ?>
                                         <?php endif; ?>

                                         <!-- Next Page -->
                                         <?php if ($dbPage < $dbTotalPages): ?>
                                             <a href="?view=database_view&page=<?= $dbPage + 1 ?>&search=<?= urlencode($dbSearch ?? '') ?>&status=<?= urlencode($dbStatus ?? '') ?>&registrar=<?= urlencode($dbRegistrar ?? '') ?>&order_by=<?= urlencode($dbOrderBy ?? '') ?>&order_dir=<?= urlencode($dbOrderDir ?? '') ?>" 
                                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                 Next
                                             </a>
                                         <?php else: ?>
                                             <span class="px-3 py-2 text-sm font-medium text-gray-300 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                                                 Next
                                             </span>
                                         <?php endif; ?>
                                     </div>
                                 </div>
                             </div>
                         <?php endif; ?>
                     <?php else: ?>
                         <div class="px-6 py-12 text-center">
                             <div class="text-gray-400 mb-4">
                                 <i data-lucide="database" class="w-12 h-12 mx-auto"></i>
                             </div>
                             <h3 class="text-lg font-medium text-gray-900 mb-2">No domains found</h3>
                             <p class="text-gray-500 mb-4">
                                 <?php if (!empty($dbSearch) || !empty($dbStatus)): ?>
                                     No domains match your current filters. Try adjusting your search criteria.
                                 <?php else: ?>
                                     No domains have been synced to the database yet.
                                 <?php endif; ?>
                             </p>
                             <?php if (empty($dbSearch) && empty($dbStatus)): ?>
                                 <a href="domain_sync.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 inline-flex">
                                     <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                     <span>Sync Domains</span>
                                 </a>
                             <?php else: ?>
                                 <a href="?view=database_view" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2 inline-flex">
                                     <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                     <span>Clear Filters</span>
                                 </a>
                             <?php endif; ?>
                         </div>
                     <?php endif; ?>
                 </div>
                 
                 <?php elseif ($currentView === 'sync'): ?>
                 <!-- Sync View Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">🔄 Domain Sync Interface</h1>
                     <p class="text-gray-600">Sync domain data from WHMCS API to local database</p>
                 </div>

                 <!-- Error Message -->
                 <?php if ($syncViewError): ?>
                     <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                             <div>
                                 <div class="font-semibold">Configuration Error</div>
                                 <div class="text-sm mt-1"><?= htmlspecialchars($syncViewError) ?></div>
                             </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Sync Status -->
                 <?php if ($syncViewLastSync): ?>
                     <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="refresh-cw" class="w-5 h-5 text-blue-600"></i>
                             <div>
                                 <div class="font-semibold">Last Sync: <?= date('M j, Y g:i A', strtotime($syncViewLastSync['sync_started'])) ?></div>
                                 <div class="text-sm mt-1">
                                     Batch <?= $syncViewLastSync['batch_number'] ?> - 
                                     <?= $syncViewLastSync['domains_processed'] ?> domains processed
                                     (<?= $syncViewLastSync['domains_added'] ?> added, <?= $syncViewLastSync['domains_updated'] ?> updated)
                                 </div>
                             </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Main Card -->
                 <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                     <div class="px-6 py-4 border-b border-gray-200">
                         <h2 class="text-lg font-semibold text-gray-900">Sync Controls</h2>
                     </div>
                     <div class="p-6">
                         <!-- Sync Configuration -->
                         <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                             <h3 class="text-lg font-semibold text-gray-900 mb-4">Sync Configuration</h3>
                             
                             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                 <div>
                                     <label for="batch_number" class="block text-sm font-medium text-gray-700 mb-2">Batch Number</label>
                                     <input 
                                         type="number" 
                                         name="batch_number" 
                                         id="batch_number" 
                                         class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                         min="1" 
                                         value="1"
                                         required
                                     >
                                     <div class="text-xs text-gray-500 mt-1">Specify which batch of domains to sync (10 domains per batch)</div>
                                 </div>
                                 
                                 <div>
                                     <label for="batch_size" class="block text-sm font-medium text-gray-700 mb-2">Batch Size</label>
                                     <input 
                                         type="number" 
                                         name="batch_size" 
                                         id="batch_size" 
                                         class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                         min="5" 
                                         max="50"
                                         value="10"
                                         required
                                     >
                                     <div class="text-xs text-gray-500 mt-1">Number of domains to fetch per API call (5-50, recommended: 10)</div>
                                 </div>
                             </div>
                             
                             <div class="mt-6">
                                 <button type="button" id="startSync" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                     <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
                                     Start Sync
                                 </button>
                                 <button type="button" id="stopSync" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors ml-4" style="display: none;">
                                     <i data-lucide="square" class="w-4 h-4 mr-2"></i>
                                     Stop Sync
                                 </button>
                             </div>
                         </div>

                         <!-- Sync Progress -->
                         <div id="syncProgress" class="bg-white rounded-xl border border-gray-200 p-6 mb-6" style="display: none;">
                             <h3 class="text-lg font-semibold text-gray-900 mb-4">Sync Progress</h3>
                             
                             <!-- Progress Bar -->
                             <div class="mb-6">
                                 <div class="flex items-center justify-between mb-2">
                                     <span class="text-sm font-medium text-gray-700">Sync Progress</span>
                                     <span id="progressPercentage" class="text-sm font-medium text-gray-900">0%</span>
                                 </div>
                                 <div class="w-full bg-gray-200 rounded-full h-3">
                                     <div id="progressBar" class="bg-gradient-to-r from-blue-500 to-green-500 h-3 rounded-full transition-all duration-300 ease-out" style="width: 0%"></div>
                                 </div>
                             </div>
                             
                             <!-- Current Domain Status -->
                             <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                 <div class="flex items-center justify-between mb-2">
                                     <span class="text-sm font-medium text-blue-700">Current Domain:</span>
                                     <span id="currentDomain" class="text-sm font-medium text-blue-900">-</span>
                                 </div>
                                 <div class="flex items-center justify-between">
                                     <span class="text-sm font-medium text-blue-700">Status:</span>
                                     <span id="currentStatus" class="text-sm font-medium text-blue-600">Waiting...</span>
                                 </div>
                             </div>
                             
                             <!-- Statistics Grid -->
                             <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                 <div class="bg-gray-50 rounded-lg p-3">
                                     <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Domains Found</div>
                                     <div id="domainsFound" class="text-lg font-semibold text-gray-900">0</div>
                                 </div>
                                 
                                 <div class="bg-gray-50 rounded-lg p-3">
                                     <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Processed</div>
                                     <div id="domainsProcessed" class="text-lg font-semibold text-gray-900">0</div>
                                 </div>
                                 
                                 <div class="bg-green-50 rounded-lg p-3">
                                     <div class="text-xs font-medium text-green-500 uppercase tracking-wide">Added</div>
                                     <div id="domainsAdded" class="text-lg font-semibold text-green-600">0</div>
                                 </div>
                                 
                                 <div class="bg-blue-50 rounded-lg p-3">
                                     <div class="text-xs font-medium text-blue-500 uppercase tracking-wide">Updated</div>
                                     <div id="domainsUpdated" class="text-lg font-semibold text-blue-600">0</div>
                                 </div>
                             </div>
                             
                             <!-- Error Counter -->
                             <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200">
                                 <span class="text-sm font-medium text-red-700">Errors:</span>
                                 <span id="syncErrors" class="text-sm font-medium text-red-600">0</span>
                             </div>
                         </div>

                         <!-- Sync Log -->
                         <div id="syncLog" class="bg-white rounded-xl border border-gray-200 p-6 mb-6" style="display: none;">
                             <h3 class="text-lg font-semibold text-gray-900 mb-4">Sync Log</h3>
                             <div id="logContent" class="bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto text-sm font-mono">
                                 <!-- Log messages will be added here -->
                             </div>
                         </div>

                         <!-- Action Buttons -->
                         <div class="flex items-center space-x-4">
                             <a href="?view=database_view" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                                 <i data-lucide="database" class="w-4 h-4 mr-2"></i>
                                 View Database
                             </a>
                             <a href="?view=export" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                                 <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                                 Export CSV
                             </a>
                             <a href="?view=debug" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                 <i data-lucide="wrench" class="w-4 h-4 mr-2"></i>
                                 Debug Tools
                             </a>
                             <button type="button" id="clearOldData" class="inline-flex items-center px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                                 <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                                 Clear Old Data
                             </button>
                         </div>
                     </div>
                 </div>
                 
                 <?php elseif ($currentView === 'create_tables'): ?>
                 <?php if (!isServerAdmin()): ?>
                     <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600"></i>
                             <div>
                                 <h3 class="text-lg font-semibold text-red-900">Access Denied</h3>
                                 <p class="text-red-800 mt-1">You don't have permission to access Create Tables. Only server administrators can create database tables.</p>
                             </div>
                         </div>
                     </div>
                 <?php else: ?>
                 <!-- Create Tables Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <div class="flex items-center justify-between">
                         <div>
                             <h1 class="text-2xl font-bold text-gray-900 mb-2">🗄️ Create Database Tables</h1>
                             <p class="text-gray-600">Set up the required database tables for domain management</p>
                         </div>
                         <?php if ($setupStatus['tables_created']): ?>
                             <div class="flex items-center space-x-2 text-green-600 bg-green-50 px-4 py-2 rounded-lg">
                                 <i data-lucide="check-circle" class="w-5 h-5"></i>
                                 <span class="font-medium">Tables Created</span>
                             </div>
                         <?php endif; ?>
                     </div>
                 </div>

                 <!-- Messages -->
                 <?php if ($createTablesMessage): ?>
                     <div class="mb-6 p-4 rounded-lg <?= $createTablesMessageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
                         <div class="flex items-center space-x-3">
                             <span style="font-size: 1.5rem;">
                                 <?= $createTablesMessageType === 'success' ? '✅' : '❌' ?>
                             </span>
                             <div>
                                 <div class="font-semibold">
                                     <?= $createTablesMessageType === 'success' ? 'Success' : 'Error' ?>
                                 </div>
                                 <div class="text-sm mt-1"><?= htmlspecialchars($createTablesMessage) ?></div>
                             </div>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Information Section -->
                 <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
                     <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="info" class="w-5 h-5 text-blue-600"></i>
                         <span>Database Tables</span>
                     </h3>
                     
                     <div class="space-y-3 text-sm text-blue-800">
                         <div class="flex items-start gap-2">
                             <i data-lucide="database" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                             <div><strong>domains</strong> - Main domain information storage</div>
                         </div>
                         <div class="flex items-start gap-2">
                             <i data-lucide="server" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                             <div><strong>domain_nameservers</strong> - Nameserver configuration for domains</div>
                         </div>
                         <div class="flex items-start gap-2">
                             <i data-lucide="activity" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                             <div><strong>sync_logs</strong> - Sync operation history and statistics</div>
                         </div>
                         <div class="flex items-start gap-2">
                             <i data-lucide="settings" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                             <div><strong>user_settings</strong> - User API credentials and preferences</div>
                         </div>
                     </div>
                 </div>

                 <!-- Next Steps -->
                 <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-6">
                     <h3 class="text-lg font-semibold text-green-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="arrow-right" class="w-5 h-5 text-green-600"></i>
                         <span>Next Steps</span>
                     </h3>
                     
                     <div class="space-y-3">
                         <div class="flex items-center gap-3">
                             <i data-lucide="arrow-right" class="w-4 h-4 text-green-600"></i>
                             <a href="sync_interface.php" class="text-green-800 hover:text-green-900 font-medium">
                                 Sync Interface - Import domain data from WHMCS
                             </a>
                         </div>
                         <div class="flex items-center gap-3">
                             <i data-lucide="arrow-right" class="w-4 h-4 text-green-600"></i>
                             <a href="?view=domains" class="text-green-800 hover:text-green-900 font-medium">
                                 View Database - See imported domains
                             </a>
                         </div>
                         <div class="flex items-center gap-3">
                             <i data-lucide="arrow-right" class="w-4 h-4 text-green-600"></i>
                             <a href="test_db_connection.php" class="text-green-800 hover:text-green-900 font-medium">
                                 Test Database Connection - Verify setup
                             </a>
                         </div>
                     </div>
                 </div>

                 <!-- Action Buttons -->
                 <div class="flex items-center space-x-4">
                     <a href="?view=dashboard" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                         <i data-lucide="home" class="w-4 h-4"></i>
                         <span>Go to Dashboard</span>
                     </a>
                     <a href="sync_interface.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                         <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                         <span>Start Domain Sync</span>
                     </a>
                 </div>
                 <?php endif; ?>
                 
                 <?php elseif ($currentView === 'debug'): ?>
                 <?php if (!isServerAdmin()): ?>
                     <div class="bg-red-50 border border-red-200 rounded-xl p-6">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="alert-triangle" class="w-6 h-6 text-red-600"></i>
                             <div>
                                 <h3 class="text-lg font-semibold text-red-900">Access Denied</h3>
                                 <p class="text-red-800 mt-1">You don't have permission to access Debug Settings. Only server administrators can view debug information.</p>
                             </div>
                         </div>
                     </div>
                 <?php else: ?>
                 <!-- Debug Settings Content -->
                 <?php
                 // Generate debug information
                 $debugInfo = [];
                 
                 // Get session information
                 $debugInfo['session'] = [
                     'logged_in' => $_SESSION['logged_in'] ?? false,
                     'user_email' => $_SESSION['user_email'] ?? 'Not set',
                     'firebase_token' => isset($_SESSION['firebase_token']) ? 'Present' : 'Not set',
                     'session_id' => session_id(),
                     'session_save_path' => session_save_path(),
                 ];
                 
                 // Get user settings information
                 $debugInfo['settings'] = [
                     'has_settings' => userHasSettingsDB(),
                     'settings_validation' => validateSettingsCompletenessDB(),
                     'current_settings' => getUserSettingsDB()
                 ];
                 
                 // Get file system information
                 $debugInfo['filesystem'] = [
                     'user_settings_dir_exists' => is_dir('user_settings'),
                     'user_settings_dir_writable' => is_writable('user_settings'),
                     'settings_file' => null,
                     'settings_file_exists' => false,
                     'settings_file_readable' => false
                 ];
                 
                 if (isset($_SESSION['user_email']) && isset($_SESSION['company_id'])) {
                     $userSettings = new UserSettingsDB();
                     $debugInfo['filesystem']['settings_file'] = 'Database (user_settings table)';
                     $debugInfo['filesystem']['settings_file_exists'] = $userSettings->hasSettings($_SESSION['company_id'], $_SESSION['user_email']);
                     $debugInfo['filesystem']['settings_file_readable'] = $userSettings->hasSettings($_SESSION['company_id'], $_SESSION['user_email']);
                 }
                 
                 // Get environment information
                 $debugInfo['environment'] = [
                     'php_version' => phpversion(),
                     'session_module_name' => session_module_name(),
                     'encryption_key_defined' => defined('ENCRYPTION_KEY'),
                     'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown'
                 ];
                 ?>
                 
                 <!-- Page Header -->
                 <div class="mb-8">
                     <div class="flex items-center justify-between">
                         <div>
                             <h1 class="text-2xl font-bold text-gray-900 mb-2">🔍 Debug Tools</h1>
                             <p class="text-gray-600">Diagnostic information for troubleshooting settings persistence</p>
                         </div>
                         <div class="flex items-center space-x-2 text-blue-600 bg-blue-50 px-4 py-2 rounded-lg">
                             <i data-lucide="activity" class="w-5 h-5"></i>
                             <span class="font-medium">System Diagnostics</span>
                         </div>
                     </div>
                 </div>

                 <!-- Overall Status Cards -->
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-lg font-semibold text-gray-900">Authentication Status</h3>
                             <div class="w-12 h-12 <?= $debugInfo['session']['logged_in'] ? 'bg-green-100' : 'bg-red-100' ?> rounded-full flex items-center justify-center">
                                 <span class="text-2xl"><?= $debugInfo['session']['logged_in'] ? '✅' : '❌' ?></span>
                             </div>
                         </div>
                         <p class="text-gray-600 mb-2"><?= $debugInfo['session']['logged_in'] ? 'User is properly logged in' : 'Authentication issue detected' ?></p>
                         <div class="text-sm text-gray-500">
                             User: <?= htmlspecialchars($debugInfo['session']['user_email']) ?>
                         </div>
                     </div>
                     
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-lg font-semibold text-gray-900">Settings Status</h3>
                             <div class="w-12 h-12 <?= $debugInfo['settings']['has_settings'] ? 'bg-green-100' : 'bg-yellow-100' ?> rounded-full flex items-center justify-center">
                                 <span class="text-2xl"><?= $debugInfo['settings']['has_settings'] ? '✅' : '⚠️' ?></span>
                             </div>
                         </div>
                         <p class="text-gray-600 mb-2"><?= $debugInfo['settings']['has_settings'] ? 'Settings are configured and saved' : 'No settings configured yet' ?></p>
                         <div class="text-sm text-gray-500">
                             <?= empty($debugInfo['settings']['settings_validation']['missing']) ? 'All required fields complete' : count($debugInfo['settings']['settings_validation']['missing']) . ' missing fields' ?>
                         </div>
                     </div>
                 </div>

                 <!-- Session Information -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="shield-check" class="w-5 h-5 text-primary-600"></i>
                         <span>Session Information</span>
                     </h3>
                     
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div>
                             <h4 class="font-medium text-gray-900 mb-3">Session Data</h4>
                             <div class="space-y-2 text-sm">
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Logged In:</span>
                                     <span class="font-medium <?= $debugInfo['session']['logged_in'] ? 'text-green-600' : 'text-red-600' ?>">
                                         <?= $debugInfo['session']['logged_in'] ? 'Yes' : 'No' ?>
                                     </span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">User Email:</span>
                                     <span class="font-medium"><?= htmlspecialchars($debugInfo['session']['user_email']) ?></span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Firebase Token:</span>
                                     <span class="font-medium"><?= $debugInfo['session']['firebase_token'] ?></span>
                                 </div>
                             </div>
                         </div>
                         
                         <div>
                             <h4 class="font-medium text-gray-900 mb-3">Session Configuration</h4>
                             <div class="space-y-2 text-sm">
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Session ID:</span>
                                     <span class="font-mono text-xs"><?= htmlspecialchars($debugInfo['session']['session_id']) ?></span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Save Path:</span>
                                     <span class="font-mono text-xs"><?= htmlspecialchars($debugInfo['session']['session_save_path']) ?></span>
                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>

                 <!-- Settings Information -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="settings" class="w-5 h-5 text-primary-600"></i>
                         <span>Settings Information</span>
                     </h3>
                     
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div>
                             <h4 class="font-medium text-gray-900 mb-3">Settings Status</h4>
                             <div class="space-y-2 text-sm">
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Has Settings:</span>
                                     <span class="font-medium <?= $debugInfo['settings']['has_settings'] ? 'text-green-600' : 'text-red-600' ?>">
                                         <?= $debugInfo['settings']['has_settings'] ? 'Yes' : 'No' ?>
                                     </span>
                                 </div>
                                 <?php if (!empty($debugInfo['settings']['settings_validation']['missing'])): ?>
                                 <div class="mt-3">
                                     <span class="text-gray-600 block mb-2">Missing Fields:</span>
                                     <div class="space-y-1">
                                         <?php foreach ($debugInfo['settings']['settings_validation']['missing'] as $missing): ?>
                                         <div class="flex items-center space-x-2 text-red-600">
                                             <span class="text-red-500">•</span>
                                             <span><?= htmlspecialchars($missing) ?></span>
                                         </div>
                                         <?php endforeach; ?>
                                     </div>
                                 </div>
                                 <?php endif; ?>
                             </div>
                         </div>
                         
                         <div>
                             <h4 class="font-medium text-gray-900 mb-3">Current Settings (Masked)</h4>
                             <?php 
                             $maskedSettings = $debugInfo['settings']['current_settings'];
                             if ($maskedSettings) {
                                 $maskedSettings['api_identifier'] = $maskedSettings['api_identifier'] ? str_repeat('*', 8) . substr($maskedSettings['api_identifier'], -4) : 'Not set';
                                 $maskedSettings['api_secret'] = $maskedSettings['api_secret'] ? str_repeat('*', 12) : 'Not set';
                             }
                             ?>
                             <div class="space-y-2 text-sm">
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">API URL:</span>
                                     <span class="font-medium"><?= $maskedSettings ? htmlspecialchars(parse_url($maskedSettings['api_url'], PHP_URL_HOST) ?? 'Not set') : 'Not set' ?></span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">API Identifier:</span>
                                     <span class="font-mono text-xs"><?= $maskedSettings ? htmlspecialchars($maskedSettings['api_identifier']) : 'Not set' ?></span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">API Secret:</span>
                                     <span class="font-mono text-xs"><?= $maskedSettings ? htmlspecialchars($maskedSettings['api_secret']) : 'Not set' ?></span>
                                 </div>
                                 <?php if ($maskedSettings && !empty($maskedSettings['updated_at'])): ?>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Last Updated:</span>
                                     <span class="font-medium"><?= htmlspecialchars($maskedSettings['updated_at']) ?></span>
                                 </div>
                                 <?php endif; ?>
                             </div>
                         </div>
                     </div>
                 </div>

                 <!-- File System Information -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="folder" class="w-5 h-5 text-primary-600"></i>
                         <span>File System Information</span>
                     </h3>
                     
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div>
                             <h4 class="font-medium text-gray-900 mb-3">Directory Status</h4>
                             <div class="space-y-2 text-sm">
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Settings Dir Exists:</span>
                                     <span class="font-medium <?= $debugInfo['filesystem']['user_settings_dir_exists'] ? 'text-green-600' : 'text-red-600' ?>">
                                         <?= $debugInfo['filesystem']['user_settings_dir_exists'] ? 'Yes' : 'No' ?>
                                     </span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Dir Writable:</span>
                                     <span class="font-medium <?= $debugInfo['filesystem']['user_settings_dir_writable'] ? 'text-green-600' : 'text-red-600' ?>">
                                         <?= $debugInfo['filesystem']['user_settings_dir_writable'] ? 'Yes' : 'No' ?>
                                     </span>
                                 </div>
                             </div>
                         </div>
                         
                         <div>
                             <h4 class="font-medium text-gray-900 mb-3">Settings File</h4>
                             <div class="space-y-2 text-sm">
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">File Exists:</span>
                                     <span class="font-medium <?= $debugInfo['filesystem']['settings_file_exists'] ? 'text-green-600' : 'text-red-600' ?>">
                                         <?= $debugInfo['filesystem']['settings_file_exists'] ? 'Yes' : 'No' ?>
                                     </span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">File Readable:</span>
                                     <span class="font-medium <?= $debugInfo['filesystem']['settings_file_readable'] ? 'text-green-600' : 'text-red-600' ?>">
                                         <?= $debugInfo['filesystem']['settings_file_readable'] ? 'Yes' : 'No' ?>
                                     </span>
                                 </div>
                                 <?php if ($debugInfo['filesystem']['settings_file']): ?>
                                 <div class="mt-2">
                                     <span class="text-gray-600 block">File Path:</span>
                                     <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($debugInfo['filesystem']['settings_file']) ?></span>
                                 </div>
                                 <?php endif; ?>
                             </div>
                         </div>
                     </div>
                 </div>

                 <!-- Environment Information -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="server" class="w-5 h-5 text-primary-600"></i>
                         <span>Environment Information</span>
                     </h3>
                     
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div>
                             <h4 class="font-medium text-gray-900 mb-3">System Info</h4>
                             <div class="space-y-2 text-sm">
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">PHP Version:</span>
                                     <span class="font-medium"><?= htmlspecialchars($debugInfo['environment']['php_version']) ?></span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Session Module:</span>
                                     <span class="font-medium"><?= htmlspecialchars($debugInfo['environment']['session_module_name']) ?></span>
                                 </div>
                             </div>
                         </div>
                         
                         <div>
                             <h4 class="font-medium text-gray-900 mb-3">Configuration</h4>
                             <div class="space-y-2 text-sm">
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Encryption Key:</span>
                                     <span class="font-medium <?= $debugInfo['environment']['encryption_key_defined'] ? 'text-green-600' : 'text-red-600' ?>">
                                         <?= $debugInfo['environment']['encryption_key_defined'] ? 'Defined' : 'Not Defined' ?>
                                     </span>
                                 </div>
                                 <div class="flex justify-between">
                                     <span class="text-gray-600">Server Name:</span>
                                     <span class="font-medium"><?= htmlspecialchars($debugInfo['environment']['server_name']) ?></span>
                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>

                 <!-- Debug Tools Section -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="wrench" class="w-5 h-5 text-primary-600"></i>
                         <span>Debug Tools</span>
                     </h3>
                     <p class="text-gray-600 mb-6">Advanced debugging tools for troubleshooting API connections, sync issues, and domain management.</p>
                     
                     <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                         <!-- API Debug Tools -->
                         <div class="space-y-3">
                             <h4 class="font-medium text-gray-900 text-sm uppercase tracking-wider">API Testing</h4>
                             <div class="space-y-2">
                                 <a href="test_api_debug.php" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white font-medium rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="bug" class="w-4 h-4 mr-2"></i>
                                     Debug API
                                 </a>
                                 <a href="debug_html_response.php" class="inline-flex items-center px-4 py-2 bg-orange-600 text-white font-medium rounded-lg hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="search" class="w-4 h-4 mr-2"></i>
                                     HTML Debug
                                 </a>
                                 <a href="test_output_buffering.php" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white font-medium rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="shield" class="w-4 h-4 mr-2"></i>
                                     Output Test
                                 </a>
                             </div>
                         </div>
                         
                         <!-- Sync Debug Tools -->
                         <div class="space-y-3">
                             <h4 class="font-medium text-gray-900 text-sm uppercase tracking-wider">Sync Testing</h4>
                             <div class="space-y-2">
                                 <a href="debug_sync_errors.php" class="inline-flex items-center px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="alert-triangle" class="w-4 h-4 mr-2"></i>
                                     Sync Errors
                                 </a>
                                 <a href="test_small_sync.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="play" class="w-4 h-4 mr-2"></i>
                                     Test Sync
                                 </a>
                                 <a href="add_company_id_to_tables.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="database" class="w-4 h-4 mr-2"></i>
                                     Add Company ID
                                 </a>
                             </div>
                         </div>
                         
                         <!-- Nameserver Debug Tools -->
                         <div class="space-y-3">
                             <h4 class="font-medium text-gray-900 text-sm uppercase tracking-wider">Nameserver Testing</h4>
                             <div class="space-y-2">
                                 <a href="test_nameservers.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="server" class="w-4 h-4 mr-2"></i>
                                     Test Nameservers
                                 </a>
                                 <a href="debug_nameserver_api.php" class="inline-flex items-center px-4 py-2 bg-teal-600 text-white font-medium rounded-lg hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="search" class="w-4 h-4 mr-2"></i>
                                     Debug Nameserver API
                                 </a>
                                 <a href="check_domain_data.php" class="inline-flex items-center px-4 py-2 bg-pink-600 text-white font-medium rounded-lg hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="list" class="w-4 h-4 mr-2"></i>
                                     Check Domain Data
                                 </a>
                             </div>
                         </div>
                         
                         <!-- Advanced Debug Tools -->
                         <div class="space-y-3">
                             <h4 class="font-medium text-gray-900 text-sm uppercase tracking-wider">Advanced Testing</h4>
                             <div class="space-y-2">
                                 <a href="test_nameserver_methods.php" class="inline-flex items-center px-4 py-2 bg-cyan-600 text-white font-medium rounded-lg hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="test-tube" class="w-4 h-4 mr-2"></i>
                                     Test Nameserver Methods
                                 </a>
                                 <a href="test_nameserver_display.php" class="inline-flex items-center px-4 py-2 bg-lime-600 text-white font-medium rounded-lg hover:bg-lime-700 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 transition-colors w-full">
                                     <i data-lucide="eye" class="w-4 h-4 mr-2"></i>
                                     Test Nameserver Display
                                 </a>
                             </div>
                         </div>
                     </div>
                 </div>


                 
                 <?php endif; ?>
                 
                 <?php elseif ($currentView === 'help'): ?>
                 <!-- Help Content -->
                 <div class="mb-8">
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">📚 Help & Documentation</h1>
                     <p class="text-gray-600">Complete setup guide and troubleshooting information for Domain Tools Management Suite.</p>
                 </div>
                 
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-8">
                     <h2 class="text-lg font-semibold text-gray-900 mb-4">Installation & Setup Guide</h2>
                     <div class="space-y-4">
                         <div>
                             <h3 class="font-semibold text-gray-900 mb-2">Step 1: File Upload</h3>
                             <p class="text-sm text-gray-600">Upload all application files to your web server directory. Ensure PHP 7.4+ is installed and the web server has read/write permissions.</p>
                         </div>
                         <div>
                             <h3 class="font-semibold text-gray-900 mb-2">Step 2: Database Setup</h3>
                             <p class="text-sm text-gray-600">Create a new MySQL/MariaDB database and note down the connection details (host, port, name, username, password).</p>
                         </div>
                         <div>
                             <h3 class="font-semibold text-gray-900 mb-2">Step 3: Configure Settings</h3>
                             <p class="text-sm text-gray-600">Go to Settings → Database Configuration and enter your database details. Test the connection and save settings.</p>
                         </div>
                         <div>
                             <h3 class="font-semibold text-gray-900 mb-2">Step 4: Create Tables</h3>
                             <p class="text-sm text-gray-600">In Settings, use the "Create Tables" button to set up the required database tables for the application.</p>
                         </div>
                         <div>
                             <h3 class="font-semibold text-gray-900 mb-2">Step 5: WHMCS API Setup</h3>
                             <p class="text-sm text-gray-600">Configure your WHMCS API credentials in Settings. Create API identifier and secret in WHMCS admin panel.</p>
                         </div>
                     </div>
                 </div>
                 
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-8">
                     <h2 class="text-lg font-semibold text-gray-900 mb-4">Troubleshooting</h2>
                     <div class="space-y-4">
                         <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                             <h3 class="font-semibold text-red-900 mb-2">Database Issues</h3>
                             <p class="text-sm text-red-800">Verify database credentials, ensure server is running, check user permissions.</p>
                         </div>
                         <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                             <h3 class="font-semibold text-yellow-900 mb-2">API Issues</h3>
                             <p class="text-sm text-yellow-800">Check WHMCS API credentials, verify API URL accessibility, ensure API access is enabled.</p>
                         </div>
                         <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                             <h3 class="font-semibold text-blue-900 mb-2">Debug Tools</h3>
                             <p class="text-sm text-blue-800">Use Settings → Debug Tools (admin only) to diagnose issues. Test database connection, API access, and nameserver functionality.</p>
                         </div>
                     </div>
                 </div>
                 
                 <?php endif; ?>
                 
                 <!-- Contact Developer Section -->
                 <div class="mt-12 bg-gray-50 border border-gray-200 rounded-xl p-6">
                     <div class="text-center">
                         <h3 class="text-lg font-semibold text-gray-900 mb-2">Need Help?</h3>
                         <p class="text-gray-600 mb-4">If you're experiencing issues or have questions about this application, please don't hesitate to contact the developer.</p>
                         <div class="flex flex-col items-center space-y-3">
                             <div class="flex items-center justify-center space-x-2">
                                 <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                                 <a href="mailto:guilio@kaldera.co.za" class="text-primary-600 hover:text-primary-700 font-medium transition-colors">
                                     guilio@kaldera.co.za
                                 </a>
                             </div>
                             <?php if (isServerAdmin()): ?>
                             <div class="flex items-center justify-center space-x-2">
                                 <i data-lucide="bug" class="w-5 h-5 text-gray-400"></i>
                                 <a href="?view=debug" class="text-primary-600 hover:text-primary-700 font-medium transition-colors">
                                     Debug Tools
                                 </a>
                             </div>
                             <?php endif; ?>
                         </div>
                         <p class="text-sm text-gray-500 mt-3">We're here to help you get the most out of your domain management experience.</p>
                     </div>
                 </div>
             </main>
         </div>
     </div>

    <?php if ($currentView !== 'domains'): ?>
    <script src="js/dashboard.js"></script>
    <?php endif; ?>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Setup Progress Hide/Show functionality
        document.addEventListener('DOMContentLoaded', function() {
            const setupProgressSection = document.getElementById('setupProgressSection');
            const showSetupProgressButton = document.getElementById('showSetupProgressButton');
            const hideSetupProgressButton = document.getElementById('hideSetupProgress');
            const showSetupProgressButton2 = document.getElementById('showSetupProgress');
            
            // Check if setup progress was previously hidden
            const isHidden = localStorage.getItem('setupProgressHidden') === 'true';
            
            if (isHidden && setupProgressSection) {
                setupProgressSection.style.display = 'none';
                if (showSetupProgressButton) {
                    showSetupProgressButton.classList.remove('hidden');
                }
            }
            
            // Hide setup progress
            if (hideSetupProgressButton) {
                hideSetupProgressButton.addEventListener('click', function() {
                    if (setupProgressSection) {
                        setupProgressSection.style.display = 'none';
                        localStorage.setItem('setupProgressHidden', 'true');
                    }
                    if (showSetupProgressButton) {
                        showSetupProgressButton.classList.remove('hidden');
                    }
                });
            }
            
            // Show setup progress
            if (showSetupProgressButton2) {
                showSetupProgressButton2.addEventListener('click', function() {
                    if (setupProgressSection) {
                        setupProgressSection.style.display = 'block';
                        localStorage.setItem('setupProgressHidden', 'false');
                    }
                    if (showSetupProgressButton) {
                        showSetupProgressButton.classList.add('hidden');
                    }
                });
            }
        });

        // Search now uses server-side filtering via form submission
        console.log('Domain search and filters are now server-side powered');
        
        // Sync functionality
        let syncInProgress = false;
        let currentLogId = null;
        
        // Clear old data functionality for debug page
        document.getElementById('clearOldData')?.addEventListener('click', function() {
            if (confirm('Are you sure you want to clear old data? This will remove domains that haven\'t been synced in the last 30 days.')) {
                // Add your clear old data logic here
                console.log('Clear old data functionality would be implemented here');
                alert('Clear old data functionality would be implemented here');
            }
        });
        
        // Start sync button
        document.getElementById('startSync')?.addEventListener('click', function() {
            if (syncInProgress) return;
            
            const batchNumber = document.getElementById('batch_number')?.value;
            const batchSize = document.getElementById('batch_size')?.value;
            
            if (batchNumber && batchSize) {
                startSync(batchNumber, batchSize);
            }
        });
        
        // Stop sync button
        document.getElementById('stopSync')?.addEventListener('click', function() {
            if (!syncInProgress) return;
            
            stopSync();
        });
        
        // Clear old data button
        document.getElementById('clearOldData')?.addEventListener('click', function() {
            if (confirm('Are you sure you want to clear old data? This will remove domains that haven\'t been synced in the last 30 days.')) {
                clearOldData();
            }
        });
        
        function startSync(batchNumber, batchSize) {
            syncInProgress = true;
            const startSyncBtn = document.getElementById('startSync');
            const stopSyncBtn = document.getElementById('stopSync');
            const syncProgress = document.getElementById('syncProgress');
            const syncLog = document.getElementById('syncLog');
            
            if (startSyncBtn) startSyncBtn.style.display = 'none';
            if (stopSyncBtn) stopSyncBtn.style.display = 'inline-flex';
            if (syncProgress) syncProgress.style.display = 'block';
            if (syncLog) syncLog.style.display = 'block';
            
            // Reset progress
            resetProgress();
            addLogMessage('Starting sync for batch ' + batchNumber + '...');
            
            // Update current status
            const currentStatus = document.getElementById('currentStatus');
            if (currentStatus) currentStatus.textContent = 'Initializing...';
            
            // Make AJAX request to start sync using ultra-fast endpoint
            fetch('ultra_simple_sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'batch_number=' + batchNumber + '&batch_size=' + batchSize
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentLogId = data.log_id;
                    
                    // Start polling for progress updates
                    pollSyncProgress(batchNumber, batchSize);
                    
                    addLogMessage('Batch ' + batchNumber + ' started successfully!');
                    addLogMessage('Monitoring progress in real-time...');
                    
                } else {
                    addLogMessage('Sync failed: ' + (data.error || 'Unknown error'), 'error');
                    syncInProgress = false;
                    if (startSyncBtn) startSyncBtn.style.display = 'inline-flex';
                    if (stopSyncBtn) stopSyncBtn.style.display = 'none';
                }
            })
            .catch(error => {
                addLogMessage('Network error: ' + error.message, 'error');
                syncInProgress = false;
                if (startSyncBtn) startSyncBtn.style.display = 'inline-flex';
                if (stopSyncBtn) stopSyncBtn.style.display = 'none';
            });
        }
        
        function pollSyncProgress(batchNumber, batchSize) {
            if (!syncInProgress) return;
            
            fetch('ultra_simple_sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=check_progress&batch_number=' + batchNumber + '&batch_size=' + batchSize
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateProgress(data.data);
                    
                    // Update log with current domain if available
                    if (data.data.current_domain) {
                        const currentStatus = document.getElementById('currentStatus');
                        if (currentStatus && currentStatus.textContent !== 'Completed') {
                            addLogMessage('Processing: ' + data.data.current_domain);
                        }
                    }
                    
                    // Check if sync is complete
                    if (data.data.status === 'completed') {
                        addLogMessage('✅ Batch ' + batchNumber + ' completed successfully!');
                        
                        // Show batch information
                        if (data.data.total_domains && data.data.batch_start && data.data.batch_end) {
                            addLogMessage('Processed domains ' + data.data.batch_start + '-' + data.data.batch_end + ' of ' + data.data.total_domains);
                        }
                        
                        addLogMessage('Domains in batch: ' + data.data.domains_found);
                        addLogMessage('Domains processed: ' + data.data.domains_processed);
                        addLogMessage('Domains added: ' + data.data.domains_added);
                        addLogMessage('Domains updated: ' + data.data.domains_updated);
                        
                        if (data.data.errors > 0) {
                            addLogMessage('Errors: ' + data.data.errors, 'error');
                        }
                        
                        // Suggest next batch if there are more domains
                        if (data.data.total_domains && data.data.batch_end < data.data.total_domains) {
                            const nextBatch = parseInt(batchNumber) + 1;
                            addLogMessage('💡 Tip: Run batch ' + nextBatch + ' to continue syncing remaining domains');
                        }
                        
                        syncInProgress = false;
                        const startSyncBtn = document.getElementById('startSync');
                        const stopSyncBtn = document.getElementById('stopSync');
                        if (startSyncBtn) startSyncBtn.style.display = 'inline-flex';
                        if (stopSyncBtn) stopSyncBtn.style.display = 'none';
                    } else {
                        // Continue polling
                        setTimeout(() => pollSyncProgress(batchNumber, batchSize), 1000);
                    }
                } else {
                    addLogMessage('Progress check failed: ' + (data.error || 'Unknown error'), 'error');
                    syncInProgress = false;
                    const startSyncBtn = document.getElementById('startSync');
                    const stopSyncBtn = document.getElementById('stopSync');
                    if (startSyncBtn) startSyncBtn.style.display = 'inline-flex';
                    if (stopSyncBtn) stopSyncBtn.style.display = 'none';
                }
            })
            .catch(error => {
                addLogMessage('Progress check error: ' + error.message, 'error');
                syncInProgress = false;
                const startSyncBtn = document.getElementById('startSync');
                const stopSyncBtn = document.getElementById('stopSync');
                if (startSyncBtn) startSyncBtn.style.display = 'inline-flex';
                if (stopSyncBtn) stopSyncBtn.style.display = 'none';
            });
        }
        
        function stopSync() {
            syncInProgress = false;
            const startSyncBtn = document.getElementById('startSync');
            const stopSyncBtn = document.getElementById('stopSync');
            
            if (startSyncBtn) startSyncBtn.style.display = 'inline-flex';
            if (stopSyncBtn) stopSyncBtn.style.display = 'none';
            addLogMessage('Sync stopped by user', 'warning');
        }
        
        function clearOldData() {
            fetch('domain_sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=clear_old_data&days_old=30'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addLogMessage('Old data cleared successfully');
                    // Reload page to update stats
                    setTimeout(() => location.reload(), 1000);
                } else {
                    addLogMessage('Failed to clear old data: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                addLogMessage('Network error: ' + error.message, 'error');
            });
        }
        
        function resetProgress() {
            const currentDomain = document.getElementById('currentDomain');
            const currentStatus = document.getElementById('currentStatus');
            const domainsFound = document.getElementById('domainsFound');
            const domainsProcessed = document.getElementById('domainsProcessed');
            const domainsAdded = document.getElementById('domainsAdded');
            const domainsUpdated = document.getElementById('domainsUpdated');
            const syncErrors = document.getElementById('syncErrors');
            const progressBar = document.getElementById('progressBar');
            const progressPercentage = document.getElementById('progressPercentage');
            
            if (currentDomain) currentDomain.textContent = '-';
            if (currentStatus) currentStatus.textContent = 'Waiting...';
            if (domainsFound) domainsFound.textContent = '0';
            if (domainsProcessed) domainsProcessed.textContent = '0';
            if (domainsAdded) domainsAdded.textContent = '0';
            if (domainsUpdated) domainsUpdated.textContent = '0';
            if (syncErrors) syncErrors.textContent = '0';
            if (progressBar) progressBar.style.width = '0%';
            if (progressPercentage) progressPercentage.textContent = '0%';
        }
        
        function updateProgress(data) {
            const currentDomain = document.getElementById('currentDomain');
            const currentStatus = document.getElementById('currentStatus');
            const domainsFound = document.getElementById('domainsFound');
            const domainsProcessed = document.getElementById('domainsProcessed');
            const domainsAdded = document.getElementById('domainsAdded');
            const domainsUpdated = document.getElementById('domainsUpdated');
            const syncErrors = document.getElementById('syncErrors');
            const progressBar = document.getElementById('progressBar');
            const progressPercentage = document.getElementById('progressPercentage');
            
            // Update current domain and status
            if (currentDomain && data.current_domain) {
                currentDomain.textContent = data.current_domain;
            }
            if (currentStatus) {
                currentStatus.textContent = data.status === 'completed' ? 'Completed' : 'Processing...';
            }
            
            // Update statistics
            if (domainsFound) domainsFound.textContent = data.domains_found || 0;
            if (domainsProcessed) domainsProcessed.textContent = data.domains_processed || 0;
            if (domainsAdded) domainsAdded.textContent = data.domains_added || 0;
            if (domainsUpdated) domainsUpdated.textContent = data.domains_updated || 0;
            if (syncErrors) syncErrors.textContent = data.errors || 0;
            
            // Update progress bar and percentage
            if (progressBar && data.domains_found > 0) {
                const progress = Math.min((data.domains_processed / data.domains_found) * 100, 100);
                progressBar.style.width = progress + '%';
                if (progressPercentage) {
                    progressPercentage.textContent = Math.round(progress) + '%';
                }
            }
        }
        
        function addLogMessage(message, type = 'info') {
            const logContent = document.getElementById('logContent');
            if (!logContent) return;
            
            const timestamp = new Date().toLocaleTimeString();
            const colorClass = type === 'error' ? 'text-red-600' : type === 'warning' ? 'text-yellow-600' : 'text-gray-600';
            
            const logEntry = document.createElement('div');
            logEntry.className = 'mb-1 ' + colorClass;
            logEntry.textContent = '[' + timestamp + '] ' + message;
            
            // Prepend new messages to show latest at the top
            logContent.insertBefore(logEntry, logContent.firstChild);
            logContent.scrollTop = 0; // Scroll to top to show latest message
        }
        
        // Prevent any form hijacking on domains page
        <?php if ($currentView === 'domains'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Disable any existing event listeners on the search form
            const searchForm = document.getElementById('domainSearchForm');
            if (searchForm) {
                // Store current values before cloning
                const registrarValue = document.getElementById('registrarFilter')?.value || '';
                const statusValue = document.getElementById('statusFilter')?.value || '';
                const searchValue = document.getElementById('domainSearch')?.value || '';
                
                // Clone the form to remove all event listeners
                const newForm = searchForm.cloneNode(true);
                searchForm.parentNode.replaceChild(newForm, searchForm);
                console.log('Cleaned search form from event listeners');
                
                // Restore values and selections
                const newRegistrarFilter = document.getElementById('registrarFilter');
                const newStatusFilter = document.getElementById('statusFilter');
                const newSearchInput = document.getElementById('domainSearch');
                
                if (newRegistrarFilter) {
                    newRegistrarFilter.value = registrarValue;
                    // Force "All Registrars" selection if value is empty
                    if (!registrarValue) {
                        newRegistrarFilter.selectedIndex = 0;
                    }
                    newRegistrarFilter.addEventListener('change', function() {
                        this.form.submit();
                    });
                }
                
                if (newStatusFilter) {
                    newStatusFilter.value = statusValue;
                    // Force "All Statuses" selection if value is empty
                    if (!statusValue) {
                        newStatusFilter.selectedIndex = 0;
                    }
                    newStatusFilter.addEventListener('change', function() {
                        this.form.submit();
                    });
                }
                
                if (newSearchInput) {
                    newSearchInput.value = searchValue;
                }
                
                console.log('Re-added filter auto-submit functionality and restored selections');
            }
            
            // Remove any opacity or overlay effects that might be applied
            const allElements = document.querySelectorAll('*');
            allElements.forEach(el => {
                if (el.style.opacity && el.style.opacity !== '1') {
                    el.style.opacity = '1';
                }
            });
        });
        <?php endif; ?>
    </script>
    
    <!-- Add Domain Modal -->
    <div id="addDomainModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Add New Domain</h3>
                        <button type="button" id="closeAddDomainModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                
                <form id="addDomainForm" class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label for="domainName" class="block text-sm font-medium text-gray-700 mb-1">Domain Name</label>
                            <input type="text" id="domainName" name="domain_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="example.com">
                        </div>
                        
                        <div>
                            <label for="registrar" class="block text-sm font-medium text-gray-700 mb-1">Registrar</label>
                            <div class="space-y-2">
                                <select id="registrar" name="registrar" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">Select a registrar...</option>
                                    <option value="__add_new__">+ Add New Registrar</option>
                                    <?php if (isset($uniqueRegistrars) && !empty($uniqueRegistrars)): ?>
                                        <?php foreach ($uniqueRegistrars as $registrar): ?>
                                            <option value="<?= htmlspecialchars($registrar) ?>"><?= htmlspecialchars($registrar) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <input type="text" id="newRegistrar" name="new_registrar" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                       placeholder="Enter new registrar name" style="display: none;">
                            </div>
                        </div>
                        
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="Active">Active</option>
                                <option value="Expired">Expired</option>
                                <option value="Pending">Pending</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="Grace">Grace</option>
                                <option value="Redemption">Redemption</option>
                                <option value="Transferred Away">Transferred Away</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="expiryDate" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                            <input type="date" id="expiryDate" name="expiry_date" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        

                        
                        <div>
                            <label for="nameservers" class="block text-sm font-medium text-gray-700 mb-1">Nameservers (comma separated)</label>
                            <input type="text" id="nameservers" name="nameservers" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="ns1.example.com, ns2.example.com">
                        </div>
                        

                    </div>
                    
                    <div class="flex items-center justify-end space-x-3 mt-6">
                        <button type="button" id="cancelAddDomain" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            Add Domain
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Domain Modal -->
    <div id="editDomainModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Edit Domain</h3>
                        <button type="button" id="closeEditDomainModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                
                <form id="editDomainForm" class="p-6">
                    <input type="hidden" id="editDomainId" name="domain_id">
                    <div class="space-y-4">
                        <div>
                            <label for="editDomainName" class="block text-sm font-medium text-gray-700 mb-1">Domain Name</label>
                            <input type="text" id="editDomainName" name="domain_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="example.com">
                        </div>
                        
                        <div>
                            <label for="editRegistrar" class="block text-sm font-medium text-gray-700 mb-1">Registrar</label>
                            <div class="space-y-2">
                                <select id="editRegistrar" name="registrar" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">Select a registrar...</option>
                                    <option value="__add_new__">+ Add New Registrar</option>
                                    <?php 
                                    // Use the appropriate registrar list based on current view
                                    $registrarList = [];
                                    if ($currentView === 'domains' && isset($uniqueRegistrars)) {
                                        $registrarList = $uniqueRegistrars;
                                    } elseif ($currentView === 'database_view' && isset($databaseViewUniqueRegistrars)) {
                                        $registrarList = $databaseViewUniqueRegistrars;
                                    }
                                    ?>
                                    <?php if (!empty($registrarList)): ?>
                                        <?php foreach ($registrarList as $registrar): ?>
                                            <option value="<?= htmlspecialchars($registrar) ?>"><?= htmlspecialchars($registrar) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <input type="text" id="editNewRegistrar" name="new_registrar" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 hidden"
                                       placeholder="Enter new registrar name">
                            </div>
                        </div>
                        
                        <div>
                            <label for="editStatus" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="editStatus" name="status" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="Active">Active</option>
                                <option value="Expired">Expired</option>
                                <option value="Pending">Pending</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="Grace">Grace</option>
                                <option value="Redemption">Redemption</option>
                                <option value="Transferred Away">Transferred Away</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="editExpiryDate" class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                            <input type="date" id="editExpiryDate" name="expiry_date" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        
                        <div>
                            <label for="editNameservers" class="block text-sm font-medium text-gray-700 mb-1">Nameservers (comma separated)</label>
                            <input type="text" id="editNameservers" name="nameservers" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="ns1.example.com, ns2.example.com">
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-end space-x-3 mt-6">
                        <button type="button" id="cancelEditDomain" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            Update Domain
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cache Modal Script -->
    <script src="js/cache-modal.js"></script>
    
    <script>
        // Add Domain Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const addDomainBtn = document.getElementById('addDomainBtn');
            const addDomainModal = document.getElementById('addDomainModal');
            const closeAddDomainModal = document.getElementById('closeAddDomainModal');
            const cancelAddDomain = document.getElementById('cancelAddDomain');
            const addDomainForm = document.getElementById('addDomainForm');
            
            // Open modal
            addDomainBtn?.addEventListener('click', function() {
                addDomainModal.classList.remove('hidden');
                // Set default date
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('expiryDate').value = today;
            });
            
            // Close modal
            function closeModal() {
                addDomainModal.classList.add('hidden');
                addDomainForm.reset();
            }
            
            closeAddDomainModal?.addEventListener('click', closeModal);
            cancelAddDomain?.addEventListener('click', closeModal);
            
            // Close modal when clicking outside
            addDomainModal?.addEventListener('click', function(e) {
                if (e.target === addDomainModal) {
                    closeModal();
                }
            });
            
            // Registrar field functionality for Add Domain
            document.addEventListener('change', function(e) {
                if (e.target.id === 'registrar') {
                    const newRegistrarInput = document.getElementById('newRegistrar');
                    if (e.target.value === '__add_new__') {
                        newRegistrarInput.style.display = 'block';
                        newRegistrarInput.required = true;
                        newRegistrarInput.focus();
                    } else {
                        newRegistrarInput.style.display = 'none';
                        newRegistrarInput.required = false;
                        newRegistrarInput.value = '';
                    }
                }
            });
            
            // Handle form submission
            addDomainForm?.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(addDomainForm);
                
                // If "Add New Registrar" is selected, use the new registrar value
                if (formData.get('registrar') === '__add_new__') {
                    const newRegistrarValue = formData.get('new_registrar');
                    if (!newRegistrarValue || newRegistrarValue.trim() === '') {
                        alert('Please enter a registrar name');
                        return;
                    }
                    formData.set('registrar', newRegistrarValue.trim());
                    formData.delete('new_registrar');
                }
                
                fetch('add_domain.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Domain added successfully!');
                        closeModal();
                        location.reload(); // Refresh the page to show the new domain
                    } else {
                        alert('Error: ' + (data.error || 'Failed to add domain'));
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            });
            
            // Edit Domain Modal Functionality
            const editDomainBtn = document.querySelectorAll('.edit-domain-btn');
            const editDomainModal = document.getElementById('editDomainModal');
            const closeEditDomainModal = document.getElementById('closeEditDomainModal');
            const cancelEditDomain = document.getElementById('cancelEditDomain');
            const editDomainForm = document.getElementById('editDomainForm');
            
            // Open edit modal
            editDomainBtn.forEach(btn => {
                btn.addEventListener('click', function() {
                    const domainId = this.getAttribute('data-domain-id');
                    const domainName = this.getAttribute('data-domain-name');
                    const registrar = this.getAttribute('data-registrar');
                    const status = this.getAttribute('data-status');
                    const expiryDate = this.getAttribute('data-expiry-date');
                    const nameservers = this.getAttribute('data-nameservers');
                    
                    // Populate form fields
                    document.getElementById('editDomainId').value = domainId;
                    document.getElementById('editDomainName').value = domainName;
                    document.getElementById('editRegistrar').value = registrar;
                    document.getElementById('editStatus').value = status;
                    document.getElementById('editExpiryDate').value = expiryDate;
                    document.getElementById('editNameservers').value = nameservers;
                    
                    editDomainModal.classList.remove('hidden');
                });
            });
            
            // Close edit modal
            function closeEditModal() {
                editDomainModal.classList.add('hidden');
                editDomainForm.reset();
            }
            
            closeEditDomainModal?.addEventListener('click', closeEditModal);
            cancelEditDomain?.addEventListener('click', closeEditModal);
            
            // Close edit modal when clicking outside
            editDomainModal?.addEventListener('click', function(e) {
                if (e.target === editDomainModal) {
                    closeEditModal();
                }
            });
            
            // Registrar field functionality for Edit Domain
            const editRegistrarSelect = document.getElementById('editRegistrar');
            const editNewRegistrarInput = document.getElementById('editNewRegistrar');
            
            editRegistrarSelect?.addEventListener('change', function() {
                if (this.value === '__add_new__') {
                    editNewRegistrarInput.classList.remove('hidden');
                    editNewRegistrarInput.required = true;
                    editNewRegistrarInput.focus();
                } else {
                    editNewRegistrarInput.classList.add('hidden');
                    editNewRegistrarInput.required = false;
                    editNewRegistrarInput.value = '';
                }
            });
            
            // Handle edit form submission
            editDomainForm?.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(editDomainForm);
                
                // If "Add New Registrar" is selected, use the new registrar value
                if (formData.get('registrar') === '__add_new__') {
                    const newRegistrarValue = formData.get('new_registrar');
                    if (!newRegistrarValue || newRegistrarValue.trim() === '') {
                        alert('Please enter a registrar name');
                        return;
                    }
                    formData.set('registrar', newRegistrarValue.trim());
                    formData.delete('new_registrar');
                }
                
                fetch('update_domain.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Domain updated successfully!');
                        closeEditModal();
                        location.reload(); // Refresh the page to show the updated domain
                    } else {
                        alert('Error: ' + (data.error || 'Failed to update domain'));
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            });
            
            // Delete Domain Functionality
            const deleteDomainBtn = document.querySelectorAll('.delete-domain-btn');
            
            deleteDomainBtn.forEach(btn => {
                btn.addEventListener('click', function() {
                    const domainId = this.getAttribute('data-domain-id');
                    const domainName = this.getAttribute('data-domain-name');
                    
                    // Show confirmation dialog
                    if (confirm(`Are you sure you want to delete the domain "${domainName}"?\n\nThis action cannot be undone.`)) {
                        // Create form data
                        const formData = new FormData();
                        formData.append('domain_id', domainId);
                        
                        fetch('delete_domain.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Domain deleted successfully!');
                                location.reload(); // Refresh the page to show the updated list
                            } else {
                                alert('Error: ' + (data.error || 'Failed to delete domain'));
                            }
                        })
                        .catch(error => {
                            alert('Error: ' + error.message);
                        });
                    }
                });
            });
        });

        // Domain sorting functionality for nameservers view
        if (window.location.search.includes('view=nameservers')) {
            console.log('Nameservers view detected, setting up domain sorting...');
            
            function sortDomainsInSelect() {
                const domainSelect = document.getElementById('domain');
                if (domainSelect) {
                    console.log('Found domain select with', domainSelect.options.length, 'options');
                    
                    // Get all options
                    const options = Array.from(domainSelect.options);
                    
                    // Log first few domains before sorting
                    console.log('First 5 domains before JS sorting:', 
                        options.slice(0, 5).map(opt => opt.text));
                    
                    // Sort options alphabetically (case-insensitive)
                    options.sort(function(a, b) {
                        return a.text.toLowerCase().localeCompare(b.text.toLowerCase());
                    });
                    
                    // Log first few domains after sorting
                    console.log('First 5 domains after JS sorting:', 
                        options.slice(0, 5).map(opt => opt.text));
                    
                    // Clear and re-add sorted options
                    domainSelect.innerHTML = '';
                    options.forEach(function(option) {
                        domainSelect.appendChild(option);
                    });
                    
                    console.log('Domain sorting completed');
                } else {
                    console.log('Domain select element not found');
                }
            }

            // Run sorting when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', sortDomainsInSelect);
            } else {
                sortDomainsInSelect();
            }
        }
        
        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const sidebar = document.querySelector('.w-80');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        
        if (mobileMenuButton && sidebar) {
            mobileMenuButton.addEventListener('click', function() {
                sidebar.classList.toggle('-translate-x-full');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle('hidden');
                }
            });
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.add('-translate-x-full');
                    sidebarOverlay.classList.add('hidden');
                });
            }
            
            // Close mobile menu when clicking on a menu item
            const menuItems = sidebar.querySelectorAll('a[href]');
            menuItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    if (window.innerWidth < 1024) { // Only on mobile
                        sidebar.classList.add('-translate-x-full');
                        if (sidebarOverlay) {
                            sidebarOverlay.classList.add('hidden');
                        }
                    }
                });
            });
        }
        

        
        // Company logo preview functionality
        function updateCompanyLogoPreview() {
            const logoUrlInput = document.getElementById('company_logo_url');
            const logoPreviewContainer = document.getElementById('company_logo_preview_container');
            const logoPreview = document.getElementById('company_logo_preview');
            const logoError = document.getElementById('company_logo_error');
            
            if (logoUrlInput && logoPreviewContainer && logoPreview && logoError) {
                const logoUrl = logoUrlInput.value.trim();
                
                if (logoUrl) {
                    // Show the preview container
                    logoPreviewContainer.style.display = 'block';
                    
                    // Update the image source
                    logoPreview.src = logoUrl;
                    
                    // Hide any previous error
                    logoError.style.display = 'none';
                    logoPreview.style.display = 'block';
                } else {
                    // Hide the preview container if no URL
                    logoPreviewContainer.style.display = 'none';
                }
            }
        }
        
        function showCompanyLogoError() {
            const logoPreview = document.getElementById('company_logo_preview');
            const logoError = document.getElementById('company_logo_error');
            
            if (logoPreview && logoError) {
                logoPreview.style.display = 'none';
                logoError.style.display = 'block';
            }
        }
        
        function hideCompanyLogoError() {
            const logoPreview = document.getElementById('company_logo_preview');
            const logoError = document.getElementById('company_logo_error');
            
            if (logoPreview && logoError) {
                logoPreview.style.display = 'block';
                logoError.style.display = 'none';
            }
        }
        
        // Initialize logo previews on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCompanyLogoPreview();
        });
    </script>

</body>
</html> 