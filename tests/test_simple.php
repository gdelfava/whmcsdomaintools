<?php
echo "<h1>Simple Test Page</h1>";
echo "<p>If you can see this, PHP is working!</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP version: " . phpversion() . "</p>";

// Test if we can read the config file
if (file_exists('config.php')) {
    echo "<p>✅ config.php file exists</p>";
} else {
    echo "<p>❌ config.php file not found</p>";
}

// Test if we can read the database.php file
if (file_exists('database.php')) {
    echo "<p>✅ database.php file exists</p>";
} else {
    echo "<p>❌ database.php file not found</p>";
}

// List files in current directory
echo "<h2>Files in current directory:</h2>";
echo "<ul>";
$files = scandir('.');
foreach ($files as $file) {
    if (is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        echo "<li>$file</li>";
    }
}
echo "</ul>";

echo "<h2>Next Steps:</h2>";
echo "<ul>";
echo "<li><a href='test_db_connection.php'>Test Database Connection</a></li>";
echo "<li><a href='test_api_connection.php'>Test API Connection</a></li>";
echo "<li><a href='test_with_sample_data.php'>Test with Sample Data</a></li>";
echo "<li><a href='main_page.php'>Go to Main Page</a></li>";
echo "</ul>";
?> 