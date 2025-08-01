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
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    
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
                                <a href="main_page.php" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="layout-dashboard" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Dashboard</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <?php if (isServerAdmin()): ?>
                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">SERVER SETUP</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="main_page.php?view=database_setup" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                    <i data-lucide="database" class="w-4 h-4 text-gray-400"></i>
                                    <span class="text-sm font-normal">Database Setup</span>
                                </a>
                            </li>
                            <li>
                                <a href="create_tables.php" class="flex items-center space-x-3 px-3 py-2 bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600">
                                    <i data-lucide="table" class="w-4 h-4 text-primary-600"></i>
                                    <span class="text-sm font-semibold text-gray-900">Create Tables</span>
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
                            <a href="main_page.php?view=settings" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                <i data-lucide="settings" class="w-4 h-4 text-gray-400"></i>
                                <span class="text-sm font-normal">Settings</span>
                            </a>
                        </li>
                        <li>
                            <a href="main_page.php" class="flex items-center space-x-3 px-3 py-2 text-gray-500 hover:bg-gray-50 rounded-lg transition-colors">
                                <i data-lucide="arrow-left" class="w-4 h-4 text-gray-400"></i>
                                <span class="text-sm font-normal">Back to Dashboard</span>
                            </a>
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
                        <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center mr-3">
                            <i data-lucide="table" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-900">Create Tables</h1>
                            <p class="text-sm text-gray-500">Set up the required database tables for domain management</p>
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">A</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Administrator</p>
                                <p class="text-xs text-gray-500">System Admin</p>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6 overflow-y-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">🗄️ Create Database Tables</h1>
                    <p class="text-gray-600">Set up the required database tables for domain management</p>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
                        <div class="flex items-center space-x-3">
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
                            <a href="main_page.php?view=domains" class="text-green-800 hover:text-green-900 font-medium">
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
                    <a href="main_page.php" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                        <i data-lucide="home" class="w-4 h-4"></i>
                        <span>Go to Dashboard</span>
                    </a>
                    <a href="sync_interface.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        <span>Start Domain Sync</span>
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html> 