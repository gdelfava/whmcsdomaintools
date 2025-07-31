<?php
require_once 'auth.php';
require_once 'database.php';

// Require authentication
requireAuth();

$message = '';
$messageType = '';

if (isset($_POST['update_tables'])) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Check if user_email column exists in domains table
        $checkColumn = $pdo->query("SHOW COLUMNS FROM domains LIKE 'user_email'");
        $columnExists = $checkColumn->rowCount() > 0;
        
        if (!$columnExists) {
            // Add user_email column to domains table
            $pdo->exec("ALTER TABLE domains ADD COLUMN user_email VARCHAR(255) NOT NULL DEFAULT '' AFTER id");
            $pdo->exec("ALTER TABLE domains ADD INDEX idx_user_email (user_email)");
            
            // Check if unique_domain index exists and drop it safely
            $checkIndex = $pdo->query("SHOW INDEX FROM domains WHERE Key_name = 'unique_domain'");
            if ($checkIndex->rowCount() > 0) {
                $pdo->exec("ALTER TABLE domains DROP INDEX unique_domain");
            }
            
            $pdo->exec("ALTER TABLE domains ADD UNIQUE KEY unique_user_domain (user_email, domain_id)");
            $message .= "✅ Added user_email column to domains table<br>";
        } else {
            $message .= "✅ user_email column already exists in domains table<br>";
        }
        
        // Check if user_email column exists in domain_nameservers table
        $checkColumn = $pdo->query("SHOW COLUMNS FROM domain_nameservers LIKE 'user_email'");
        $columnExists = $checkColumn->rowCount() > 0;
        
        if (!$columnExists) {
            // Add user_email column to domain_nameservers table
            $pdo->exec("ALTER TABLE domain_nameservers ADD COLUMN user_email VARCHAR(255) NOT NULL DEFAULT '' AFTER id");
            $pdo->exec("ALTER TABLE domain_nameservers ADD INDEX idx_user_email (user_email)");
            
            // Check if foreign key constraint exists and drop it safely
            $checkFK = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'domain_nameservers' AND REFERENCED_TABLE_NAME = 'domains'");
            if ($checkFK->rowCount() > 0) {
                $fkRow = $checkFK->fetch();
                $fkName = $fkRow['CONSTRAINT_NAME'];
                $pdo->exec("ALTER TABLE domain_nameservers DROP FOREIGN KEY $fkName");
            }
            
            $pdo->exec("ALTER TABLE domain_nameservers ADD UNIQUE KEY unique_user_domain (user_email, domain_id)");
            $message .= "✅ Added user_email column to domain_nameservers table<br>";
        } else {
            $message .= "✅ user_email column already exists in domain_nameservers table<br>";
        }
        
        // Update existing records to assign them to the current user
        $userEmail = $_SESSION['user_email'];
        $pdo->exec("UPDATE domains SET user_email = '$userEmail' WHERE user_email = '' OR user_email IS NULL");
        $pdo->exec("UPDATE domain_nameservers SET user_email = '$userEmail' WHERE user_email = '' OR user_email IS NULL");
        $message .= "✅ Updated existing records to assign to current user<br>";
        
        $message .= "✅ Multi-user database schema update completed successfully!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = '❌ Error updating tables: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Tables for Multi-User - Domain Tools</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Update Tables for Multi-User</h1>
                    <form method="post" class="inline">
                        <button type="submit" name="logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">
                            Logout
                        </button>
                    </form>
                </div>
                <p class="text-gray-600 mt-2">Update existing database tables to support multi-user functionality.</p>
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
                        <div class="text-gray-800"><?= $message ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Current Status -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Current User</h2>
                <div class="text-sm text-gray-600">
                    <div>Email: <?= htmlspecialchars($_SESSION['user_email'] ?? 'Not set') ?></div>
                    <div>Session Active: <?= isset($_SESSION['user_email']) ? '✅ Yes' : '❌ No' ?></div>
                </div>
            </div>

            <!-- Update Info -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">What This Update Does</h2>
                <div class="space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="font-medium text-blue-800 mb-2">✅ Database Schema Updates</h3>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Adds user_email column to domains table</li>
                            <li>• Adds user_email column to domain_nameservers table</li>
                            <li>• Updates unique constraints to include user_email</li>
                            <li>• Adds indexes for better performance</li>
                            <li>• Assigns existing records to current user</li>
                        </ul>
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h3 class="font-medium text-green-800 mb-2">🎯 Multi-User Benefits</h3>
                        <ul class="text-sm text-green-700 space-y-1">
                            <li>• Each user only sees their own domains</li>
                            <li>• Complete data isolation between users</li>
                            <li>• Secure multi-user environment</li>
                            <li>• Existing data preserved and assigned to current user</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Update Action -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Update Database Schema</h2>
                <p class="text-gray-600 mb-4">
                    Click the button below to update your database tables for multi-user support.
                    This will add the necessary columns and assign existing data to your account.
                </p>
                
                <form method="post" class="space-y-4">
                    <button type="submit" name="update_tables" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium">
                        Update Tables for Multi-User
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
                    <a href="test_multi_user.php" class="block bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-3 rounded-lg font-medium">
                        🧪 Test Multi-User Functionality
                    </a>
                    <a href="migrate_to_multi_user.php" class="block bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-3 rounded-lg font-medium">
                        🔧 Multi-User Migration
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 