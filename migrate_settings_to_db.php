<?php
require_once 'user_settings.php';
require_once 'user_settings_db.php';
// require_once 'database.php';

// Require authentication
// requireAuth();

// Ensure database tables exist
// $db = Database::getInstance();
// $db->createTables();

$message = '';
$messageType = '';
$migrationResults = [];

// Handle manual settings save
if (isset($_POST['save_manual_settings'])) {
    $requiredFields = ['manual_api_url', 'manual_api_identifier', 'manual_api_secret', 'manual_ns1', 'manual_ns2'];
    $allFieldsProvided = true;
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $allFieldsProvided = false;
            break;
        }
    }
    
    if ($allFieldsProvided) {
        $settings = [
            'api_url' => trim($_POST['manual_api_url']),
            'api_identifier' => trim($_POST['manual_api_identifier']),
            'api_secret' => trim($_POST['manual_api_secret']),
            'default_ns1' => trim($_POST['manual_ns1']),
            'default_ns2' => trim($_POST['manual_ns2']),
            'logo_url' => trim($_POST['manual_logo_url'] ?? '')
        ];
        
        $userSettings = new UserSettingsDB();
        if ($userSettings->saveSettings($_SESSION['user_email'], $settings)) {
            $message = '✅ Manual settings saved successfully to database!';
            $messageType = 'success';
        } else {
            $message = '❌ Failed to save manual settings to database.';
            $messageType = 'error';
        }
    } else {
        $message = '⚠️ Please fill in all required fields for manual settings.';
        $messageType = 'error';
    }
}

// Handle settings migration
if (isset($_POST['migrate_settings'])) {
    $oldUserSettings = new UserSettings();
    $newUserSettings = new UserSettingsDB();
    
    // Get all JSON settings files
    $settingsDir = 'user_settings';
    $jsonFiles = glob($settingsDir . '/*.json');
    
    if (empty($jsonFiles)) {
        $message = 'No JSON settings files found to migrate.';
        $messageType = 'info';
    } else {
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($jsonFiles as $jsonFile) {
            // Extract filename without extension
            $filename = basename($jsonFile, '.json');
            
            // Read the JSON file directly
            $content = file_get_contents($jsonFile);
            if ($content === false) {
                $migrationResults[] = [
                    'file' => $jsonFile,
                    'status' => 'error',
                    'message' => 'Failed to read file'
                ];
                $errorCount++;
                continue;
            }
            
            $settings = json_decode($content, true);
            if (!$settings) {
                $migrationResults[] = [
                    'file' => $jsonFile,
                    'status' => 'error',
                    'message' => 'Invalid JSON format'
                ];
                $errorCount++;
                continue;
            }
            
            // Try to decrypt and migrate settings
            try {
                // Decrypt the settings manually
                $decryptedSettings = [
                    'api_url' => $settings['api_url'] ?? '',
                    'api_identifier' => $oldUserSettings->decryptPublic($settings['api_identifier'] ?? ''),
                    'api_secret' => $oldUserSettings->decryptPublic($settings['api_secret'] ?? ''),
                    'default_ns1' => $settings['default_ns1'] ?? '',
                    'default_ns2' => $settings['default_ns2'] ?? '',
                    'logo_url' => $settings['logo_url'] ?? ''
                ];
                
                // Check if we have valid settings
                if (empty($decryptedSettings['api_url']) || empty($decryptedSettings['api_identifier']) || empty($decryptedSettings['api_secret'])) {
                    $migrationResults[] = [
                        'file' => $jsonFile,
                        'status' => 'error',
                        'message' => 'Invalid or incomplete settings data'
                    ];
                    $errorCount++;
                    continue;
                }
                
                // Use current user's email for migration
                $userEmail = $_SESSION['user_email'] ?? null;
                
                if (!$userEmail) {
                    $migrationResults[] = [
                        'file' => $jsonFile,
                        'status' => 'error',
                        'message' => 'No user email available in session'
                    ];
                    $errorCount++;
                    continue;
                }
                
                // Check if settings already exist for this user
                if ($newUserSettings->hasSettings($userEmail)) {
                    $migrationResults[] = [
                        'file' => $jsonFile,
                        'status' => 'warning',
                        'message' => 'Settings already exist for user: ' . $userEmail . ' (skipped)'
                    ];
                    continue;
                }
                
                // Save to database using new class
                if ($newUserSettings->saveSettings($userEmail, $decryptedSettings)) {
                    $migrationResults[] = [
                        'file' => $jsonFile,
                        'status' => 'success',
                        'message' => 'Successfully migrated to database for user: ' . $userEmail
                    ];
                    $successCount++;
                    
                    // Backup the original file
                    $backupFile = $jsonFile . '.backup';
                    copy($jsonFile, $backupFile);
                } else {
                    $migrationResults[] = [
                        'file' => $jsonFile,
                        'status' => 'error',
                        'message' => 'Failed to save to database'
                    ];
                    $errorCount++;
                }
            } catch (Exception $e) {
                $migrationResults[] = [
                    'file' => $jsonFile,
                    'status' => 'error',
                    'message' => 'Exception: ' . $e->getMessage()
                ];
                $errorCount++;
            }
        }
        
        if ($successCount > 0) {
            $message = "Migration completed! Successfully migrated $successCount settings files to database.";
            if ($errorCount > 0) {
                $message .= " $errorCount files had errors.";
            }
            $messageType = 'success';
        } else {
            $message = "Migration failed! No settings were successfully migrated. Check the detailed results below.";
            $messageType = 'error';
        }
    }
}

