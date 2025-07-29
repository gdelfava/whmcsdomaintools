<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'user_settings.php';

// Require authentication
requireAuth();

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

// Check if user has configured their API settings
if (!userHasSettings()) {
    header('Location: settings.php?redirect=sync_interface.php');
    exit;
}

// Initialize database
try {
    $db = Database::getInstance();
    $db->createTables(); // Ensure tables exist
} catch (Exception $e) {
    $error = "Database connection failed: " . $e->getMessage();
}

// Get last sync information
$lastSync = null;
if (!isset($error)) {
    try {
        $lastSync = $db->getLastSyncInfo($_SESSION['user_email'] ?? '');
    } catch (Exception $e) {
        // Don't fail the page if sync info fails
    }
}

// Get domain statistics
$domainStats = [];
if (!isset($error)) {
    try {
        $domainStats = $db->getDomainStats();
    } catch (Exception $e) {
        // Don't fail the page if stats fail
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Sync Interface</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
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
                        <div class="text-xs text-gray-500">Sync Interface</div>
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
                <span>Sync Interface</span>
            </nav>

            <!-- Main Card -->
            <div class="main-card">
                <!-- Card Header -->
                <div class="card-header">
                    <div class="card-header-content">
                        <h1 class="page-title">🔄 Domain Sync Interface</h1>
                        <p class="page-subtitle">Sync domain data from WHMCS API to local database</p>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-error mb-6">
                            <div class="flex items-center gap-3">
                                <span style="font-size: 1.5rem;">❌</span>
                                <div>
                                    <div class="font-semibold">Database Error</div>
                                    <div class="text-sm mt-1"><?= htmlspecialchars($error) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Current Status -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-primary-600 text-white p-6 rounded-xl">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-primary-100">Total Domains</h3>
                                <div class="w-8 h-8 bg-primary-500 rounded-full flex items-center justify-center">
                                    <i data-lucide="database" class="w-4 h-4"></i>
                                </div>
                            </div>
                            <div class="text-4xl font-bold"><?= $domainStats['total_domains'] ?? 0 ?></div>
                        </div>

                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-gray-500">Active Domains</h3>
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-green-600"></i>
                                </div>
                            </div>
                            <div class="text-4xl font-bold text-gray-900"><?= $domainStats['active_domains'] ?? 0 ?></div>
                        </div>

                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-gray-500">Expired Domains</h3>
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 text-red-600"></i>
                                </div>
                            </div>
                            <div class="text-4xl font-bold text-gray-900"><?= $domainStats['expired_domains'] ?? 0 ?></div>
                        </div>

                        <div class="bg-white p-6 rounded-xl border border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-medium text-gray-500">Pending Domains</h3>
                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <i data-lucide="clock" class="w-4 h-4 text-yellow-600"></i>
                                </div>
                            </div>
                            <div class="text-4xl font-bold text-gray-900"><?= $domainStats['pending_domains'] ?? 0 ?></div>
                        </div>
                    </div>

                    <!-- Last Sync Information -->
                    <?php if ($lastSync): ?>
                        <div class="alert alert-info mb-6">
                            <div class="flex items-center gap-3">
                                <span style="font-size: 1.5rem;">📊</span>
                                <div>
                                    <div class="font-semibold">Last Sync: <?= date('M j, Y g:i A', strtotime($lastSync['sync_started'])) ?></div>
                                    <div class="text-sm mt-1">
                                        Batch <?= $lastSync['batch_number'] ?> - 
                                        <?= $lastSync['domains_processed'] ?> domains processed
                                        (<?= $lastSync['domains_added'] ?> added, <?= $lastSync['domains_updated'] ?> updated)
                                        <?php if ($lastSync['errors'] > 0): ?>
                                            - <?= $lastSync['errors'] ?> errors
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sync Configuration -->
                    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Sync Configuration</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                                <div class="form-help">Specify which batch of domains to sync (10 domains per batch)</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="batch_size" class="form-label">Batch Size</label>
                                <input 
                                    type="number" 
                                    name="batch_size" 
                                    id="batch_size" 
                                    class="form-input"
                                    min="5" 
                                    max="50"
                                    value="10"
                                    required
                                >
                                <div class="form-help">Number of domains to fetch per API call (5-50, recommended: 10)</div>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="button" id="startSync" class="btn btn-primary">
                                <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
                                Start Sync
                            </button>
                            <button type="button" id="stopSync" class="btn btn-secondary ml-4" style="display: none;">
                                <i data-lucide="square" class="w-4 h-4 mr-2"></i>
                                Stop Sync
                            </button>
                        </div>
                    </div>

                    <!-- Sync Progress -->
                    <div id="syncProgress" class="bg-white rounded-xl border border-gray-200 p-6 mb-6" style="display: none;">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Sync Progress</h3>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">Status:</span>
                                <span id="syncStatus" class="text-sm font-medium text-blue-600">Initializing...</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">Domains Found:</span>
                                <span id="domainsFound" class="text-sm font-medium text-gray-900">0</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">Domains Processed:</span>
                                <span id="domainsProcessed" class="text-sm font-medium text-gray-900">0</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">Domains Added:</span>
                                <span id="domainsAdded" class="text-sm font-medium text-green-600">0</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">Domains Updated:</span>
                                <span id="domainsUpdated" class="text-sm font-medium text-blue-600">0</span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">Errors:</span>
                                <span id="syncErrors" class="text-sm font-medium text-red-600">0</span>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div id="progressBar" class="bg-primary-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
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
                        <a href="domains_db.php" class="btn btn-secondary">
                            <i data-lucide="database" class="w-4 h-4 mr-2"></i>
                            View Database
                        </a>
                        <a href="export_domains.php" class="btn btn-secondary">
                            <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                            Export CSV
                        </a>
                        <button type="button" id="clearOldData" class="btn btn-warning">
                            <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                            Clear Old Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        let syncInProgress = false;
        let currentLogId = null;
        
        // Start sync button
        document.getElementById('startSync').addEventListener('click', function() {
            if (syncInProgress) return;
            
            const batchNumber = document.getElementById('batch_number').value;
            const batchSize = document.getElementById('batch_size').value;
            
            startSync(batchNumber, batchSize);
        });
        
        // Stop sync button
        document.getElementById('stopSync').addEventListener('click', function() {
            if (!syncInProgress) return;
            
            stopSync();
        });
        
        // Clear old data button
        document.getElementById('clearOldData').addEventListener('click', function() {
            if (confirm('Are you sure you want to clear old data? This will remove domains that haven\'t been synced in the last 30 days.')) {
                clearOldData();
            }
        });
        
        function startSync(batchNumber, batchSize) {
            syncInProgress = true;
            document.getElementById('startSync').style.display = 'none';
            document.getElementById('stopSync').style.display = 'inline-flex';
            document.getElementById('syncProgress').style.display = 'block';
            document.getElementById('syncLog').style.display = 'block';
            
            // Reset progress
            resetProgress();
            addLogMessage('Starting sync for batch ' + batchNumber + '...');
            
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
                    updateProgress(data.data);
                    addLogMessage('Batch ' + batchNumber + ' completed successfully!');
                    
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
                } else {
                    addLogMessage('Sync failed: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                addLogMessage('Network error: ' + error.message, 'error');
            })
            .finally(() => {
                syncInProgress = false;
                document.getElementById('startSync').style.display = 'inline-flex';
                document.getElementById('stopSync').style.display = 'none';
            });
        }
        
        function stopSync() {
            syncInProgress = false;
            document.getElementById('startSync').style.display = 'inline-flex';
            document.getElementById('stopSync').style.display = 'none';
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
            document.getElementById('syncStatus').textContent = 'Initializing...';
            document.getElementById('domainsFound').textContent = '0';
            document.getElementById('domainsProcessed').textContent = '0';
            document.getElementById('domainsAdded').textContent = '0';
            document.getElementById('domainsUpdated').textContent = '0';
            document.getElementById('syncErrors').textContent = '0';
            document.getElementById('progressBar').style.width = '0%';
        }
        
        function updateProgress(data) {
            document.getElementById('syncStatus').textContent = data.status === 'completed' ? 'Completed' : 'Running';
            document.getElementById('domainsFound').textContent = data.domains_found || 0;
            document.getElementById('domainsProcessed').textContent = data.domains_processed || 0;
            document.getElementById('domainsAdded').textContent = data.domains_added || 0;
            document.getElementById('domainsUpdated').textContent = data.domains_updated || 0;
            document.getElementById('syncErrors').textContent = data.errors || 0;
            
            // Update progress bar
            if (data.domains_found > 0) {
                const progress = (data.domains_processed / data.domains_found) * 100;
                document.getElementById('progressBar').style.width = progress + '%';
            }
        }
        
        function addLogMessage(message, type = 'info') {
            const logContent = document.getElementById('logContent');
            const timestamp = new Date().toLocaleTimeString();
            const colorClass = type === 'error' ? 'text-red-600' : type === 'warning' ? 'text-yellow-600' : 'text-gray-600';
            
            const logEntry = document.createElement('div');
            logEntry.className = 'mb-1 ' + colorClass;
            logEntry.textContent = '[' + timestamp + '] ' + message;
            
            // Prepend new messages to show latest at the top
            logContent.insertBefore(logEntry, logContent.firstChild);
            logContent.scrollTop = 0; // Scroll to top to show latest message
        }
    </script>
</body>
</html> 