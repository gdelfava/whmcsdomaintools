<?php
/**
 * Generate Secure Encryption Key
 * 
 * This script generates a cryptographically secure encryption key
 * for encrypting sensitive user data like API credentials.
 */

echo "=== Generate Secure Encryption Key ===\n\n";

// Generate a cryptographically secure random key
$encryptionKey = bin2hex(random_bytes(32)); // 64 characters (32 bytes)

echo "✅ Generated secure encryption key:\n";
echo "Key: " . $encryptionKey . "\n\n";

echo "📝 Instructions:\n";
echo "1. Copy the key above\n";
echo "2. Add it to your .env file:\n";
echo "   ENCRYPTION_KEY=" . $encryptionKey . "\n\n";

echo "🔒 Security Notes:\n";
echo "- This key is 64 characters long (32 bytes)\n";
echo "- It's cryptographically secure\n";
echo "- Keep this key secret and backup safely\n";
echo "- If you change this key, existing encrypted data will need to be re-encrypted\n\n";

echo "⚠️  Important: If you already have encrypted data, changing the key will make it unreadable!\n";
echo "Only generate a new key if you're setting up for the first time.\n\n";

// Test the key
echo "🧪 Testing encryption with generated key...\n";
define('ENCRYPTION_KEY', $encryptionKey);

// Simple test
$testData = "test_api_credential_123";
$cipher = "AES-256-CBC";
$ivlen = openssl_cipher_iv_length($cipher);
$iv = openssl_random_pseudo_bytes($ivlen);
$encrypted = openssl_encrypt($testData, $cipher, $encryptionKey, 0, $iv);
$encryptedData = base64_encode($iv . $encrypted);

$decrypted = openssl_decrypt(
    substr(base64_decode($encryptedData), $ivlen),
    $cipher,
    $encryptionKey,
    0,
    substr(base64_decode($encryptedData), 0, $ivlen)
);

if ($decrypted === $testData) {
    echo "✅ Encryption test successful!\n";
} else {
    echo "❌ Encryption test failed!\n";
}

echo "\nSetup complete! 🎉\n";
?> 