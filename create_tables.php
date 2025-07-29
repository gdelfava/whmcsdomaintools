<?php
require_once 'config.php';
require_once 'database.php';

$message = '';
$messageType = '';

try {
    // Get database instance
    $db = Database::getInstance();
    
    // Create tables
    if ($db->createTables()) {
        $message = '✅ SUCCESS: Database tables created successfully!';
        $messageType = 'success';
    } else {
        $message = '❌ ERROR: Failed to create database tables.';
        $messageType = 'error';
    }
} catch (Exception $e) {
    $message = '❌ ERROR: ' . $e->getMessage();
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Create Tables</title>
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
                    <div>
                        <div class="font-semibold text-gray-900">WHMCS Domain Tools</div>
                        <div class="text-xs text-gray-500">Create Database Tables</div>
                    </div>
                </div>
            </nav>

            <!-- Main Card -->
            <div class="main-card">
                <!-- Card Header -->
                <div class="card-header">
                    <div class="card-header-content">
                        <h1 class="page-title">🗄️ Create Database Tables</h1>
                        <p class="page-subtitle">Set up the required database tables for domain management</p>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> mb-6">
                            <div class="flex items-center gap-3">
                                <span style="font-size: 1.5rem;">
                                    <?= $messageType === 'success' ? '✅' : '❌' ?>
                                </span>
                                <div>
                                    <div class="font-semibold">
                                        <?= $messageType === 'success' ? 'Success' : 'Error' ?>
                                    </div>
                                    <div class="text-sm mt-1"><?= htmlspecialchars($message) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Information Section -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4">Database Tables</h3>
                        
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
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="bg-green-50 border border-green-200 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-green-900 mb-4">Next Steps</h3>
                        
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <i data-lucide="arrow-right" class="w-4 h-4 text-green-600"></i>
                                <a href="sync_interface.php" class="text-green-800 hover:text-green-900 font-medium">
                                    Sync Interface - Import domain data from WHMCS
                                </a>
                            </div>
                            <div class="flex items-center gap-3">
                                <i data-lucide="arrow-right" class="w-4 h-4 text-green-600"></i>
                                <a href="main_page.php" class="text-green-800 hover:text-green-900 font-medium">
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
                    <div class="flex items-center space-x-4 mt-6">
                        <a href="main_page.php" class="btn btn-primary">
                            <i data-lucide="home" class="w-4 h-4 mr-2"></i>
                            Go to Dashboard
                        </a>
                        <a href="sync_interface.php" class="btn btn-secondary">
                            <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
                            Start Domain Sync
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html> 