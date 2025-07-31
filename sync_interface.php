<?php
require_once 'user_settings_db.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint

// Handle logout
// if (isset($_POST['logout'])) { // This block is removed as per the edit hint
//     handleLogout(); // This line is removed as per the edit hint
// }

// Check if user has configured their API settings
if (!userHasSettings()) {
    header('Location: settings.php?redirect=sync_interface.php');
    exit;
}

// Initialize database
// try { // This block is removed as per the edit hint
//     $db = Database::getInstance(); // This line is removed as per the edit hint
//     $db->createTables(); // Ensure tables exist // This line is removed as per the edit hint
// } catch (Exception $e) { // This line is removed as per the edit hint
//     $error = "Database connection failed: " . $e->getMessage(); // This line is removed as per the edit hint
// }

// Get last sync information
$lastSync = null;
// if (!isset($error)) { // This block is removed as per the edit hint
//     try { // This line is removed as per the edit hint
//         $lastSync = $db->getLastSyncInfo($_SESSION['user_email'] ?? ''); // This line is removed as per the edit hint
//     } catch (Exception $e) { // This line is removed as per the edit hint
//         // Don't fail the page if sync info fails // This line is removed as per the edit hint
//     } // This line is removed as per the edit hint
// }

// Get domain statistics
$domainStats = [];
// if (!isset($error)) { // This block is removed as per the edit hint
//     try { // This line is removed as per the edit hint
//         $domainStats = $db->getDomainStats($_SESSION['user_email'] ?? ''); // This line is removed as per the edit hint
//     } catch (Exception $e) { // This line is removed as per the edit hint
//         // Don't fail the page if stats fail // This line is removed as per the edit hint
//     } // This line is removed as per the edit hint
// }
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
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
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
                                <a href="main_page.php?view=dashboard" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="layout-dashboard" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Dashboard</span>
                                </a>
                            </li>
                            <li>
                                <a href="main_page.php?view=billing" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="credit-card" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Billing</span>
                                </a>
                            </li>
                            <li>
                                <a href="main_page.php?view=orders" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="shopping-cart" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Orders</span>
                                </a>
                            </li>
                            <li>
                                <a href="main_page.php?view=domains" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="globe" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Domains</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">DOMAIN ACTIONS</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="sync_interface.php" class="flex items-center space-x-3 px-3 py-2 bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600">
                                    <i data-lucide="refresh-cw" class="w-4 h-4 text-primary-600"></i>
                                    <span class="text-sm font-semibold text-gray-900">Domain Sync</span>
                                </a>
                            </li>
                            <li>
                                <a href="main_page.php?view=nameservers" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="server" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Update Nameservers</span>
                                </a>
                            </li>
                            <li>
                                <a href="main_page.php?view=export" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="download" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Export Domains</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-auto">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">GENERAL</h3>
                    <ul class="space-y-1">
                        <li>
                            <a href="main_page.php?view=settings" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                <i data-lucide="settings" class="w-4 h-4 text-gray-400"></i>
                                <span class="text-sm font-normal">Settings</span>
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
                <div class="flex items-center justify-between">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <button class="lg:hidden mr-4 text-gray-500" id="sidebar-toggle">
                            <i data-lucide="menu" class="w-6 h-6"></i>
                        </button>
                        <img src="<?= htmlspecialchars(getLogoUrl()) ?>" 
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
                            $userEmail = $_SESSION['user_email'] ?? '';
                            $gravatarHash = md5(strtolower(trim($userEmail)));
                            $gravatarUrl = "https://www.gravatar.com/avatar/{$gravatarHash}?s=32&d=mp&r=g";
                            ?>
                            <img src="<?= htmlspecialchars($gravatarUrl) ?>" 
                                 alt="User Avatar" 
                                 class="w-8 h-8 rounded-full object-cover border-2 border-gray-200"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center hidden">
                                <span class="text-white font-medium text-sm">
                                    <?= !empty($userEmail) ? strtoupper(substr($userEmail, 0, 1)) : 'U' ?>
                                </span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($userEmail) ?></span>
                                <span class="text-xs text-gray-500">Administrator</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-6">
                <!-- Page Title -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Domain Sync Interface</h1>
                    <p class="text-sm text-gray-500 mt-1">Sync domain data from WHMCS API to local database</p>
                </div>

                <!-- Main Card -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Sync Controls</h2>
                    </div>
                    <div class="p-6">
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
                                    class="form-input border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    min="1" 
                                    value="1"
                                    required
                                >
                                <div class="form-help text-xs text-gray-500 mt-1 font-normal">Specify which batch of domains to sync (10 domains per batch)</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="batch_size" class="form-label">Batch Size</label>
                                <input 
                                    type="number" 
                                    name="batch_size" 
                                    id="batch_size" 
                                    class="form-input border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    min="5" 
                                    max="50"
                                    value="10"
                                    required
                                >
                                <div class="form-help text-xs text-gray-500 mt-1 font-normal">Number of domains to fetch per API call (5-50, recommended: 10)</div>
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
                        <a href="domains_db.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                            <i data-lucide="database" class="w-4 h-4 mr-2"></i>
                            View Database
                        </a>
                        <a href="main_page.php?view=export" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                            <i data-lucide="download" class="w-4 h-4 mr-2"></i>
                            Export CSV
                        </a>
                        <button type="button" id="clearOldData" class="inline-flex items-center px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                            <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                            Clear Old Data
                        </button>
                    </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Mobile sidebar toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            const sidebar = document.querySelector('.w-64');
            const overlay = document.getElementById('sidebar-overlay');
            
            sidebar.classList.toggle('-translate-x-full');
            if (overlay) {
                overlay.classList.toggle('hidden');
            }
        });
        
        // Close sidebar when clicking overlay
        document.getElementById('sidebar-overlay')?.addEventListener('click', function() {
            const sidebar = document.querySelector('.w-64');
            this.classList.add('hidden');
            sidebar.classList.add('-translate-x-full');
        });
        
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