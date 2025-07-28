<?php
require_once 'auth.php';
require_once 'api.php';
require_once 'user_settings.php';

// Require authentication
requireAuth();

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

// Check if user has configured their API settings
if (!userHasSettings()) {
    header('Location: settings.php?redirect=update_nameservers.php');
    exit;
}

// Load user settings
$userSettings = getUserSettings();
if (!$userSettings) {
    $message = 'Unable to load your API settings. Please configure them first.';
    $allDomains = [];
} else {
    // === MAIN LOGIC ===
    $response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);
    $allDomains = $response['domains']['domain'] ?? [];
    
    // Clear cache if debug mode is enabled
    if (isset($_GET['clear_cache'])) {
        $cache = new SimpleCache();
        $cacheKey = 'all_domains_' . md5($userSettings['api_url'] . $userSettings['api_identifier']);
        $cache->delete($cacheKey, $_SESSION['user_email']);
        $message = 'Cache cleared. Refreshing...';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?view=update_nameservers');
        exit;
    }
}

// Sort domains alphabetically by domainname (case-insensitive)
usort($allDomains, function($a, $b) {
    $domainA = strtolower($a['domainname'] ?? '');
    $domainB = strtolower($b['domainname'] ?? '');
    return strcmp($domainA, $domainB);
});

// Debug: Log the first few domains to verify sorting (only in debug mode)
if (isset($_GET['debug']) && count($allDomains) > 0) {
    error_log("Update Nameservers - First 5 domains after sorting:");
    for ($i = 0; $i < min(5, count($allDomains)); $i++) {
        error_log("  " . ($i + 1) . ". " . $allDomains[$i]['domainname']);
    }
}

