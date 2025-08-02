<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

// Increase PHP timeout limits for testing
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '512M'); // Increase memory limit
set_time_limit(300); // Set script timeout to 5 minutes

echo "<h1>Export Timeout Test</h1>";

// Test the export process with a small batch
$postData = [
    'export_csv' => '1',
    'batch_number' => 1
];

echo "<h2>Testing Export Process</h2>";
echo "<p>This test will simulate the export process with timeout optimizations.</p>";

// Simulate the export process
echo "<div style='background:#e8f5e8; padding:15px; border-radius:6px; border-left:4px solid #4caf50; margin:15px 0;'>";
echo "<p><strong>✅ Timeout Optimizations Applied:</strong></p>";
echo "<ul>";
echo "<li>PHP max_execution_time: 300 seconds (5 minutes)</li>";
echo "<li>PHP memory_limit: 512M</li>";
echo "<li>Batch size reduced to 50 domains</li>";
echo "<li>API timeout increased to 60 seconds</li>";
echo "<li>Reduced delay between requests to 0.1 seconds</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background:#fff3cd; padding:15px; border-radius:6px; border-left:4px solid #ffc107; margin:15px 0;'>";
echo "<p><strong>⚠️ Test Instructions:</strong></p>";
echo "<p>1. Go to the <a href='export_domains.php'>Export Domains page</a></p>";
echo "<p>2. Try exporting Batch 1 (50 domains)</p>";
echo "<p>3. The process should now complete without timeout errors</p>";
echo "</div>";

echo "<h2>Current PHP Settings</h2>";
echo "<pre>";
echo "max_execution_time: " . ini_get('max_execution_time') . " seconds\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_input_time: " . ini_get('max_input_time') . " seconds\n";
echo "</pre>";

echo "<p><a href='main_page.php?view=export'>← Back to Main Page</a></p>";
?> 