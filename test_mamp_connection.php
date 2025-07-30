<?php
require_once 'config.php';

echo "<h1>MAMP Database Connection Test</h1>";

// Get database settings from environment
$host = getEnvVar('DB_HOST', 'localhost');
$database = getEnvVar('DB_NAME', 'domain_tools');
$username = getEnvVar('DB_USER', 'root');
$password = getEnvVar('DB_PASSWORD', '');
$port = getEnvVar('DB_PORT', '3306');

echo "<h2>Database Settings:</h2>";
echo "<ul>";
echo "<li><strong>Host:</strong> $host</li>";
echo "<li><strong>Port:</strong> $port</li>";
echo "<li><strong>Database:</strong> $database</li>";
echo "<li><strong>Username:</strong> $username</li>";
echo "<li><strong>Password:</strong> " . (empty($password) ? 'empty' : 'set') . "</li>";
echo "</ul>";

// Test connection with explicit TCP settings for MAMP
try {
    // Force TCP connection for MAMP
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false, // Disable persistent connections
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ SUCCESS:</strong> Database connection established successfully!";
    echo "</div>";
    
    // Test if we can query the database
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "<p><strong>MySQL Version:</strong> " . $result['version'] . "</p>";
    
    // Test if tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>⚠️ NOTE:</strong> No tables found in database. You'll need to run the setup to create tables.";
        echo "</div>";
    } else {
        echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>ℹ️ INFO:</strong> Found " . count($tables) . " table(s) in database: " . implode(', ', $tables);
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> Database connection failed: " . $e->getMessage();
    echo "</div>";
    
    echo "<h3>Troubleshooting Tips:</h3>";
    echo "<ul>";
    echo "<li>Make sure MAMP MySQL server is running</li>";
    echo "<li>Check if the database '$database' exists in phpMyAdmin</li>";
    echo "<li>Verify username and password are correct</li>";
    echo "<li>Try using MAMP's default credentials (root/root)</li>";
    echo "<li>Check if the port $port is correct for your MAMP setup</li>";
    echo "<li>Try connecting without database name first</li>";
    echo "</ul>";
}

echo "<h2>Next Steps:</h2>";
echo "<ul>";
echo "<li><a href='create_tables.php'>Run Database Setup</a> - Create tables</li>";
echo "<li><a href='sync_interface.php'>Sync Interface</a> - Import domain data</li>";
echo "<li><a href='domains_db.php'>View Database</a> - See imported domains</li>";
echo "</ul>";
?> 