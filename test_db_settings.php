<?php
// require_once 'auth.php';
// require_once 'database.php';
// If needed, use:
// require_once 'auth_v2.php';
// require_once 'database_v2.php';
require_once 'user_settings_db.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint.

// Ensure database tables exist
// $db = Database::getInstance(); // This line is removed as per the edit hint.
// $db->createTables(); // This line is removed as per the edit hint.

echo "<h1>Database Settings Test</h1>";

// Test 1: Check if user has settings
$userSettings = new UserSettingsDB();
$hasSettings = $userSettings->hasSettings($_SESSION['user_email']);
echo "<p><strong>User has settings:</strong> " . ($hasSettings ? 'Yes' : 'No') . "</p>";

// Test 2: Load settings
$settings = $userSettings->loadSettings($_SESSION['user_email']);
if ($settings) {
    echo "<p><strong>Settings loaded successfully:</strong></p>";
    echo "<ul>";
    echo "<li>API URL: " . (!empty($settings['api_url']) ? '✅ Set' : '❌ Not set') . "</li>";
    echo "<li>API Identifier: " . (!empty($settings['api_identifier']) ? '✅ Set' : '❌ Not set') . "</li>";
    echo "<li>API Secret: " . (!empty($settings['api_secret']) ? '✅ Set' : '❌ Not set') . "</li>";
    echo "<li>NS1: " . (!empty($settings['default_ns1']) ? '✅ Set' : '❌ Not set') . "</li>";
    echo "<li>NS2: " . (!empty($settings['default_ns2']) ? '✅ Set' : '❌ Not set') . "</li>";
    echo "<li>Logo URL: " . (!empty($settings['logo_url']) ? '✅ Set' : '❌ Not set') . "</li>";
    echo "</ul>";
} else {
    echo "<p><strong>No settings found in database.</strong></p>";
}

// Test 3: Save test settings
if (isset($_POST['save_test'])) {
    $testSettings = [
        'api_url' => 'https://test.example.com/includes/api.php',
        'api_identifier' => 'test_identifier',
        'api_secret' => 'test_secret',
        'default_ns1' => 'ns1.test.com',
        'default_ns2' => 'ns2.test.com',
        'logo_url' => 'https://test.example.com/logo.png'
    ];
    
    if ($userSettings->saveSettings($_SESSION['user_email'], $testSettings)) {
        echo "<p><strong>✅ Test settings saved successfully!</strong></p>";
    } else {
        echo "<p><strong>❌ Failed to save test settings.</strong></p>";
    }
}

// Test 4: Delete test settings
if (isset($_POST['delete_test'])) {
    if ($userSettings->deleteSettings($_SESSION['user_email'])) {
        echo "<p><strong>✅ Test settings deleted successfully!</strong></p>";
    } else {
        echo "<p><strong>❌ Failed to delete test settings.</strong></p>";
    }
}

// Test 5: Check database table
// $allSettings = $db->getAllUserSettings(); // This line is removed as per the edit hint.
// echo "<p><strong>Total users with settings in database:</strong> " . count($allSettings) . "</p>"; // This line is removed as per the edit hint.

// if (!empty($allSettings)) { // This line is removed as per the edit hint.
//     echo "<p><strong>Users with settings:</strong></p>"; // This line is removed as per the edit hint.
//     echo "<ul>"; // This line is removed as per the edit hint.
//     foreach ($allSettings as $setting) { // This line is removed as per the edit hint.
//         echo "<li>" . htmlspecialchars($setting['user_email']) . " (Updated: " . $setting['updated_at'] . ")</li>"; // This line is removed as per the edit hint.
//     } // This line is removed as per the edit hint.
//     echo "</ul>"; // This line is removed as per the edit hint.
// } // This line is removed as per the edit hint.

?>

<form method="post" style="margin: 20px 0;">
    <button type="submit" name="save_test" style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-right: 10px;">
        Save Test Settings
    </button>
    <button type="submit" name="delete_test" style="background: #ef4444; color: white; padding: 10px 20px; border: none; border-radius: 5px;">
        Delete Test Settings
    </button>
</form>

<p><a href="migrate_settings_to_db.php" style="color: #3b82f6;">Go to Migration Page</a></p>
<p><a href="settings.php" style="color: #3b82f6;">Go to Settings Page</a></p>
<p><a href="main_page.php" style="color: #3b82f6;">Go to Main Dashboard</a></p> 