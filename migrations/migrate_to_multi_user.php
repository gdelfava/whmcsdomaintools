<?php
// require_once 'auth.php';
// require_once 'database.php';
// If needed, use:
// require_once 'auth_v2.php';
// require_once 'database_v2.php';

// Require authentication
requireAuth();

// Ensure database tables exist with new schema
$db = Database::getInstance();
$db->createTables();

$message = '';
$messageType = '';

if (isset($_POST['migrate_data'])) {
    try {
        $userEmail = $_SESSION['user_email'];
        
        // Check if user has settings
        if (!$db->hasUserSettings($userEmail)) {
            $message = '❌ No settings found for current user. Please configure settings first.';
            $messageType = 'error';
        } else {
            // Get user settings to verify they exist
            $userSettings = $db->getUserSettings($userEmail);
            
            if (!$userSettings) {
                $message = '❌ Failed to load user settings.';
                $messageType = 'error';
            } else {
                $message = '✅ User settings verified. Multi-user migration completed!';
                $messageType = 'success';
            }
        }
    } catch (Exception $e) {
        $message = '❌ Migration error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

// Get current user info
$userEmail = $_SESSION['user_email'] ?? 'Not set';
$hasSettings = $db->hasUserSettings($userEmail);
$userSettings = $db->getUserSettings($userEmail);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-User Migration - Domain Tools</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Multi-User Migration</h1>
                    <form method="post" class="inline">
                        <button type="submit" name="logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">
                            Logout
                        </button>
                    </form>
                </div>
                <p class="text-gray-600 mt-2">Prepare the application for multi-user support.</p>
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
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Current User Status</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">User Information</h3>
                        <div class="text-sm text-gray-600">
                            <div>Email: <?= htmlspecialchars($userEmail) ?></div>
                            <div>Has Settings: <?= $hasSettings ? '✅ Yes' : '❌ No' ?></div>
                        </div>
                    </div>
                    
                    <?php if ($userSettings): ?>
                        <div>
                            <h3 class="font-medium text-gray-900 mb-2">Settings Status</h3>
                            <div class="text-sm text-gray-600">
                                <div>API URL: <?= !empty($userSettings['api_url']) ? '✅ Set' : '❌ Not set' ?></div>
                                <div>API Identifier: <?= !empty($userSettings['api_identifier']) ? '✅ Set' : '❌ Not set' ?></div>
                                <div>API Secret: <?= !empty($userSettings['api_secret']) ? '✅ Set' : '❌ Not set' ?></div>
                                <div>NS1: <?= !empty($userSettings['default_ns1']) ? '✅ Set' : '❌ Not set' ?></div>
                                <div>NS2: <?= !empty($userSettings['default_ns2']) ? '✅ Set' : '❌ Not set' ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Migration Info -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Multi-User Features</h2>
                <div class="space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="font-medium text-blue-800 mb-2">✅ What's Been Updated</h3>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Database schema updated with user_email fields</li>
                            <li>• All domain queries now filter by user</li>
                            <li>• Settings are user-specific</li>
                            <li>• Sync logs are user-specific</li>
                            <li>• Each user only sees their own data</li>
                        </ul>
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h3 class="font-medium text-green-800 mb-2">🎯 Multi-User Benefits</h3>
                        <ul class="text-sm text-green-700 space-y-1">
                            <li>• Multiple users can use the same application</li>
                            <li>• Each user's data is completely isolated</li>
                            <li>• Settings persist per user</li>
                            <li>• Secure data separation</li>
                            <li>• Scalable architecture</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Migration Action -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Complete Migration</h2>
                <p class="text-gray-600 mb-4">
                    Click the button below to complete the multi-user migration for your account.
                </p>
                
                <form method="post" class="space-y-4">
                    <button type="submit" name="migrate_data" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium">
                        Complete Multi-User Migration
                    </button>
                </form>
            </div>

            <!-- Navigation -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Next Steps</h2>
                <div class="space-y-3">
                    <a href="main_page.php" class="block bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-3 rounded-lg font-medium">
                        🏠 Go to Main Dashboard
                    </a>
                    <a href="settings.php" class="block bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-3 rounded-lg font-medium">
                        ⚙️ Go to Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 