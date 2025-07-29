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
                        <div class="text-xs text-gray-500">Database Setup</div>
                    </div>
                </div>
            </nav>

            <!-- Main Card -->
            <div class="main-card">
                <!-- Card Header -->
                <div class="card-header">
                    <div class="card-header-content">
                        <h1 class="page-title">🗄️ Database Setup</h1>
                        <p class="page-subtitle">Configure MySQL database for domain data storage</p>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> mb-6">
                            <div class="flex items-center gap-3">
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">MySQL Database Configuration</h3>
                        
                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label for="db_host" class="form-label">Database Host</label>
                                    <input 
                                        type="text" 
                                        name="db_host" 
                                        id="db_host" 
                                        value="<?= htmlspecialchars($currentHost) ?>"
                                        placeholder="localhost"
                                        class="form-input"
                                        required
                                    >
                                    <div class="form-help">Usually 'localhost' for local installations</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_port" class="form-label">Database Port</label>
                                    <input 
                                        type="number" 
                                        name="db_port" 
                                        id="db_port" 
                                        value="<?= htmlspecialchars($currentPort) ?>"
                                        placeholder="3306"
                                        class="form-input"
                                        required
                                    >
                                    <div class="form-help">Default MySQL port is 3306</div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label for="db_name" class="form-label">Database Name</label>
                                    <input 
                                        type="text" 
                                        name="db_name" 
                                        id="db_name" 
                                        value="<?= htmlspecialchars($currentDatabase) ?>"
                                        placeholder="domain_tools"
                                        class="form-input"
                                        required
                                    >
                                    <div class="form-help">Database will be created if it doesn't exist</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_user" class="form-label">Database Username</label>
                                    <input 
                                        type="text" 
                                        name="db_user" 
                                        id="db_user" 
                                        value="<?= htmlspecialchars($currentUser) ?>"
                                        placeholder="root"
                                        class="form-input"
                                        required
                                    >
                                    <div class="form-help">MySQL user with CREATE/DROP privileges</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_password" class="form-label">Database Password</label>
                                <input 
                                    type="password" 
                                    name="db_password" 
                                    id="db_password" 
                                    value="<?= htmlspecialchars($currentPassword) ?>"
                                    placeholder="Enter database password"
                                    class="form-input"
                                >
                                <div class="form-help">Leave empty if no password is set</div>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <button type="submit" class="btn btn-primary">
                                    <i data-lucide="database" class="w-4 h-4 mr-2"></i>
                                    Test & Save Configuration
                                </button>
                                <a href="main_page.php" class="btn btn-secondary">
                                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Information Section -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4">Database Requirements</h3>
                        
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
                    <div class="bg-green-50 border border-green-200 rounded-xl p-6 mt-6">
                        <h3 class="text-lg font-semibold text-green-900 mb-4">Database Features</h3>
                        
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