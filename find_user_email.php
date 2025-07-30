<?php
// Script to find what email corresponds to the existing settings files

$settingsFiles = glob('user_settings/*.json');

echo "<h1>Settings Files Analysis</h1>";

foreach ($settingsFiles as $file) {
    $hash = basename($file, '.json');
    echo "<h2>File: " . htmlspecialchars($file) . "</h2>";
    echo "<p><strong>Hash:</strong> " . htmlspecialchars($hash) . "</p>";
    
    // Try some common emails
    $testEmails = [
        'admin@fridgehosting.co.za',
        'admin@fridgehosting.com',
        'test@fridgehosting.co.za',
        'test@fridgehosting.com',
        'user@fridgehosting.co.za',
        'user@fridgehosting.com',
        'info@fridgehosting.co.za',
        'info@fridgehosting.com',
        'support@fridgehosting.co.za',
        'support@fridgehosting.com'
    ];
    
    foreach ($testEmails as $email) {
        if (md5($email) === $hash) {
            echo "<p><strong>✅ Found matching email:</strong> " . htmlspecialchars($email) . "</p>";
            break;
        }
    }
    
    // Show file contents
    $content = file_get_contents($file);
    $settings = json_decode($content, true);
    if ($settings) {
        echo "<p><strong>API URL:</strong> " . htmlspecialchars($settings['api_url'] ?? 'N/A') . "</p>";
        echo "<p><strong>Default NS1:</strong> " . htmlspecialchars($settings['default_ns1'] ?? 'N/A') . "</p>";
        echo "<p><strong>Default NS2:</strong> " . htmlspecialchars($settings['default_ns2'] ?? 'N/A') . "</p>";
    }
    
    echo "<hr>";
}
?> 