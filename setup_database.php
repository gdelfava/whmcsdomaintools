<?php
require_once 'config.php';

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

$message = '';
$messageType = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? '');
    $port = trim($_POST['db_port'] ?? '3306');
    $database = trim($_POST['db_name'] ?? '');
    $username = trim($_POST['db_user'] ?? '');
    $password = trim($_POST['db_password'] ?? '');
    
    if (empty($host) || empty($database) || empty($username)) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    } else {
        // First try to create database if it doesn't exist
        $createResult = createDatabaseIfNotExists($host, $port, $username, $password, $database);
        
        if ($createResult['success']) {
            // Test connection to the specific database
            $testResult = testDatabaseConnection($host, $port, $database, $username, $password);
            
            if ($testResult['success']) {
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
                    $message = 'Database configuration saved successfully! You can now use the database features.';
                    $messageType = 'success';
                } else {
                    $message = 'Database connection successful, but failed to save configuration. Please check file permissions.';
                    $messageType = 'warning';
                }
            } else {
                $message = $testResult['message'];
                $messageType = 'error';
            }
        } else {
            $message = $createResult['message'];
            $messageType = 'error';
        }
    }
}

// Get current database settings
$currentHost = getEnvVar('DB_HOST', 'localhost');
$currentPort = getEnvVar('DB_PORT', '3306');
$currentDatabase = getEnvVar('DB_NAME', 'domain_tools');
$currentUser = getEnvVar('DB_USER', 'root');
$currentPassword = getEnvVar('DB_PASSWORD', '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Database Setup</title>
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

                    <div class="mb-6">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">SERVER SETUP</h3>
                        <ul class="space-y-1">
                            <li>
                                <a href="setup_database.php" class="flex items-center space-x-3 px-3 py-2 bg-primary-50 text-primary-700 rounded-lg border-l-4 border-primary-600">
                                    <i data-lucide="database" class="w-4 h-4 text-primary-600"></i>
                                    <span class="text-sm font-semibold text-gray-900">Database Setup</span>
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
                            <i data-lucide="database" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold text-gray-900">Database Setup</h1>
                            <p class="text-sm text-gray-500">Configure MySQL database for domain data storage</p>
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
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">🗄️ Database Setup</h1>
                    <p class="text-gray-600">Configure MySQL database for domain data storage</p>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : ($messageType === 'warning' ? 'bg-yellow-50 border border-yellow-200 text-yellow-800' : 'bg-red-50 border border-red-200 text-red-800') ?>">
                        <div class="flex items-center space-x-3">
                            <span style="font-size: 1.5rem;">
                                <?= $messageType === 'success' ? '✅' : ($messageType === 'warning' ? '⚠️' : '❌') ?>
                            </span>
                            <div>
                                <div class="font-semibold">
                                    <?= $messageType === 'success' ? 'Success' : ($messageType === 'warning' ? 'Warning' : 'Error') ?>
                                </div>
                                <div class="text-sm mt-1"><?= htmlspecialchars($message) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Database Configuration Form -->
                <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                        <i data-lucide="database" class="w-5 h-5 text-primary-600"></i>
                        <span>MySQL Database Configuration</span>
                    </h3>
                    
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
                            <a href="main_page.php" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-4 py-2 rounded-lg font-medium transition-colors flex items-center space-x-2">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                <span>Back to Dashboard</span>
                            </a>
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
            </main>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html> 