<?php
require_once 'auth.php';
require_once 'api.php';
require_once 'user_settings.php';
require_once 'cache.php';

// Require authentication
requireAuth();

// Check if user has settings configured
$hasSettings = userHasSettings();
$settingsValidation = validateSettingsCompleteness();

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

// Handle settings save
$message = '';
$messageType = '';
if (isset($_POST['save_settings'])) {
    $requiredFields = ['api_url', 'api_identifier', 'api_secret', 'default_ns1', 'default_ns2'];
    $allFieldsProvided = true;
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
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
            'default_ns2' => trim($_POST['default_ns2']),
            'logo_url' => trim($_POST['logo_url'] ?? '')
        ];
        
        $userSettings = new UserSettings();
        if ($userSettings->saveSettings($_SESSION['user_email'], $settings)) {
            $message = 'Settings saved successfully!';
            $messageType = 'success';
            
            // Clear user cache when settings change
            clearUserCache($_SESSION['user_email']);
            
            // Refresh settings status
            $hasSettings = userHasSettings();
            $settingsValidation = validateSettingsCompleteness();
        } else {
            $message = 'Failed to save settings. Please try again.';
            $messageType = 'error';
        }
    } else {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    }
}

// Handle settings test
if (isset($_POST['test_settings'])) {
    $userSettings = getUserSettings();
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

// Load existing settings
$currentSettings = getUserSettings();

// Determine current view
$currentView = $_GET['view'] ?? 'dashboard';

// Handle nameserver updates
$updateMessage = '';
$updateResults = [];
$allDomains = [];

if ($currentView === 'nameservers') {
    // Check if user has configured their API settings
    if (!userHasSettings()) {
        $updateMessage = 'Please configure your API settings first.';
    } else {
        // Load user settings
        $userSettings = getUserSettings();
        if (!$userSettings) {
            $updateMessage = 'Unable to load your API settings. Please configure them first.';
        } else {
            // Get all domains
            $response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
            $allDomains = $response['domains']['domain'] ?? [];
            
            // Sort domains alphabetically
            usort($allDomains, function($a, $b) {
                return strcmp($a['domainname'], $b['domainname']);
            });
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

// Handle export domains
$exportMessage = '';
$exportResults = [];

if ($currentView === 'export') {
    // Check if user has configured their API settings
    if (!userHasSettings()) {
        $exportMessage = 'Please configure your API settings first.';
    } else {
        // Load user settings
        $userSettings = getUserSettings();
        if (!$userSettings) {
            $exportMessage = 'Unable to load your API settings. Please configure them first.';
        }
    }
    
    // Handle CSV export
    if (isset($_POST['export_csv'])) {
        // Get batch parameters
        $batchSize = 200; // Keep at 200 to avoid timeouts
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
                $filename = 'domains_active_batch' . $batchNumber . '_' . date('Y-m-d_H-i-s') . '.csv';
                $file = fopen($filename, 'w');
                fputcsv($file, ['Domain Name', 'Domain ID', 'Status', 'NS1', 'NS2', 'NS3', 'NS4', 'NS5', 'Notes', 'Batch Number']);
                
                $processed = 0;
                $successful = 0;
                $errors = 0;
                
                foreach ($activeDomains as $domain) {
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
                            fputcsv($file, [$domainName, $domainId, $domainStatus, 'ERROR', $errorMsg, '', '', '', 'Failed to get nameservers', $batchNumber]);
                            $errors++;
                        }
                    }
                    
                    $processed++;
                    usleep(250000); // 0.25 second delay
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

// If user has settings, get real domain data
if (userHasSettings()) {
    $userSettings = getUserSettings();
    if ($userSettings) {
        $response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
        if (isset($response['domains']['domain']) && is_array($response['domains']['domain'])) {
            $allDomains = $response['domains']['domain'];
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
        }
    }
}

// Get recent domains for dashboard
$recentProjects = [];

if (userHasSettings()) {
    $userSettings = getUserSettings();
    if ($userSettings) {
        $response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
        if (isset($response['domains']['domain']) && is_array($response['domains']['domain'])) {
            $allDomains = $response['domains']['domain'];
            
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

// Fallback to mock data if no domains found
if (empty($recentProjects)) {
    $recentProjects = [
        ['name' => 'No domains found', 'due_date' => 'Configure API settings', 'icon' => 'alert-circle', 'status' => 'none']
    ];
}

// Get system status information
$systemStatus = [];

// Get registrars data
$registrarsData = [];

// Get server health data
$serverHealth = [];

if (userHasSettings()) {
    $userSettings = getUserSettings();
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
            
            // Get registrars and count domains per registrar
            $registrarsResponse = getRegistrars($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
            if (isset($registrarsResponse['registrars']) && is_array($registrarsResponse['registrars'])) {
                $allDomains = $response['domains']['domain'];
                
                // Count domains per registrar
                $registrarCounts = [];
                foreach ($allDomains as $domain) {
                    $registrar = $domain['registrar'] ?? 'Unknown';
                    if (!isset($registrarCounts[$registrar])) {
                        $registrarCounts[$registrar] = 0;
                    }
                    $registrarCounts[$registrar]++;
                }
                
                // Sort by domain count (highest first)
                arsort($registrarCounts);
                
                // Take top 5 registrars
                $topRegistrars = array_slice($registrarCounts, 0, 5, true);
                
                foreach ($topRegistrars as $registrar => $count) {
                    $registrarsData[] = [
                        'name' => $registrar,
                        'count' => $count,
                        'percentage' => round(($count / $domainCount) * 100, 1)
                    ];
                }
            }
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

// Get domain status distribution for analytics
$domainStatusStats = [
    'Active' => 0,
    'Pending' => 0,
    'Suspended' => 0,
    'Cancelled' => 0,
    'Fraud' => 0,
    'Other' => 0
];

if (userHasSettings()) {
    $userSettings = getUserSettings();
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
        
        // Get domain status distribution
        $domainsResponse = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
        if (isset($domainsResponse['domains']['domain']) && is_array($domainsResponse['domains']['domain'])) {
            $allDomains = $domainsResponse['domains']['domain'];
            
            foreach ($allDomains as $domain) {
                $status = $domain['status'] ?? 'Unknown';
                $status = ucfirst(strtolower($status)); // Normalize status
                
                if (isset($domainStatusStats[$status])) {
                    $domainStatusStats[$status]++;
                } else {
                    $domainStatusStats['Other']++;
                }
            }
        }
    } else {
        $systemStatus[] = [
            'name' => 'Configuration',
            'task' => 'API settings not configured',
            'status' => 'pending'
        ];
    }
} else {
    $systemStatus[] = [
        'name' => 'Configuration',
        'task' => 'Please configure API settings first',
        'status' => 'pending'
    ];
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
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col lg:translate-x-0 -translate-x-full transition-transform duration-300 fixed lg:relative z-40 h-full">
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
                            <li>
                                <a href="?view=domains" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'domains' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="globe" class="w-4 h-4 <?= $currentView === 'domains' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'domains' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Domains</span>
                                </a>
                            </li>
                            <li>
                                <a href="?view=nameservers" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'nameservers' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="refresh-cw" class="w-4 h-4 <?= $currentView === 'nameservers' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'nameservers' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Update Nameservers</span>
                                </a>
                            </li>
                            <li>
                                <a href="?view=export" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'export' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                    <i data-lucide="download" class="w-4 h-4 <?= $currentView === 'export' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                    <span class="text-sm <?= $currentView === 'export' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Export Domains</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-auto">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">GENERAL</h3>
                    <ul class="space-y-1">
                        <li>
                            <a href="?view=settings" class="flex items-center space-x-3 px-3 py-2 <?= $currentView === 'settings' ? 'bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600' : 'text-gray-500 hover:bg-gray-50 rounded-lg transition-colors' ?>">
                                <i data-lucide="settings" class="w-4 h-4 <?= $currentView === 'settings' ? 'text-primary-600' : 'text-gray-400' ?>"></i>
                                <span class="text-sm <?= $currentView === 'settings' ? 'font-semibold text-gray-900' : 'font-normal' ?>">Settings</span>
                            </a>
                        </li>
                        <li>
                            <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                <i data-lucide="help-circle" class="w-4 h-4 text-gray-400"></i>
                                <span class="text-sm font-normal">Help</span>
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
                <div class="flex items-center justify-end">
                    <div class="flex items-center space-x-4">
                        <button class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                            <i data-lucide="mail" class="w-5 h-5"></i>
                        </button>
                        <button class="p-2 text-gray-400 hover:text-gray-600 transition-colors">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                        </button>
                        <div class="flex items-center space-x-3">
                            <?php
                            $userEmail = $_SESSION['user_email'] ?? '';
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
                                <p class="text-xs text-gray-500">Administrator</p>
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
                    <!-- Logo -->
                    <div class="mb-4 flex justify-center">
                        <img src="<?= htmlspecialchars(getLogoUrl()) ?>" 
                             alt="Logo" 
                             class="h-12 max-w-full object-contain"
                             onerror="this.style.display='none';">
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Dashboard</h1>
                    <p class="text-gray-600">Plan, prioritize, and manage your domains with ease.</p>
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
                                <h3 class="text-lg font-semibold text-gray-900">Domain Analytics</h3>
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
                     <!-- Logo -->
                     <div class="mb-4 flex justify-center">
                         <img src="<?= htmlspecialchars(getLogoUrl()) ?>" 
                              alt="Logo" 
                              class="h-12 max-w-full object-contain"
                              onerror="this.style.display='none';">
                     </div>
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
                         <div class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($incomeStats['income_today']) ?></div>
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
                         <div class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($incomeStats['income_thismonth']) ?></div>
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
                         <div class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($incomeStats['income_thisyear']) ?></div>
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
                         <div class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($incomeStats['income_alltime']) ?></div>
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
                     <!-- Logo -->
                     <div class="mb-4 flex justify-center">
                         <img src="<?= htmlspecialchars(getLogoUrl()) ?>" 
                              alt="Logo" 
                              class="h-12 max-w-full object-contain"
                              onerror="this.style.display='none';">
                     </div>
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
                         <div class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($ordersStats['orders_today_total']) ?></div>
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
                         <div class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($ordersStats['orders_yesterday_total']) ?></div>
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
                         <div class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($ordersStats['orders_thismonth_total']) ?></div>
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
                         <div class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($ordersStats['orders_thisyear_total']) ?></div>
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
                     <!-- Logo -->
                     <div class="mb-4 flex justify-center">
                         <img src="<?= htmlspecialchars(getLogoUrl()) ?>" 
                              alt="Logo" 
                              class="h-12 max-w-full object-contain"
                              onerror="this.style.display='none';">
                     </div>
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">API Settings</h1>
                     <p class="text-gray-600">Configure your WHMCS API credentials and default nameserver settings.</p>
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

                     <!-- Customization Section -->
                     <div class="bg-white p-6 rounded-xl border border-gray-200">
                         <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                             <i data-lucide="palette" class="w-5 h-5 text-primary-600"></i>
                             <span>Customization</span>
                         </h3>
                         
                         <div>
                             <label for="logo_url" class="block text-sm font-medium text-gray-700 mb-2">Custom Logo URL</label>
                             <input 
                                 type="url" 
                                 id="logo_url" 
                                 name="logo_url" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                 value="<?= htmlspecialchars($currentSettings['logo_url'] ?? '') ?>"
                                 placeholder="https://yourdomain.com/logo.png"
                                 oninput="updateLogoPreview()"
                                 onblur="updateLogoPreview()"
                             >
                             <p class="text-xs text-gray-500 mt-1">Optional: Enter a URL to your custom logo. Recommended size: 200x60 pixels.</p>
                             
                             <div id="logo_preview_container" class="mt-3 p-3 bg-gray-50 rounded-lg" style="display: <?= !empty($currentSettings['logo_url']) ? 'block' : 'none' ?>;">
                                 <div class="text-sm font-medium text-gray-700 mb-2">Logo Preview:</div>
                                 <img id="logo_preview" 
                                      src="<?= htmlspecialchars($currentSettings['logo_url'] ?? '') ?>" 
                                      alt="Custom Logo" 
                                      class="max-h-12 max-w-full object-contain"
                                      onerror="showLogoError()"
                                      onload="hideLogoError()">
                                 <div id="logo_error" class="text-sm text-red-600" style="display: none;">⚠️ Logo not accessible</div>
                             </div>
                         </div>
                     </div>

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
                 </form>

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
                     <!-- Logo -->
                     <div class="mb-4 flex justify-center">
                         <img src="<?= htmlspecialchars(getLogoUrl()) ?>" 
                              alt="Logo" 
                              class="h-12 max-w-full object-contain"
                              onerror="this.style.display='none';">
                     </div>
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">Domains</h1>
                     <p class="text-gray-600">View and manage all your registered domains.</p>
                 </div>

                 <?php
                 // Pagination logic
                 $domainsPerPage = 25; // Number of domains per page
                 $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                 $totalDomains = count($allDomains);
                 $totalPages = ceil($totalDomains / $domainsPerPage);
                 $offset = ($currentPage - 1) * $domainsPerPage;
                 
                 // Sort domains alphabetically by domain name
                 usort($allDomains, function($a, $b) {
                     return strcasecmp($a['domainname'], $b['domainname']);
                 });
                 
                 // Get domains for current page
                 $domainsForPage = array_slice($allDomains, $offset, $domainsPerPage);
                 
                 // Get domain statistics for the stats cards
                 $domainStats = [
                     'total_projects' => 0,
                     'ended_projects' => 0,
                     'running_projects' => 0,
                     'pending_projects' => 0
                 ];

                 if (userHasSettings()) {
                     $userSettings = getUserSettings();
                     if ($userSettings) {
                         $domainStats['total_projects'] = count($allDomains);
                         
                         // Count domains by status
                         foreach ($allDomains as $domain) {
                             $status = strtolower($domain['status'] ?? 'unknown');
                             switch ($status) {
                                 case 'active':
                                     $domainStats['running_projects']++;
                                     break;
                                 case 'expired':
                                 case 'terminated':
                                 case 'cancelled':
                                     $domainStats['ended_projects']++;
                                     break;
                                 case 'pending':
                                 case 'pendingtransfer':
                                 case 'pendingregistration':
                                     $domainStats['pending_projects']++;
                                     break;
                                 default:
                                     $domainStats['pending_projects']++;
                                     break;
                             }
                         }
                     }
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
                         <div class="flex items-center justify-between mb-4">
                             <h3 class="text-lg font-semibold text-gray-900">All Domains</h3>
                             <div class="flex items-center space-x-4">
                                 <!-- Search Field -->
                                 <div class="relative">
                                     <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                         <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                                     </div>
                                     <input 
                                         type="text" 
                                         id="domainSearch" 
                                         placeholder="Search domains..." 
                                         class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm w-64"
                                     >
                                 </div>
                                 <span class="text-sm text-gray-500" id="domainCount"><?= $totalDomains ?> domains</span>
                             </div>
                         </div>
                         
                         <!-- Filters -->
                         <div class="flex items-center space-x-4">
                             <div class="text-sm font-medium text-gray-700">Filters:</div>
                             
                             <!-- Registrar Filter -->
                             <div class="relative">
                                 <select id="registrarFilter" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white">
                                     <option value="">All Registrars</option>
                                     <?php
                                     $registrars = array_unique(array_map(function($domain) {
                                         return $domain['registrar'] ?? 'Unknown';
                                     }, $allDomains));
                                     sort($registrars);
                                     foreach ($registrars as $registrar): ?>
                                         <option value="<?= htmlspecialchars(strtolower($registrar)) ?>"><?= htmlspecialchars($registrar) ?></option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                             
                             <!-- Expiry Filter -->
                             <div class="relative">
                                 <select id="expiryFilter" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white">
                                     <option value="">All Expiry Dates</option>
                                     <option value="expired">Expired</option>
                                     <option value="30days">Expiring in 30 days</option>
                                     <option value="90days">Expiring in 90 days</option>
                                     <option value="thisyear">Expiring this year</option>
                                     <option value="nextyear">Expiring next year</option>
                                 </select>
                             </div>
                             
                             <!-- Status Filter -->
                             <div class="relative">
                                 <select id="statusFilter" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 pr-8 focus:ring-2 focus:ring-primary-500 focus:border-transparent bg-white">
                                     <option value="">All Statuses</option>
                                     <?php
                                     $statuses = array_unique(array_map(function($domain) {
                                         return $domain['status'] ?? 'Unknown';
                                     }, $allDomains));
                                     sort($statuses);
                                     foreach ($statuses as $status): ?>
                                         <option value="<?= htmlspecialchars(strtolower($status)) ?>"><?= htmlspecialchars($status) ?></option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                             
                             <!-- Clear Filters -->
                             <button id="clearFilters" class="text-sm text-primary-600 hover:text-primary-700 font-medium px-3 py-1.5 border border-primary-200 rounded-lg hover:bg-primary-50 transition-colors">
                                 Clear Filters
                             </button>
                         </div>
                     </div>
                     
                     <?php if (!empty($allDomains)): ?>
                         <div class="overflow-x-auto">
                             <!-- Header -->
                             <div class="grid grid-cols-12 gap-0 bg-gray-50 border-b border-gray-200 sticky top-0 z-10">
                                 <div class="col-span-5 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain Name</div>
                                 <div class="col-span-3 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registrar</div>
                                 <div class="col-span-2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</div>
                                 <div class="col-span-2 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</div>
                             </div>
                             <!-- Body -->
                             <div id="domainsTableBody" class="bg-white divide-y divide-gray-200">
                                 <?php foreach ($domainsForPage as $domain): ?>
                                     <div class="grid grid-cols-12 gap-0 hover:bg-gray-50 transition-colors border-b border-gray-200" data-domain="<?= htmlspecialchars(strtolower($domain['domainname'])) ?>" data-registrar="<?= htmlspecialchars(strtolower($domain['registrar'] ?? '')) ?>" data-expiry="<?= htmlspecialchars(strtolower(!empty($domain['expirydate']) ? date('M j, Y', strtotime($domain['expirydate'])) : 'n/a')) ?>" data-status="<?= htmlspecialchars(strtolower($domain['status'] ?? '')) ?>">
                                         <div class="col-span-5 px-6 py-4 flex items-center">
                                             <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                 <i data-lucide="globe" class="w-4 h-4 text-primary-600"></i>
                                             </div>
                                             <div class="min-w-0 flex-1">
                                                 <div class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($domain['domainname']) ?></div>
                                             </div>
                                         </div>
                                         <div class="col-span-3 px-6 py-4 flex items-center">
                                             <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 truncate">
                                                 <?= htmlspecialchars($domain['registrar'] ?? 'Unknown') ?>
                                             </span>
                                         </div>
                                         <div class="col-span-2 px-6 py-4 flex items-center text-sm text-gray-900">
                                             <?php 
                                             if (!empty($domain['expirydate'])) {
                                                 $expiryDate = strtotime($domain['expirydate']);
                                                 $daysUntilExpiry = ceil(($expiryDate - time()) / (60 * 60 * 24));
                                                 $expiryClass = $daysUntilExpiry <= 30 ? 'text-red-600' : ($daysUntilExpiry <= 90 ? 'text-yellow-600' : 'text-gray-900');
                                                 echo '<span class="' . $expiryClass . '">' . date('M j, Y', $expiryDate) . '</span>';
                                             } else {
                                                 echo 'N/A';
                                             }
                                             ?>
                                         </div>
                                         <div class="col-span-2 px-6 py-4 flex items-center">
                                             <?php
                                             $status = $domain['status'] ?? 'Unknown';
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
                                             <a href="?view=domains&page=<?= $currentPage - 1 ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
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
                                             <a href="?view=domains&page=1" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
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
                                                 <a href="?view=domains&page=<?= $i ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                     <?= $i ?>
                                                 </a>
                                             <?php endif; ?>
                                         <?php endfor; ?>
                                         
                                         <?php if ($endPage < $totalPages): ?>
                                             <?php if ($endPage < $totalPages - 1): ?>
                                                 <span class="px-3 py-2 text-sm text-gray-500">...</span>
                                             <?php endif; ?>
                                             <a href="?view=domains&page=<?= $totalPages ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
                                                 <?= $totalPages ?>
                                             </a>
                                         <?php endif; ?>
                                         
                                         <!-- Next Page -->
                                         <?php if ($currentPage < $totalPages): ?>
                                             <a href="?view=domains&page=<?= $currentPage + 1 ?>" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition-colors">
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
                             <i data-lucide="globe" class="w-12 h-12 text-gray-400 mx-auto mb-4"></i>
                             <h3 class="text-lg font-medium text-gray-900 mb-2">No domains found</h3>
                             <p class="text-gray-500">No domains are currently registered in your WHMCS system.</p>
                         </div>
                     <?php endif; ?>
                 </div>

                 <?php elseif ($currentView === 'nameservers'): ?>
                 <!-- Nameservers Content -->
                 <!-- Page Header -->
                 <div class="mb-8">
                     <!-- Logo -->
                     <div class="mb-4 flex justify-center">
                         <img src="<?= htmlspecialchars(getLogoUrl()) ?>" 
                              alt="Logo" 
                              class="h-12 max-w-full object-contain"
                              onerror="this.style.display='none';">
                     </div>
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
                                 <label for="domain" class="block text-sm font-medium text-gray-700">Available Domains (<?= count($allDomains) ?> total)</label>
                                 <div class="flex gap-2">
                                     <button type="button" id="selectAllBtn" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3 py-1 rounded-md text-sm font-medium transition-colors">Select All</button>
                                     <button type="button" id="clearAllBtn" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-3 py-1 rounded-md text-sm font-medium transition-colors">Clear All</button>
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
                     <!-- Logo -->
                     <div class="mb-4 flex justify-center">
                         <img src="<?= htmlspecialchars(getLogoUrl()) ?>" 
                              alt="Logo" 
                              class="h-12 max-w-full object-contain"
                              onerror="this.style.display='none';">
                     </div>
                     <h1 class="text-2xl font-bold text-gray-900 mb-2">Export Domain Data</h1>
                     <p class="text-gray-600">Export your domain information to CSV format for analysis and reporting.</p>
                 </div>

                 <!-- Status Message -->
                 <?php if ($exportMessage): ?>
                     <div class="mb-6 p-4 rounded-lg <?= strpos($exportMessage, 'completed') !== false ? 'bg-green-50 border border-green-200 text-green-800' : (strpos($exportMessage, 'Error') !== false ? 'bg-red-50 border border-red-200 text-red-800' : 'bg-yellow-50 border border-yellow-200 text-yellow-800') ?>">
                         <div class="flex items-center space-x-3">
                             <i data-lucide="<?= strpos($exportMessage, 'completed') !== false ? 'check-circle' : (strpos($exportMessage, 'Error') !== false ? 'x-circle' : 'alert-triangle') ?>" class="w-5 h-5"></i>
                             <span><?= htmlspecialchars($exportMessage) ?></span>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Export Results (if any) -->
                 <?php if (!empty($exportResults)): ?>
                     <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                         <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                             <i data-lucide="file-text" class="w-5 h-5 text-primary-600"></i>
                             <span>Export Results - Batch <?= $exportResults['batch_number'] ?></span>
                         </h3>
                         <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                             <div class="bg-gray-50 p-4 rounded-lg text-center">
                                 <div class="text-2xl font-bold text-gray-900"><?= $exportResults['processed'] ?></div>
                                 <div class="text-sm text-gray-500">Total Processed</div>
                             </div>
                             <div class="bg-green-50 p-4 rounded-lg text-center">
                                 <div class="text-2xl font-bold text-green-600"><?= $exportResults['successful'] ?></div>
                                 <div class="text-sm text-green-600">Successful</div>
                             </div>
                             <div class="bg-red-50 p-4 rounded-lg text-center">
                                 <div class="text-2xl font-bold text-red-600"><?= $exportResults['errors'] ?></div>
                                 <div class="text-sm text-red-600">Errors</div>
                             </div>
                         </div>
                         <div class="flex items-center justify-between">
                             <div class="text-sm text-gray-600">
                                 <strong>File created:</strong> <?= htmlspecialchars($exportResults['filename']) ?>
                             </div>
                             <a href="<?= htmlspecialchars($exportResults['filename']) ?>" download class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                                 <i data-lucide="download" class="w-4 h-4"></i>
                                 <span>Download CSV</span>
                             </a>
                         </div>
                     </div>
                 <?php endif; ?>

                 <!-- Export Configuration -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200 mb-6">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="settings" class="w-5 h-5 text-primary-600"></i>
                         <span>Batch Export Configuration</span>
                     </h3>
                     
                     <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                         <div class="flex items-start space-x-3">
                             <i data-lucide="lightbulb" class="w-5 h-5 text-blue-600 mt-0.5"></i>
                             <div>
                                 <h4 class="font-semibold text-blue-800 mb-1">Batch Processing</h4>
                                 <p class="text-sm text-blue-700">Domains are exported in batches of 200 to prevent timeouts. Each batch creates a separate CSV file.</p>
                            </div>
                        </div>
                    </div>

                     <form method="POST" class="space-y-6">
                         <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                             <div>
                                 <div class="flex items-center space-x-2 mb-2">
                                     <label for="batch_number" class="text-sm font-medium text-gray-700">Batch Number</label>
                                     <div class="relative group">
                                         <i data-lucide="info" class="w-4 h-4 text-gray-400 cursor-help"></i>
                                         <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10 w-64">
                                             Specify which batch of domains to export (200 domains per batch)
                                             <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                                         </div>
                                     </div>
                                 </div>
                                 <input 
                                     type="number" 
                                     name="batch_number" 
                                     id="batch_number" 
                                     class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                     min="1" 
                                     value="1"
                                     required
                                 >
                             </div>
                             <div class="flex items-end">
                                 <button type="submit" name="export_csv" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                                     <i data-lucide="download" class="w-4 h-4"></i>
                                     <span>Export Batch</span>
                                 </button>
                             </div>
                         </div>
                     </form>

                     <!-- Batch Information -->
                     <div class="bg-primary-50 border border-primary-200 rounded-lg p-4 mt-6">
                         <h4 class="font-semibold text-primary-800 mb-2">Batch Breakdown</h4>
                         <div class="text-sm text-primary-700 space-y-1">
                             <div>• Batch 1: Domains 1-200</div>
                             <div>• Batch 2: Domains 201-400</div>
                             <div>• Batch 3: Domains 401-600</div>
                             <div>• And so on...</div>
                         </div>
                     </div>
                 </div>

                 <!-- Export Information -->
                 <div class="bg-white p-6 rounded-xl border border-gray-200">
                     <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                         <i data-lucide="info" class="w-5 h-5 text-primary-600"></i>
                         <span>Export Details</span>
                        </h3>
                     
                     <div class="bg-gray-50 rounded-lg p-6">
                         <h4 class="font-semibold text-gray-900 mb-4">CSV File Contents</h4>
                         <p class="text-gray-600 text-sm mb-4">Each exported CSV file will contain the following information for active domains:</p>
                         
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
                                 <strong>Note:</strong> Only domains with "Active" status will be included in the export.
                             </div>
                         </div>
                     </div>
                 </div>
                 
                 <?php endif; ?>
             </main>
         </div>
     </div>

    <script src="js/dashboard.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Domain search and filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('domainSearch');
            const registrarFilter = document.getElementById('registrarFilter');
            const expiryFilter = document.getElementById('expiryFilter');
            const statusFilter = document.getElementById('statusFilter');
            const clearFiltersBtn = document.getElementById('clearFilters');
            const domainsTableBody = document.getElementById('domainsTableBody');
            const domainCount = document.getElementById('domainCount');
            
            if (domainsTableBody) {
                const domainRows = domainsTableBody.querySelectorAll('[data-domain]');
                const totalCount = domainRows.length;
                
                // Function to check if domain matches expiry filter
                function matchesExpiryFilter(expiryDateStr, filterValue) {
                    if (!filterValue || !expiryDateStr || expiryDateStr === 'n/a') return true;
                    
                    const expiryDate = new Date(expiryDateStr);
                    const today = new Date();
                    const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                    
                    switch(filterValue) {
                        case 'expired':
                            return daysUntilExpiry < 0;
                        case '30days':
                            return daysUntilExpiry >= 0 && daysUntilExpiry <= 30;
                        case '90days':
                            return daysUntilExpiry >= 0 && daysUntilExpiry <= 90;
                        case 'thisyear':
                            return expiryDate.getFullYear() === today.getFullYear();
                        case 'nextyear':
                            return expiryDate.getFullYear() === today.getFullYear() + 1;
                        default:
                            return true;
                    }
                }
                
                // Function to apply all filters
                function applyFilters() {
                    const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
                    const registrarValue = registrarFilter ? registrarFilter.value : '';
                    const expiryValue = expiryFilter ? expiryFilter.value : '';
                    const statusValue = statusFilter ? statusFilter.value : '';
                    
                    let visibleCount = 0;
                    
                    domainRows.forEach(function(row) {
                        const domainName = row.getAttribute('data-domain') || '';
                        const registrar = row.getAttribute('data-registrar') || '';
                        const expiryDate = row.getAttribute('data-expiry') || '';
                        const status = row.getAttribute('data-status') || '';
                        
                        // Check search term
                        const matchesSearch = searchTerm === '' || 
                                            domainName.includes(searchTerm) || 
                                            registrar.includes(searchTerm) || 
                                            expiryDate.includes(searchTerm) || 
                                            status.includes(searchTerm);
                        
                        // Check registrar filter
                        const matchesRegistrar = registrarValue === '' || registrar === registrarValue;
                        
                        // Check expiry filter
                        const matchesExpiry = matchesExpiryFilter(expiryDate, expiryValue);
                        
                        // Check status filter
                        const matchesStatus = statusValue === '' || status === statusValue;
                        
                        // Show row only if all conditions match
                        const shouldShow = matchesSearch && matchesRegistrar && matchesExpiry && matchesStatus;
                        
                        if (shouldShow) {
                            row.style.display = 'grid';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Update domain count
                    if (domainCount) {
                        const hasActiveFilters = searchTerm !== '' || registrarValue !== '' || expiryValue !== '' || statusValue !== '';
                        if (hasActiveFilters) {
                            domainCount.textContent = visibleCount + ' of ' + totalCount + ' domains';
                        } else {
                            domainCount.textContent = totalCount + ' domains';
                        }
                    }
                }
                
                // Add event listeners
                if (searchInput) {
                    searchInput.addEventListener('input', applyFilters);
                }
                if (registrarFilter) {
                    registrarFilter.addEventListener('change', applyFilters);
                }
                if (expiryFilter) {
                    expiryFilter.addEventListener('change', applyFilters);
                }
                if (statusFilter) {
                    statusFilter.addEventListener('change', applyFilters);
                }
                if (clearFiltersBtn) {
                    clearFiltersBtn.addEventListener('click', function() {
                        if (searchInput) searchInput.value = '';
                        if (registrarFilter) registrarFilter.value = '';
                        if (expiryFilter) expiryFilter.value = '';
                        if (statusFilter) statusFilter.value = '';
                        applyFilters();
                    });
                }
            }
        });
    </script>
    

</body>
</html> 