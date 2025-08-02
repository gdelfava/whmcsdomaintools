<?php
require_once 'auth_v2.php';
require_once 'api.php';
require_once 'user_settings_db.php';

// Check if this is an AJAX request
$isAjax = isset($_POST['action']);

// For development/testing purposes, allow export without authentication
// TODO: Remove this in production
if (!isLoggedIn()) {
    // Set default session values for testing
    $_SESSION['user_id'] = 1;
    $_SESSION['user_email'] = 'test@example.com';
    $_SESSION['company_id'] = 1;
    $_SESSION['user_role'] = 'server_admin';
}

// Require authentication (commented out for testing)
// if (!isLoggedIn()) {
//     if ($isAjax) {
//         echo json_encode(['error' => 'Please log in first.']);
//         exit;
//     } else {
//         requireAuth();
//     }
// }

// Increase PHP timeout limits
ini_set('max_execution_time', 1200);
ini_set('memory_limit', '1024M');
set_time_limit(1200);

// Set FastCGI timeout headers
if (function_exists('fastcgi_finish_request')) {
    header('X-FastCGI-Timeout: 1200');
}

ignore_user_abort(true);

// Handle AJAX requests
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'progress') {
        $batchNumber = (int)$_POST['batch_number'];
        $currentDomain = (int)$_POST['current_domain'];
        $totalDomains = (int)$_POST['total_domains'];
        
        // Get user settings
        $userSettings = getUserSettingsDB();
        if (!$userSettings) {
            echo json_encode(['error' => 'No API settings found']);
            exit;
        }
        
        // Process next domain
        $batchSize = 50;
        $offset = ($batchNumber - 1) * $batchSize;
        
        // Get domains for this batch
        $domainsResponse = getDomainsForExport($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $batchSize, $offset);
        
        if (!isset($domainsResponse['domains']['domain'])) {
            $errorMsg = 'Could not fetch domains';
            if (isset($domainsResponse['message'])) {
                $errorMsg .= ': ' . $domainsResponse['message'];
            }
            echo json_encode(['error' => $errorMsg]);
            exit;
        }
        
        $domains = $domainsResponse['domains']['domain'];
        
        // Debug: Log current domain and total domains
        error_log("Export progress: Batch $batchNumber - Current domain: $currentDomain, Total domains: " . count($domains));
        
        // Check if we should complete the export
        $shouldComplete = ($currentDomain >= count($domains)) || ($currentDomain >= 49); // Force completion after 49 domains
        
        if ($shouldComplete) {
            // Export complete - generate CSV file
            $filename = 'exports/domains_progress_batch' . $batchNumber . '_' . date('Y-m-d_H-i-s') . '.csv';
            
            // Read the session data to get all results
            $allResults = $_SESSION['export_results_' . $batchNumber] ?? [];
            
            // Debug: Log the completion
            error_log("Export progress: Batch $batchNumber completed with " . count($allResults) . " results");
            
            // Create CSV file
            $file = fopen($filename, 'w');
            if ($file === false) {
                error_log("Export progress: Failed to create CSV file: $filename");
                echo json_encode([
                    'status' => 'complete',
                    'message' => 'Export completed but failed to create CSV file',
                    'filename' => '',
                    'total_processed' => count($allResults)
                ]);
                exit;
            }
            
            fputcsv($file, ['Domain Name', 'Domain ID', 'Status', 'NS1', 'NS2', 'NS3', 'NS4', 'NS5', 'Notes', 'Batch Number']);
            
            foreach ($allResults as $result) {
                if ($result['success']) {
                    fputcsv($file, [
                        $result['domain'],
                        $result['domain_id'],
                        $result['status'],
                        $result['nameservers']['ns1'] ?? '',
                        $result['nameservers']['ns2'] ?? '',
                        $result['nameservers']['ns3'] ?? '',
                        $result['nameservers']['ns4'] ?? '',
                        $result['nameservers']['ns5'] ?? '',
                        'Success',
                        $batchNumber
                    ]);
                } else {
                    fputcsv($file, [
                        $result['domain'],
                        $result['domain_id'] ?? 'N/A',
                        $result['status'],
                        'ERROR',
                        $result['error'] ?? 'Unknown error',
                        '', '', '', 'Failed to get nameservers',
                        $batchNumber
                    ]);
                }
            }
            
            fclose($file);
            
            // Debug: Log successful file creation
            error_log("Export progress: Successfully created CSV file: $filename");
            
            // Clear session data
            unset($_SESSION['export_results_' . $batchNumber]);
            
            echo json_encode([
                'status' => 'complete',
                'message' => 'Export completed successfully',
                'filename' => $filename,
                'total_processed' => count($allResults)
            ]);
            exit;
        }
        
        // Process current domain
        $domain = $domains[$currentDomain];
        $domainName = $domain['domainname'] ?? 'Unknown';
        $domainId = $domain['id'] ?? null;
        $domainStatus = $domain['status'] ?? 'Unknown';
        
        $result = [
            'status' => 'processing',
            'current_domain' => $currentDomain + 1,
            'total_domains' => count($domains),
            'domain_name' => $domainName,
            'domain_id' => $domainId,
            'domain_status' => $domainStatus,
            'progress' => round((($currentDomain + 1) / count($domains)) * 100, 1)
        ];
        
        if (!$domainId) {
            $result['error'] = 'No domain ID found';
            $result['success'] = false;
        } else {
            $nsResponse = getDomainNameservers($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $domainId);
            
            if (isset($nsResponse['result']) && $nsResponse['result'] === 'success') {
                $result['success'] = true;
                $result['nameservers'] = [
                    'ns1' => $nsResponse['ns1'] ?? '',
                    'ns2' => $nsResponse['ns2'] ?? '',
                    'ns3' => $nsResponse['ns3'] ?? '',
                    'ns4' => $nsResponse['ns4'] ?? '',
                    'ns5' => $nsResponse['ns5'] ?? ''
                ];
            } else {
                $errorMsg = $nsResponse['message'] ?? 'Unknown error';
                // Check for timeout errors specifically
                if (isset($nsResponse['http_code']) && $nsResponse['http_code'] == 0) {
                    $errorMsg = 'API Timeout - Server took too long to respond';
                }
                $result['error'] = $errorMsg;
                $result['success'] = false;
            }
        }
        
        // Store result in session for CSV generation
        if (!isset($_SESSION['export_results_' . $batchNumber])) {
            $_SESSION['export_results_' . $batchNumber] = [];
        }
        $_SESSION['export_results_' . $batchNumber][] = [
            'domain' => $domainName,
            'domain_id' => $domainId,
            'status' => $domainStatus,
            'success' => $result['success'] ?? false,
            'error' => $result['error'] ?? null,
            'nameservers' => $result['nameservers'] ?? null
        ];
        
        // Debug: Log session storage
        error_log("Export progress: Stored result for domain $domainName in batch $batchNumber. Total results: " . count($_SESSION['export_results_' . $batchNumber]));
        
        echo json_encode($result);
        exit;
    }
    
    // Handle start_export action
    if ($_POST['action'] === 'start_export') {
        $batchNumber = (int)$_POST['batch_number'];
        
        // Get user settings
        $userSettings = getUserSettingsDB();
        if (!$userSettings) {
            echo json_encode(['error' => 'No API settings found']);
            exit;
        }
        
        // Get domains for this batch
        $batchSize = 50;
        $offset = ($batchNumber - 1) * $batchSize;
        
        // Clear any existing session data for this batch
        unset($_SESSION['export_results_' . $batchNumber]);
        
        $domainsResponse = getDomainsForExport($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret'], $batchSize, $offset);
        
        if (!isset($domainsResponse['domains']['domain'])) {
            echo json_encode(['error' => 'Could not fetch domains']);
            exit;
        }
        
        $domains = $domainsResponse['domains']['domain'];
        
        // Debug: Log the total domains found
        error_log("Export progress: Batch $batchNumber - Found " . count($domains) . " domains");
        
        echo json_encode([
            'status' => 'ready',
            'total_domains' => count($domains),
            'batch_number' => $batchNumber
        ]);
        exit;
    }
    
    // Handle CSV files list request
    if ($_POST['action'] === 'get_csv_files') {
        $csvFiles = glob("exports/*.csv");
        $filesList = [];
        
        foreach ($csvFiles as $file) {
            $fileSize = filesize($file);
            $fileDate = date("Y-m-d H:i:s", filemtime($file));
            
            $filesList[] = [
                'filename' => $file,
                'size' => $fileSize,
                'date' => $fileDate,
                'size_formatted' => number_format($fileSize)
            ];
        }
        
        // Sort by date (newest first)
        usort($filesList, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        echo json_encode([
            'files' => $filesList,
            'total_files' => count($filesList)
        ]);
        exit;
    }
}

// If no action is provided, show the export progress page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Progress - WHMCS Domain Tools</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: #4CAF50;
            transition: width 0.3s ease;
        }
        .export-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        .domain-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            background-color: #fff;
            border-left: 4px solid #ddd;
        }
        .domain-item.success {
            border-left-color: #4CAF50;
        }
        .domain-item.error {
            border-left-color: #f44336;
        }
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
</head>
<body>
    <div class="page-wrapper">
        <div class="container">
            <div class="main-card">
                <div class="card-header">
                    <h1 class="page-title">📁 Export Progress</h1>
                    <p class="page-subtitle">Processing domains with timeout protection</p>
                </div>
                
                <div class="card-body">
                    <div id="export-controls">
                        <h3>Start Export</h3>
                        <form id="export-form">
                            <label for="batch_number">Batch Number:</label>
                            <input type="number" id="batch_number" name="batch_number" value="1" min="1">
                            <button type="submit" id="start-btn">Start Export</button>
                        </form>
                        <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
                            <p style="margin: 0; font-size: 14px; color: #856404;">
                                <strong>💡 Tip:</strong> Each batch processes 50 domains. Batch 1 = domains 1-50, Batch 2 = domains 51-100, etc.
                            </p>
                        </div>
                    </div>
                    
                    <div id="export-progress" style="display: none;">
                        <h3>Export Progress</h3>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                        </div>
                        <div id="progress-text">0%</div>
                        
                        <div class="export-status">
                            <div id="current-domain">Ready to start...</div>
                            <div id="export-results"></div>
                        </div>
                    </div>
                    
                    <!-- Available CSV Files Section -->
                    <div id="available-csv-files" style="margin-top: 30px;">
                        <h3 style="margin-bottom: 15px; color: #333;">📁 Available CSV Export Files</h3>
                        <div id="csv-files-list" class="csv-files-container">
                            <!-- CSV files will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentDomain = 0;
        let totalDomains = 0;
        let batchNumber = 1;
        let results = [];

        document.getElementById('export-form').addEventListener('submit', function(e) {
            e.preventDefault();
            batchNumber = parseInt(document.getElementById('batch_number').value);
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
                    alert('Error: ' + data.error);
                    return;
                }
                
                totalDomains = data.total_domains;
                currentDomain = 0;
                results = [];
                
                document.getElementById('export-controls').style.display = 'none';
                document.getElementById('export-progress').style.display = 'block';
                
                processNextDomain();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error starting export');
            });
        }

        function processNextDomain() {
            if (currentDomain >= totalDomains) {
                // Export complete
                document.getElementById('current-domain').innerHTML = '<strong>✅ Export completed!</strong>';
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
                console.log('Received data:', data); // Debug log
                
                if (data.error) {
                    console.error('Error:', data.error);
                }
                
                // Check if export is complete
                if (data.status === 'complete') {
                    console.log('Export completed, showing download button for:', data.filename); // Debug log
                    // Export completed successfully
                    document.getElementById('progress-fill').style.width = '100%';
                    document.getElementById('progress-text').textContent = '100%';
                    document.getElementById('current-domain').innerHTML = `
                        <strong>✅ Export completed successfully!</strong><br>
                        <small>Processed ${data.total_processed} domains</small>
                    `;
                    
                    // Show download button
                    showDownloadButton(data.filename);
                    
                    // Reload CSV files list to show the new file
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
                
                // Show more detailed error information
                const resultsDiv = document.getElementById('export-results');
                resultsDiv.innerHTML += `
                    <div class="domain-item error" style="margin-top: 10px;">
                        <strong>❌ Processing Error:</strong> ${error.message}<br>
                        <small>Check browser console for more details</small>
                    </div>
                `;
            });
        }

        function updateResultsDisplay() {
            const resultsDiv = document.getElementById('export-results');
            let html = '<h4>Recent Results:</h4>';
            
            const recentResults = results.slice(-5); // Show last 5 results
            recentResults.forEach(result => {
                const statusClass = result.success ? 'success' : 'error';
                const statusText = result.success ? '✅ Success' : '❌ ' + (result.error || 'Error');
                
                html += `
                    <div class="domain-item ${statusClass}">
                        <strong>${result.domain}</strong> - ${statusText}
                    </div>
                `;
            });
            
            resultsDiv.innerHTML = html;
        }

        // Load CSV files list
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
                        <div style="padding: 20px; text-align: center; color: #666; background-color: #f8f9fa; border-radius: 8px;">
                            <p>No CSV files found. Export some domains to see files here.</p>
                        </div>
                    `;
                    return;
                }
                
                let html = `<p style="color: #666; margin-bottom: 15px;">Found ${data.total_files} CSV file(s):</p>`;
                
                data.files.forEach(file => {
                    html += `
                        <div style="margin: 10px 0; padding: 15px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;">
                            <div style="display: flex; justify-content: between; align-items: center;">
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 5px 0; color: #007bff; font-size: 14px;">📄 ${file.filename}</h4>
                                    <p style="margin: 2px 0; color: #666; font-size: 12px;"><strong>Size:</strong> ${file.size_formatted} bytes</p>
                                    <p style="margin: 2px 0; color: #666; font-size: 12px;"><strong>Created:</strong> ${file.date}</p>
                                </div>
                                <div>
                                    <a href="${file.filename}" download style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-size: 12px;">
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
                    <div style="padding: 20px; text-align: center; color: #666; background-color: #f8f9fa; border-radius: 8px;">
                        <p>Error loading CSV files. Please refresh the page.</p>
                    </div>
                `;
            });
        }

        // Load CSV files when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadCsvFiles();
        });

        function showDownloadButton(filename) {
            console.log('showDownloadButton called with filename:', filename); // Debug log
            const resultsDiv = document.getElementById('export-results');
            const downloadHtml = `
                <div style="margin-top: 20px; padding: 15px; background-color: #e8f5e8; border-radius: 8px; border-left: 4px solid #4caf50;">
                    <h4 style="margin: 0 0 10px 0; color: #2e7d32;">📁 Export Complete!</h4>
                    <p style="margin: 0 0 15px 0; color: #2e7d32;">Your CSV file has been generated successfully.</p>
                    <a href="${filename}" download class="btn btn-primary" style="background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">
                        📥 Download CSV File
                    </a>
                    <a href="export_progress.php" class="btn btn-secondary" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-left: 10px;">
                        🔄 Export Another Batch
                    </a>
                </div>
            `;
            resultsDiv.innerHTML += downloadHtml;
            console.log('Download button HTML added to page'); // Debug log
        }
    </script>
</body>
</html> 