// Handle logout
if (isset($_POST['logout'])) {
    // handleLogout(); // This function is no longer needed as auth is removed
}

// Get current user's settings status
$hasOldSettings = userHasSettings();
$hasNewSettings = userHasSettingsDB();
$oldSettings = getUserSettings();
$newSettings = getUserSettingsDB();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings Migration - Domain Tools</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Settings Migration</h1>
                    <form method="post" class="inline">
                        <button type="submit" name="logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">
                            Logout
                        </button>
                    </form>
                </div>
                <p class="text-gray-600 mt-2">Migrate settings from JSON files to database for better persistence across devices.</p>
            </div>

            <!-- Message -->
            <?php if (!empty($message)): ?>
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex items-center">
                        <?php if ($messageType === 'success'): ?>
                            <div class="text-green-500 mr-3">✅</div>
                        <?php elseif ($messageType === 'error'): ?>
                            <div class="text-red-500 mr-3">❌</div>
                        <?php else: ?>
                            <div class="text-blue-500 mr-3">ℹ️</div>
                        <?php endif; ?>
                        <p class="text-gray-800"><?= htmlspecialchars($message) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Current Status -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Settings Status</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">JSON Files (Old)</h3>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <span class="text-sm text-gray-600">Has settings:</span>
                                <span class="ml-2 font-medium <?= $hasOldSettings ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $hasOldSettings ? 'Yes' : 'No' ?>
                                </span>
                            </div>
                            <?php if ($oldSettings): ?>
                                <div class="text-sm text-gray-600">
                                    <div>API URL: <?= !empty($oldSettings['api_url']) ? '✅' : '❌' ?></div>
                                    <div>API Identifier: <?= !empty($oldSettings['api_identifier']) ? '✅' : '❌' ?></div>
                                    <div>API Secret: <?= !empty($oldSettings['api_secret']) ? '✅' : '❌' ?></div>
                                    <div>NS1: <?= !empty($oldSettings['default_ns1']) ? '✅' : '❌' ?></div>
                                    <div>NS2: <?= !empty($oldSettings['default_ns2']) ? '✅' : '❌' ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="border rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">Database (New)</h3>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <span class="text-sm text-gray-600">Has settings:</span>
                                <span class="ml-2 font-medium <?= $hasNewSettings ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $hasNewSettings ? 'Yes' : 'No' ?>
                                </span>
                            </div>
                            <?php if ($newSettings): ?>
                                <div class="text-sm text-gray-600">
                                    <div>API URL: <?= !empty($newSettings['api_url']) ? '✅' : '❌' ?></div>
                                    <div>API Identifier: <?= !empty($newSettings['api_identifier']) ? '✅' : '❌' ?></div>
                                    <div>API Secret: <?= !empty($newSettings['api_secret']) ? '✅' : '❌' ?></div>
                                    <div>NS1: <?= !empty($newSettings['default_ns1']) ? '✅' : '❌' ?></div>
                                    <div>NS2: <?= !empty($newSettings['default_ns2']) ? '✅' : '❌' ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Debug Information -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Debug Information</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">JSON Files Found</h3>
                        <?php
                        $jsonFiles = glob('user_settings/*.json');
                        if (empty($jsonFiles)): ?>
                            <p class="text-gray-600">No JSON files found in user_settings/ directory.</p>
                        <?php else: ?>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <?php foreach ($jsonFiles as $file): ?>
                                    <li><?= htmlspecialchars(basename($file)) ?> (<?= filesize($file) ?> bytes)</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">Current User</h3>
                        <p class="text-sm text-gray-600">Email: <?= htmlspecialchars($_SESSION['user_email'] ?? 'Not set') ?></p>
                        <p class="text-sm text-gray-600">Session ID: <?= htmlspecialchars(session_id()) ?></p>
                    </div>
                    
                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">Database Status</h3>
                        <?php
                        // try {
                        //     $db = Database::getInstance();
                        //     $db->createTables();
                        //     echo '<p class="text-green-600 text-sm">✅ Database connection successful</p>';
                        // } catch (Exception $e) {
                        //     echo '<p class="text-red-600 text-sm">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        // }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Migration Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Migrate Settings to Database</h2>
                <p class="text-gray-600 mb-4">
                    This will move your settings from JSON files to the database, making them available across all devices when you log in.
                </p>
                
                <form method="post" class="space-y-4">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="text-yellow-500 mr-3">⚠️</div>
                            <div>
                                <h4 class="font-medium text-yellow-800">Important Notes:</h4>
                                <ul class="text-sm text-yellow-700 mt-2 space-y-1">
                                    <li>• Original JSON files will be backed up with .backup extension</li>
                                    <li>• Settings will be encrypted in the database for security</li>
                                    <li>• After migration, you can delete the old JSON files</li>
                                    <li>• This process is irreversible once files are deleted</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="migrate_settings" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium">
                        Start Migration
                    </button>
                </form>
            </div>

            <!-- Manual Settings Entry -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Manual Settings Entry</h2>
                <p class="text-gray-600 mb-4">
                    If automatic migration fails, you can manually enter your settings here.
                </p>
                
                <form method="post" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">API URL</label>
                            <input type="url" name="manual_api_url" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="https://yourdomain.com/includes/api.php">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Identifier</label>
                            <input type="text" name="manual_api_identifier" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Your API Identifier">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">API Secret</label>
                            <input type="password" name="manual_api_secret" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="Your API Secret">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Nameserver</label>
                            <input type="text" name="manual_ns1" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="ns1.yourdomain.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Secondary Nameserver</label>
                            <input type="text" name="manual_ns2" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="ns2.yourdomain.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Logo URL (Optional)</label>
                            <input type="url" name="manual_logo_url" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="https://yourdomain.com/logo.png">
                        </div>
                    </div>
                    
                    <button type="submit" name="save_manual_settings" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium">
                        Save Manual Settings
                    </button>
                </form>
            </div>

            <!-- Migration Results -->
            <?php if (!empty($migrationResults)): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Migration Results</h2>
                    <div class="space-y-2">
                        <?php foreach ($migrationResults as $result): ?>
                            <div class="flex items-center p-3 border rounded-lg">
                                <div class="mr-3">
                                    <?php if ($result['status'] === 'success'): ?>
                                        <div class="text-green-500">✅</div>
                                    <?php elseif ($result['status'] === 'warning'): ?>
                                        <div class="text-yellow-500">⚠️</div>
                                    <?php else: ?>
                                        <div class="text-red-500">❌</div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars(basename($result['file'])) ?></div>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($result['message']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Next Steps</h2>
                <div class="space-y-3">
                    <a href="settings.php" class="block bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-3 rounded-lg font-medium">
                        📝 Go to Settings Page
                    </a>
                    <a href="main_page.php" class="block bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-3 rounded-lg font-medium">
                        🏠 Go to Main Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 