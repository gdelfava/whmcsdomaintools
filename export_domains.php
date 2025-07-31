<?php
require_once 'api.php';
require_once 'user_settings_db.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

// Check if user has configured their API settings
if (!userHasSettings()) {
    header('Location: settings.php?redirect=export_domains.php');
    exit;
}

// Load user settings
    $userSettings = getUserSettingsDB();
if (!$userSettings) {
    $message = 'Unable to load your API settings. Please configure them first.';
}

// === CSV Export Logic ===
if (isset($_POST['export_csv'])) {
    // Get batch parameters
    $batchSize = 200; // Keep at 200 to avoid timeouts
    $batchNumber = isset($_POST['batch_number']) ? (int)$_POST['batch_number'] : 1;
    $offset = ($batchNumber - 1) * $batchSize;
    
    // Immediately show the export page
    echo '<!DOCTYPE html>';
    echo '<html lang="en"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Exporting Domains - WHMCS Domain Tools</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="css/main.css">';
    echo '</head>';
    echo '<body>';
    echo '<div class="page-wrapper">';
    echo '<div class="container">';
    echo '<div class="main-card">';
    
    // Header
    echo '<div class="card-header">';
    echo '<div class="card-header-content">';
    echo '<h1 class="page-title">📁 Exporting Domains</h1>';
    echo '<p class="page-subtitle">Batch ' . $batchNumber . ' - Processing domains ' . ($offset + 1) . ' to ' . ($offset + $batchSize) . '</p>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="card-body">';
    echo '<div class="flex justify-between items-center mb-6">';
    echo '<div></div>'; // spacer
    echo '<a href="export_domains.php" class="btn btn-secondary">Cancel Export</a>';
    echo '</div>';
    
    echo '<div class="alert alert-info">';
    echo '<div class="flex items-center gap-3">';
    echo '<span style="font-size: 1.5rem;">📋</span>';
    echo '<div>';
    echo '<div class="font-semibold">Export Process Started</div>';
    echo '<div class="text-sm mt-1">Processing batch ' . $batchNumber . '... Please wait while we gather your domain information.</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Force output to display immediately
    if (ob_get_level()) ob_end_flush();
    flush();
    
    // Get domains with offset (limit to 200 per batch)
    echo '<div class="alert alert-info mt-4">';
    echo '<div class="flex items-center gap-3">';
    echo '<span style="font-size: 1.25rem;">🔄</span>';
    echo '<div><strong>Step 1:</strong> Getting domain list from WHMCS (Batch ' . $batchNumber . ', Offset: ' . $offset . ')...</div>';
    echo '</div>';
    echo '</div>';
    flush();
    
    $domainsResponse = getDomainsForExport($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $batchSize, $offset);
    
    // Display debug information if available
    if (isset($domainsResponse['debug_info']) && !empty($domainsResponse['debug_info'])) {
        echo '<div class="alert alert-info mt-4">';
        echo '<div class="flex items-center gap-3">';
        echo '<span style="font-size: 1.25rem;">🔍</span>';
        echo '<div>';
        echo '<div class="font-semibold">API Debug Information</div>';
        echo '<div class="text-sm mt-1 space-y-1">';
        foreach ($domainsResponse['debug_info'] as $debugLine) {
            echo '<div class="text-gray-600">' . htmlspecialchars($debugLine) . '</div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        flush();
    }
    
    if (!isset($domainsResponse['domains']['domain']) || $domainsResponse['result'] !== 'success') {
        echo '<div class="alert alert-error mt-4">';
        echo '<div class="flex items-center gap-3">';
        echo '<span style="font-size: 1.25rem;">❌</span>';
        echo '<div>';
        echo '<div class="font-semibold">Error: Could not fetch domains from WHMCS API</div>';
        if (isset($domainsResponse['message'])) {
            echo '<div class="text-sm mt-1">API Message: ' . htmlspecialchars($domainsResponse['message']) . '</div>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="mt-6 text-center">';
        echo '<a href="export_domains.php" class="btn btn-secondary">🔙 Go Back</a>';
        echo '</div></div></div></body></html>';
        exit;
    }
    
    $domains = $domainsResponse['domains']['domain'];
    $totalInBatch = count($domains);
    
    echo '<div class="alert alert-success mt-4">';
    echo '<div class="flex items-center gap-3">';
    echo '<span style="font-size: 1.25rem;">✅</span>';
    echo '<div>';
    echo '<div class="font-semibold">Step 1 Complete: Found ' . $totalInBatch . ' domains in batch ' . $batchNumber . '</div>';
    if ($totalInBatch < $batchSize) {
        echo '<div class="text-sm mt-1 text-warning-600"><strong>ℹ️ Note:</strong> This batch has fewer than ' . $batchSize . ' domains, indicating this may be the last batch.</div>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';
    flush();
    
    // Process all domains (not just active ones)
    echo '<div class="alert alert-info mt-4">';
    echo '<div class="flex items-center gap-3">';
    echo '<span style="font-size: 1.25rem;">🔄</span>';
    echo '<div><strong>Step 2:</strong> Processing all domains (including expired, pending, etc.)...</div>';
    echo '</div>';
    echo '</div>';
    flush();
    
    $allDomains = $domains; // Use all domains, not just active ones
    
    // Count domains by status for reporting
    $statusCounts = [];
    foreach ($allDomains as $domain) {
        $status = $domain['status'] ?? 'Unknown';
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }
    
    echo '<div style="background:#e8f5e8; padding:15px; border-radius:6px; border-left:4px solid #4caf50; margin-top:15px;">';
    echo '<p style="margin:0; color:#2e7d32;"><strong>✅ Step 2 Complete:</strong> Processing ' . count($allDomains) . ' domains in this batch</p>';
    echo '<div style="margin-top:8px; font-size:12px; color:#2e7d32;">';
    echo '<strong>Status breakdown:</strong> ';
    $statusParts = [];
    foreach ($statusCounts as $status => $count) {
        $statusParts[] = $status . ': ' . $count;
    }
    echo implode(', ', $statusParts);
    echo '</div>';
    echo '</div>';
    flush();
    
    if (count($allDomains) === 0) {
        echo '<div style="background:#fff3cd; padding:15px; border-radius:6px; border-left:4px solid #ffc107; margin-top:15px;">';
        echo '<p style="margin:0; color:#856404;"><strong>⚠️ Warning:</strong> No domains found in this batch to export.</p>';
        echo '</div>';
        echo '<p style="margin-top:20px;"><a href="export_domains.php" style="background:#6c757d;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;">🔙 Go Back</a></p>';
        echo '</div></body></html>';
        exit;
    }
    
    // Create CSV file with batch number
    $filename = 'domains_all_batch' . $batchNumber . '_' . date('Y-m-d_H-i-s') . '.csv';
    $file = fopen($filename, 'w');
    fputcsv($file, ['Domain Name', 'Domain ID', 'Status', 'NS1', 'NS2', 'NS3', 'NS4', 'NS5', 'Notes', 'Batch Number']);
    
    echo '<div style="background:#f8f9fa; padding:15px; border-radius:6px; border-left:4px solid #4f8cff; margin-top:15px;">';
    echo '<p style="margin:0;"><strong>🔄 Step 3:</strong> Processing nameservers for each domain in batch ' . $batchNumber . '...</p>';
    echo '<div style="margin-top:10px; font-size:14px;">';
    flush();
    
    $processed = 0;
    $successful = 0;
    $errors = 0;
    
    foreach ($allDomains as $domain) {
        $domainName = $domain['domainname'] ?? 'Unknown';
        $domainId = $domain['id'] ?? null;
        $domainStatus = $domain['status'] ?? 'Unknown';
        
        echo '<div style="padding:8px; margin:5px 0; background:#f0f0f0; border-radius:4px;">';
        echo '🔄 <strong>(' . ($processed + 1) . '/' . count($allDomains) . '):</strong> ' . htmlspecialchars($domainName);
        flush();
        
        if (!$domainId) {
            fputcsv($file, [$domainName, 'N/A', $domainStatus, 'ERROR', 'No domain ID found', '', '', '', 'Could not fetch nameservers', $batchNumber]);
            echo ' → <span style="color:#d32f2f;">❌ No domain ID</span>';
            $errors++;
        } else {
                            $nsResponse = getDomainNameservers($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $domainId);
            
            if (isset($nsResponse['result']) && $nsResponse['result'] === 'success') {
                fputcsv($file, [
                    $domainName, $domainId, $domainStatus,
                    $nsResponse['ns1'] ?? '', $nsResponse['ns2'] ?? '', $nsResponse['ns3'] ?? '',
                    $nsResponse['ns4'] ?? '', $nsResponse['ns5'] ?? '', 'Success', $batchNumber
                ]);
                echo ' → <span style="color:#2e7d32;">✅ Success</span>';
                $successful++;
            } else {
                $errorMsg = $nsResponse['message'] ?? 'Unknown error';
                fputcsv($file, [$domainName, $domainId, $domainStatus, 'ERROR', $errorMsg, '', '', '', 'Failed to get nameservers', $batchNumber]);
                echo ' → <span style="color:#d32f2f;">❌ ' . htmlspecialchars($errorMsg) . '</span>';
                $errors++;
            }
        }
        
        $processed++;
        echo '</div>';
        flush();
        usleep(250000); // 0.25 second delay
    }
    
    fclose($file);
    
    echo '</div></div>'; // Close progress divs
    
    echo '<div style="background:#e8f5e8; padding:20px; border-radius:6px; border-left:4px solid #4caf50; margin-top:20px;">';
    echo '<h3 style="color:#2e7d32; margin:0 0 15px 0;">🎉 Batch ' . $batchNumber . ' Export Complete!</h3>';
    echo '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px; margin-bottom:15px;">';
    echo '<div style="text-align:center; padding:10px; background:rgba(76,175,80,0.1); border-radius:4px;">';
    echo '<div style="font-size:24px; font-weight:bold; color:#2e7d32;">' . $processed . '</div>';
    echo '<div style="font-size:12px; color:#666;">Total Processed</div></div>';
    echo '<div style="text-align:center; padding:10px; background:rgba(76,175,80,0.1); border-radius:4px;">';
    echo '<div style="font-size:24px; font-weight:bold; color:#2e7d32;">' . $successful . '</div>';
    echo '<div style="font-size:12px; color:#666;">Successful</div></div>';
    echo '<div style="text-align:center; padding:10px; background:rgba(244,67,54,0.1); border-radius:4px;">';
    echo '<div style="font-size:24px; font-weight:bold; color:#d32f2f;">' . $errors . '</div>';
    echo '<div style="font-size:12px; color:#666;">Errors</div></div></div>';
    echo '<p style="margin:0; font-weight:500;">📁 <strong>CSV file created:</strong> ' . $filename . '</p>';
    
    // Show navigation for next/previous batches
    if ($totalInBatch >= $batchSize || $batchNumber > 1) {
        echo '<div style="background:#f8f9fa; padding:15px; border-radius:6px; margin-top:15px;">';
        echo '<h4 style="margin:0 0 10px 0; color:#333;">📄 Export More Batches:</h4>';
        echo '<form method="POST" style="display:inline-block; margin-right:10px;">';
        if ($batchNumber > 1) {
            echo '<input type="hidden" name="batch_number" value="' . ($batchNumber - 1) . '">';
            echo '<input type="submit" name="export_csv" value="← Previous Batch (' . ($batchNumber - 1) . ')" style="background:#6c757d;color:white;padding:8px 12px;border:none;border-radius:4px;cursor:pointer;">';
        }
        echo '</form>';
        echo '<form method="POST" style="display:inline-block;">';
        if ($totalInBatch >= $batchSize) {
            echo '<input type="hidden" name="batch_number" value="' . ($batchNumber + 1) . '">';
            echo '<input type="submit" name="export_csv" value="Next Batch (' . ($batchNumber + 1) . ') →" style="background:#4f8cff;color:white;padding:8px 12px;border:none;border-radius:4px;cursor:pointer;">';
        }
        echo '</form>';
        echo '</div>';
    }
    
    echo '</div>';
    
    echo '<div style="margin-top:20px; text-align:center;">';
    echo '<a href="' . $filename . '" download style="background:#4f8cff;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;margin-right:10px;display:inline-block;">📥 Download CSV File</a>';
    echo '<a href="export_domains.php" style="background:#6c757d;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;display:inline-block;">🔙 Go Back</a>';
    echo '</div></div></body></html>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Export Domain Data</title>
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
                        <div class="text-xs text-gray-500">Data Export</div>
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
                <span>Export Domains</span>
            </nav>

            <!-- Main Card -->
            <div class="main-card">
                <!-- Card Header -->
                <div class="card-header">
                    <div class="card-header-content">
                        <h1 class="page-title">📁 Export Domain Data</h1>
                        <p class="page-subtitle">Export your domain information to CSV format for analysis and reporting</p>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <!-- Export Section -->
                    <div class="card-section">
                        <h3 class="section-title">
                            <span class="icon">📊</span>
                            Batch Export Configuration
                        </h3>
                        
                        <div class="alert alert-info mb-6">
                            <div class="flex items-center gap-3">
                                <span style="font-size: 1.5rem;">💡</span>
                                <div>
                                    <div class="font-semibold">Batch Processing</div>
                                    <div class="text-sm mt-1">Domains are exported in batches of 200 to prevent timeouts. Each batch creates a separate CSV file.</div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                                <div class="form-group">
                                    <label for="batch_number" class="form-label">Batch Number</label>
                                    <input 
                                        type="number" 
                                        name="batch_number" 
                                        id="batch_number" 
                                        class="form-input"
                                        min="1" 
                                        value="1"
                                        required
                                    >
                                    <div class="form-help">Specify which batch of domains to export (200 domains per batch)</div>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="export_csv" class="btn btn-primary w-full">
                                        <span>📥</span>
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
                    <div class="card-section">
                        <h3 class="section-title">
                            <span class="icon">ℹ️</span>
                            Export Details
                        </h3>
                        
                        <div class="bg-white rounded-lg border border-gray-200 p-6">
                            <h4 class="font-semibold text-gray-900 mb-4">CSV File Contents</h4>
                            <p class="text-gray-600 text-sm mb-4">Each exported CSV file will contain the following information for active domains:</p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                        <span class="text-sm">Domain Name</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                        <span class="text-sm">Domain ID</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                        <span class="text-sm">Status</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 bg-primary-500 rounded-full"></span>
                                        <span class="text-sm">Nameservers (NS1-NS5)</span>
                                    </div>
                                    <div class="flex items-center gap-2">
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
                </div>
            </div>
        </div>
    </div>

    <style>
        .space-y-1 > * + * { margin-top: 0.25rem; }
        .space-y-2 > * + * { margin-top: 0.5rem; }
        .space-y-4 > * + * { margin-top: 1rem; }
        .space-y-6 > * + * { margin-top: 1.5rem; }
        .bg-primary-50 { background-color: var(--primary-50); }
        .bg-primary-500 { background-color: var(--primary-500); }
        .border-primary-200 { border-color: var(--primary-200); }
        .text-primary-700 { color: var(--primary-700); }
        .text-primary-800 { color: var(--primary-800); }
        .bg-yellow-50 { background-color: #fffbeb; }
        .border-yellow-200 { border-color: #fde68a; }
        .text-yellow-800 { color: #92400e; }
        .w-2 { width: 0.5rem; }
        .h-2 { height: 0.5rem; }
        .rounded-full { border-radius: 9999px; }
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .gap-2 { gap: 0.5rem; }
        .gap-4 { gap: 1rem; }
        .gap-6 { gap: 1.5rem; }
        .items-end { align-items: flex-end; }
        @media (min-width: 768px) {
            .md\\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</body>
</html> 