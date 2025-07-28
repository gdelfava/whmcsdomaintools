<?php
/**
 * Environment Setup Script
 * 
 * This script helps you create a .env file with your Firebase configuration.
 * Run this script once to set up your environment variables securely.
 */

echo "=== WHMCS Domain Tools Environment Setup ===\n\n";

// Check if .env already exists
if (file_exists('.env')) {
    echo "⚠️  Warning: .env file already exists!\n";
    echo "This will overwrite your existing .env file.\n\n";
    
    $continue = readline("Do you want to continue? (y/N): ");
    if (strtolower($continue) !== 'y') {
        echo "Setup cancelled.\n";
        exit;
    }
}

echo "Please enter your Firebase configuration:\n\n";

// Get Firebase configuration from user
$firebaseApiKey = readline("Firebase API Key: ");
$firebaseAuthDomain = readline("Firebase Auth Domain (e.g., your-project.firebaseapp.com): ");
$firebaseProjectId = readline("Firebase Project ID: ");
$firebaseStorageBucket = readline("Firebase Storage Bucket (e.g., your-project.firebasestorage.app): ");
$firebaseMessagingSenderId = readline("Firebase Messaging Sender ID: ");
$firebaseAppId = readline("Firebase App ID: ");

// Optional encryption key
$encryptionKey = readline("Custom Encryption Key (optional, press Enter to skip): ");

// Create .env content
$envContent = "# Firebase Configuration\n";
$envContent .= "FIREBASE_API_KEY=" . $firebaseApiKey . "\n";
$envContent .= "FIREBASE_AUTH_DOMAIN=" . $firebaseAuthDomain . "\n";
$envContent .= "FIREBASE_PROJECT_ID=" . $firebaseProjectId . "\n";
$envContent .= "FIREBASE_STORAGE_BUCKET=" . $firebaseStorageBucket . "\n";
$envContent .= "FIREBASE_MESSAGING_SENDER_ID=" . $firebaseMessagingSenderId . "\n";
$envContent .= "FIREBASE_APP_ID=" . $firebaseAppId . "\n";

if (!empty($encryptionKey)) {
    $envContent .= "\n# Optional: Encryption key for additional security\n";
    $envContent .= "ENCRYPTION_KEY=" . $encryptionKey . "\n";
}

// Write .env file
if (file_put_contents('.env', $envContent)) {
    echo "\n✅ .env file created successfully!\n";
    echo "Your sensitive configuration is now secure and won't be committed to Git.\n\n";
    
    echo "Next steps:\n";
    echo "1. Test your configuration by running: php -S localhost:8000\n";
    echo "2. Visit http://localhost:8000 in your browser\n";
    echo "3. Configure your WHMCS API settings in the web interface\n\n";
} else {
    echo "\n❌ Error: Could not create .env file. Please check file permissions.\n";
}

echo "Setup complete!\n";
?> 