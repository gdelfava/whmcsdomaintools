<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

echo "<h1>📁 Available CSV Export Files</h1>";

// Get all CSV files in the current directory
$csvFiles = glob("*.csv");

if (empty($csvFiles)) {
    echo "<p>No CSV files found.</p>";
    echo "<p><a href='export_progress.php'>← Back to Export Progress</a></p>";
    exit;
}

echo "<p>Found " . count($csvFiles) . " CSV file(s):</p>";

echo "<div style='margin: 20px 0;'>";
foreach ($csvFiles as $file) {
    $fileSize = filesize($file);
    $fileDate = date("Y-m-d H:i:s", filemtime($file));
    
    echo "<div style='margin: 10px 0; padding: 15px; background-color: #f8f9fa; border-radius: 8px; border-left: 4px solid #007bff;'>";
    echo "<h4 style='margin: 0 0 10px 0; color: #007bff;'>📄 " . htmlspecialchars($file) . "</h4>";
    echo "<p style='margin: 5px 0; color: #666;'><strong>Size:</strong> " . number_format($fileSize) . " bytes</p>";
    echo "<p style='margin: 5px 0; color: #666;'><strong>Created:</strong> " . $fileDate . "</p>";
    echo "<a href='" . htmlspecialchars($file) . "' download style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>📥 Download</a>";
    echo "</div>";
}
echo "</div>";

echo "<div style='margin-top: 20px;'>";
echo "<a href='export_progress.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>🔄 Export Another Batch</a>";
echo "<a href='main_page.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-left: 10px;'>🏠 Back to Main Page</a>";
echo "</div>";
?> 