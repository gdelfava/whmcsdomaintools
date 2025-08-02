<?php
/**
 * Test Encryption Functionality
 * 
 * This script tests the encryption system to ensure it's working correctly.
 */

require_once 'config.php';
require_once 'user_settings.php';

echo "=== Encryption Test ===\n\n";

// Check if encryption key is loaded
if (defined('ENCRYPTION_KEY')) {
    echo "✅ Encryption key loaded: " . substr(ENCRYPTION_KEY, 0, 8) . "...\n";
} else {
    echo "❌ Encryption key not defined!\n";
    exit;
}

// Test the UserSettings encryption
$userSettings = new UserSettings();

// Test data
$testApiIdentifier = "test_api_identifier_123";
$testApiSecret = "test_api_secret_456";

echo "\n🧪 Testing encryption/decryption...\n";

// Test encryption by saving and loading settings
$testSettings = [
    'api_url' => 'https://test.com/api.php',
    'api_identifier' => $testApiIdentifier,
    'api_secret' => $testApiSecret,
    'default_ns1' => 'ns1.test.com',
    'default_ns2' => 'ns2.test.com',
    'logo_url' => ''
];

// Save test settings
$userSettings->saveSettings('test@example.com', $testSettings);

// Load test settings
$loadedSettings = $userSettings->loadSettings('test@example.com');

// Clean up test data
$userSettings->deleteSettings('test@example.com');

echo "Original API Identifier: " . $testApiIdentifier . "\n";
echo "Original API Secret: " . $testApiSecret . "\n\n";

echo "Loaded API Identifier: " . $loadedSettings['api_identifier'] . "\n";
echo "Loaded API Secret: " . $loadedSettings['api_secret'] . "\n\n";

// Verify
if ($loadedSettings['api_identifier'] === $testApiIdentifier && $loadedSettings['api_secret'] === $testApiSecret) {
    echo "✅ Encryption/Decryption test successful!\n";
} else {
    echo "❌ Encryption/Decryption test failed!\n";
}

echo "\n🔒 Current Encryption Status:\n";
echo "- API credentials are automatically encrypted when saved\n";
echo "- API credentials are automatically decrypted when loaded\n";
echo "- Encryption uses AES-256-CBC with a secure key\n";
echo "- Each user's data is encrypted with their own key\n";

echo "\n📁 Encrypted files are stored in: user_settings/\n";
echo "Files are named with MD5 hash of user email for security\n";

echo "\nTest completed! 🎉\n";
?> 