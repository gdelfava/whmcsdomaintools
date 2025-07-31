<?php
require_once 'config.php';
require_once 'database_v2.php';

echo "<h2>Database Connection Test</h2>";

// Test database connection
$db = Database::getInstance();

if ($db->isConnected()) {
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Test creating tables
    if ($db->createTables()) {
        echo "<p style='color: green;'>✅ Tables created/verified successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create tables</p>";
    }
    
    // Test if companies table exists and count
    try {
        $sql = "SELECT COUNT(*) as count FROM companies";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        echo "<p>📊 Companies in database: " . ($result['count'] ?? 0) . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ Companies table not found: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Database connection failed!</p>";
    
    // Show environment variables (without sensitive data)
    echo "<h3>Environment Variables:</h3>";
    echo "<p>DB_HOST: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "</p>";
    echo "<p>DB_PORT: " . ($_ENV['DB_PORT'] ?? 'NOT SET') . "</p>";
    echo "<p>DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "</p>";
    echo "<p>DB_USER: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "</p>";
    echo "<p>DB_PASSWORD: " . (isset($_ENV['DB_PASSWORD']) ? 'SET' : 'NOT SET') . "</p>";
}

echo "<h3>Environment Check:</h3>";
echo "<p>Config file loaded: " . (function_exists('getEnvVar') ? 'YES' : 'NO') . "</p>";
echo "<p>Database class available: " . (class_exists('Database') ? 'YES' : 'NO') . "</p>";
?> 