$message = '';
$updateResults = [];

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
    $message = "Batch update completed: $successCount successful, $failureCount failed out of $totalUpdated total domains.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Update Nameservers</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <div class="page-wrapper">
        <div class="container">
            <!-- Navigation Bar -->
            <nav class="navbar">
                <div class="navbar-brand">
                    <img src="<?= htmlspecialchars(getLogoUrl()) ?>" alt="Logo" onerror="this.style.display='none'">
                    <div>
                        <div class="font-semibold text-gray-900">WHMCS Domain Tools</div>
                        <div class="text-xs text-gray-500">Nameserver Management</div>
                    </div>
                </div>
                <div class="navbar-user">
                    <span>Logged in as <span class="user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></span></span>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="logout" class="logout-btn">Logout</button>
                    </form>
                </div>
            </nav>

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <a href="main_page.php">Dashboard</a>
                <span class="breadcrumb-separator">/</span>
                <span>Update Nameservers</span>
            </nav>

            <!-- Main Card -->
            <div class="main-card">
                <!-- Card Header -->
                <div class="card-header">
                    <div class="card-header-content">
                        <h1 class="page-title">🌐 Update Nameservers</h1>
                        <p class="page-subtitle">Batch update nameservers for multiple domains simultaneously</p>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <!-- Status Message -->
                    <?php if ($message): ?>
                        <div class="alert <?= strpos($message, 'successful') !== false ? 'alert-success' : 'alert-error' ?>">
                            <div class="flex items-center gap-3">
                                <span style="font-size: 1.25rem;">
                                    <?= strpos($message, 'successful') !== false ? '✅' : '⚠️' ?>
                                </span>
                                <div><?= $message ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Configuration Info -->
                    <?php if ($userSettings): ?>
                        <div class="card-section">
                            <h3 class="section-title">
                                <span class="icon">ℹ️</span>
                                Current Configuration
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <div class="text-sm text-gray-500">Primary Nameserver</div>
                                    <div class="font-semibold text-gray-900"><?= htmlspecialchars($userSettings['default_ns1']) ?></div>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <div class="text-sm text-gray-500">Secondary Nameserver</div>
                                    <div class="font-semibold text-gray-900"><?= htmlspecialchars($userSettings['default_ns2']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Detailed Results (if any) -->
                    <?php if (!empty($updateResults)): ?>
                        <div class="results-section">
                            <div class="results-header">
                                <h3>📋 Update Results (<?= count($updateResults) ?> domains processed)</h3>
                            </div>
                            <div class="results-container">
                                <?php foreach ($updateResults as $result): ?>
                                    <div class="result-item <?= $result['status'] === 'SUCCESS' ? 'success' : 'error' ?>">
                                        <div class="result-domain">
                                            <?= htmlspecialchars($result['domain']) ?>
                                        </div>
                                        <div class="result-status">
                                            <span class="result-status-text <?= $result['status'] === 'SUCCESS' ? 'success' : 'error' ?>">
                                                <?= $result['status'] ?>
                                            </span>
                                            <?php if (!empty($result['message'])): ?>
                                                <span class="result-message" title="<?= htmlspecialchars($result['message']) ?>">
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
                    <div class="card-section">
                        <h3 class="section-title">
                            <span class="icon">🎯</span>
                            Select Domains to Update
                        </h3>

                        <form method="POST" class="space-y-6">
                            <div class="form-group">
                                <div class="flex justify-between items-center mb-3">
                                    <label for="domain" class="form-label">Available Domains (<?= count($allDomains) ?> total, sorted alphabetically)</label>
                                                                    <div class="flex gap-2">
                                    <button type="button" id="selectAllBtn" class="btn btn-sm btn-outline">Select All</button>
                                    <button type="button" id="clearAllBtn" class="btn btn-sm btn-secondary">Clear All</button>
                                    <button type="button" onclick="showCacheModal()" class="btn btn-sm btn-warning">Clear Cache</button>
                                </div>
                                </div>
                                
                                <select name="domain[]" id="domain" required multiple class="form-select" style="min-height: 300px;">
                                    <?php foreach ($allDomains as $d): ?>
                                        <option value="<?= htmlspecialchars($d['domainname']) ?>">
                                            <?= htmlspecialchars($d['domainname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <div class="form-help">
                                    <div class="flex items-center justify-between text-xs">
                                        <span>Hold <strong>Ctrl/Cmd</strong> to select multiple domains</span>
                                        <span id="selectionCount" class="font-medium">0 domains selected</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-center">
                                <button type="submit" name="update" id="updateDomainsBtn" class="btn btn-primary btn-lg">
                                    <span>🚀</span>
                                    <span>Update Selected Domains</span>
                                </button>
                            </div>
                            
                            <div class="text-center mt-4">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-center justify-center gap-2 mb-2">
                                        <span style="font-size: 1.25rem;">⏳</span>
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
                    <div class="card-section">
                        <h3 class="section-title">
                            <span class="icon">📜</span>
                            Activity Log
                        </h3>
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
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
                                        
                                        // Debug: Show which log file we're looking for
                                        if (isset($_GET['debug'])) {
                                            echo '<tr><td colspan="3" class="px-4 py-2 text-xs text-gray-500">Debug: Looking for log file: ' . htmlspecialchars($logFile) . '</td></tr>';
                                            echo '<tr><td colspan="3" class="px-4 py-2 text-xs text-gray-500">Debug: User email: ' . htmlspecialchars($_SESSION['user_email'] ?? 'not set') . '</td></tr>';
                                        }
                                        
                                        if (file_exists($logFile)) {
                                            $logLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                                            if (!empty($logLines)) {
                                                // Reverse array to show newest entries first
                                                $logLines = array_reverse($logLines);
                                                
                                                if (isset($_GET['debug'])) {
                                                    echo '<tr><td colspan="3" class="px-4 py-2 text-xs text-gray-500">Debug: Found ' . count($logLines) . ' log lines</td></tr>';
                                                }
                                                
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
                                                    } else {
                                                        // Debug: Show lines that don't match the pattern
                                                        if (isset($_GET['debug'])) {
                                                            echo '<tr><td colspan="3" class="px-4 py-2 text-xs text-gray-500">Debug: Line not parsed: ' . htmlspecialchars($line) . '</td></tr>';
                                                        }
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
    <style>
        .space-y-6 > * + * {
            margin-top: 1.5rem;
        }
        .grid {
            display: grid;
        }
        .grid-cols-1 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        .grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .gap-2 {
            gap: 0.5rem;
        }
        .gap-3 {
            gap: 0.75rem;
        }
        .gap-4 {
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .md\\:grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .bg-white {
            background-color: white;
        }
        .border-gray-200 {
            border-color: var(--gray-200);
        }
        .text-gray-500 {
            color: var(--gray-500);
        }
        .text-gray-900 {
            color: var(--gray-900);
        }
        .text-gray-100 {
            color: var(--gray-100);
        }
        .bg-gray-900 {
            background-color: var(--gray-900);
        }
        .font-mono {
            font-family: var(--font-mono);
        }
        .text-xs {
            font-size: 0.75rem;
        }
        .justify-center {
            justify-content: center;
        }
        .justify-between {
            justify-content: space-between;
        }
        
        /* Table Styles */
        .w-full {
            width: 100%;
        }
        
        .table {
            border-collapse: collapse;
        }
        
        .divide-y > * + * {
            border-top: 1px solid var(--gray-200);
        }
        
        .divide-gray-200 > * + * {
            border-top-color: var(--gray-200);
        }
        
        .px-4 {
            padding-left: var(--space-4);
            padding-right: var(--space-4);
        }
        
        .py-3 {
            padding-top: var(--space-3);
            padding-bottom: var(--space-3);
        }
        
        .py-0\.5 {
            padding-top: 0.125rem;
            padding-bottom: 0.125rem;
        }
        
        .py-8 {
            padding-top: var(--space-8);
            padding-bottom: var(--space-8);
        }
        
        .text-left {
            text-align: left;
        }
        
        .text-xs {
            font-size: 0.75rem;
        }
        
        .text-sm {
            font-size: 0.875rem;
        }
        
        .font-semibold {
            font-weight: 600;
        }
        
        .font-medium {
            font-weight: 500;
        }
        
        .text-gray-700 {
            color: var(--gray-700);
        }
        
        .text-gray-900 {
            color: var(--gray-900);
        }
        
        .text-gray-500 {
            color: var(--gray-500);
        }
        
        .text-green-600 {
            color: #16a34a;
        }
        
        .text-red-600 {
            color: #dc2626;
        }
        
        .bg-gray-50 {
            background-color: var(--gray-50);
        }
        
        .bg-green-50 {
            background-color: #f0fdf4;
        }
        
        .bg-red-50 {
            background-color: #fef2f2;
        }
        
        .border-b {
            border-bottom-width: 1px;
        }
        
        .border-gray-200 {
            border-color: var(--gray-200);
        }
        
        .rounded-full {
            border-radius: 9999px;
        }
        
        .tracking-wider {
            letter-spacing: 0.05em;
        }
        
        .uppercase {
            text-transform: uppercase;
        }
        
        .inline-flex {
            display: inline-flex;
        }
        
        .items-center {
            align-items: center;
        }
        
        .mr-1 {
            margin-right: 0.25rem;
        }
        
        .mt-1 {
            margin-top: 0.25rem;
        }
        
        .hover\\:bg-gray-50:hover {
            background-color: var(--gray-50);
        }
        
        .colspan-3 {
            grid-column: span 3 / span 3;
        }
        
        .text-center {
            text-align: center;
        }
        
        .overflow-hidden {
            overflow: hidden;
        }
        
        .overflow-x-auto {
            overflow-x: auto;
        }
    </style>
    
    <!-- Cache Modal Script -->
    <script src="js/cache-modal.js"></script>
</body>
</html> 