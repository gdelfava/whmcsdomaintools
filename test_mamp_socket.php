<?php
require_once 'config.php';

echo "<h1>MAMP Socket Connection Test</h1>";

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

// Test 1: Try connecting without database name first
echo "<h3>Test 1: Connect without database name</h3>";
try {
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ SUCCESS:</strong> Connected to MySQL server!";
    echo "</div>";
    
    // Now try to use the database
    $pdo->exec("USE $database");
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ SUCCESS:</strong> Database '$database' selected!";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> " . $e->getMessage();
    echo "</div>";
}

// Test 2: Try using MAMP socket
echo "<h3>Test 2: Connect via MAMP socket</h3>";
try {
    $socket = '/Applications/MAMP/tmp/mysql/mysql.sock';
    $dsn = "mysql:unix_socket=$socket;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ SUCCESS:</strong> Connected via socket!";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> " . $e->getMessage();
    echo "</div>";
}

// Test 3: Try with root credentials
echo "<h3>Test 3: Connect with root credentials</h3>";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ SUCCESS:</strong> Connected with root credentials!";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ ERROR:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "<h2>Recommendation:</h2>";
echo "<p>If Test 3 works, we should update your .env file to use root credentials for now.</p>";
?> 