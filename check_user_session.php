<?php
session_start();

echo "<h1>User Session Check</h1>";

echo "<h2>Session Data:</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

if (isset($_SESSION['user_email'])) {
    $email = $_SESSION['user_email'];
    $settingsFile = 'user_settings/' . md5($email) . '.json';
    
    echo "<h2>Settings File Check:</h2>";
    echo "<p><strong>User Email:</strong> " . htmlspecialchars($email) . "</p>";
    echo "<p><strong>Settings File:</strong> " . htmlspecialchars($settingsFile) . "</p>";
    echo "<p><strong>File Exists:</strong> " . (file_exists($settingsFile) ? 'Yes' : 'No') . "</p>";
    
    if (file_exists($settingsFile)) {
        echo "<p><strong>File Contents:</strong></p>";
        echo "<pre>" . htmlspecialchars(file_get_contents($settingsFile)) . "</pre>";
    }
} else {
    echo "<p><strong>No user email in session!</strong></p>";
}

echo "<h2>Available Settings Files:</h2>";
$files = glob('user_settings/*.json');
foreach ($files as $file) {
    echo "<p>" . htmlspecialchars($file) . "</p>";
}
?> 