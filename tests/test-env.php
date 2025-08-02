<?php
/**
 * Environment Test Script
 * 
 * This script tests if environment variables are loaded correctly.
 */

require_once 'config.php';

echo "=== Environment Variables Test ===\n\n";

// Test if environment variables are loaded
echo "Firebase Configuration:\n";
echo "API Key: " . (isset($firebaseConfig['apiKey']) ? '✅ Loaded' : '❌ Not loaded') . "\n";
echo "Auth Domain: " . (isset($firebaseConfig['authDomain']) ? '✅ Loaded' : '❌ Not loaded') . "\n";
echo "Project ID: " . (isset($firebaseConfig['projectId']) ? '✅ Loaded' : '❌ Not loaded') . "\n";
echo "Storage Bucket: " . (isset($firebaseConfig['storageBucket']) ? '✅ Loaded' : '❌ Not loaded') . "\n";
echo "Messaging Sender ID: " . (isset($firebaseConfig['messagingSenderId']) ? '✅ Loaded' : '❌ Not loaded') . "\n";
echo "App ID: " . (isset($firebaseConfig['appId']) ? '✅ Loaded' : '❌ Not loaded') . "\n\n";

// Check if .env file exists
if (file_exists('.env')) {
    echo "✅ .env file found\n";
} else {
    echo "⚠️  .env file not found - using default values\n";
}

echo "\nTest completed!\n";
?> 