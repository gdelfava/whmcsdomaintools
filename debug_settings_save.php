<?php
// Debug script to test settings save functionality
require_once 'auth_v2.php';
require_once 'database_v2.php';
require_once 'user_settings_db.php';

// Start session for testing
session_start();

echo "<h1>Settings Save Debug</h1>";

// Check session variables
echo "<h2>Session Variables:</h2>";
echo "user_email: " . ($_SESSION['user_email'] ?? 'NOT SET') . "<br>";
echo "company_id: " . ($_SESSION['company_id'] ?? 'NOT SET') . "<br>";

// Check database connection
$db = Database::getInstance();
echo "<h2>Database Connection:</h2>";
if ($db->isConnected()) {
    echo "✅ Database connected successfully<br>";
} else {
    echo "❌ Database connection failed<br>";
    exit;
}

// Test user settings save
echo "<h2>Testing Settings Save:</h2>";

$testSettings = [
    'api_url' => 'https://test.example.com/includes/api.php',
    'api_identifier' => 'test_identifier',
    'api_secret' => 'test_secret',
    'default_ns1' => 'ns1.test.com',
    'default_ns2' => 'ns2.test.com',
    'logo_url' => 'https://test.example.com/logo.png'
];

$userSettings = new UserSettingsDB();

// Test with session variables
if (isset($_SESSION['user_email']) && isset($_SESSION['company_id'])) {
    echo "Testing with session variables...<br>";
    $result = $userSettings->saveSettings($_SESSION['company_id'], $_SESSION['user_email'], $testSettings);
    echo "Save result: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";
    
    // Check if settings were saved
    $savedSettings = $userSettings->loadSettings($_SESSION['company_id'], $_SESSION['user_email']);
    if ($savedSettings) {
        echo "✅ Settings loaded successfully<br>";
        echo "API URL: " . htmlspecialchars($savedSettings['api_url']) . "<br>";
    } else {
        echo "❌ Settings could not be loaded<br>";
    }
} else {
    echo "❌ Session variables not set<br>";
}

// Test with hardcoded values
echo "<h2>Testing with hardcoded values:</h2>";
$result = $userSettings->saveSettings(1, 'test@example.com', $testSettings);
echo "Save result: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";

// Check database table structure
echo "<h2>Database Table Check:</h2>";
try {
    $sql = "DESCRIBE user_settings";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "user_settings table columns:<br>";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking table structure: " . $e->getMessage() . "<br>";
}

// Check if user_settings table exists
echo "<h2>Table Existence Check:</h2>";
try {
    $sql = "SHOW TABLES LIKE 'user_settings'";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✅ user_settings table exists<br>";
    } else {
        echo "❌ user_settings table does not exist<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking table existence: " . $e->getMessage() . "<br>";
}

echo "<br><a href='main_page.php?view=settings'>Go to Settings Page</a>";
?> 