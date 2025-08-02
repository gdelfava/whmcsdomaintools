<?php
/**
 * Firebase Authentication Test Script
 * 
 * This script tests if Firebase authentication is working correctly.
 */

require_once 'config.php';

echo "=== Firebase Authentication Test ===\n\n";

// Test Firebase configuration
echo "Firebase Configuration:\n";
echo "API Key: " . substr($firebaseConfig['apiKey'], 0, 10) . "...\n";
echo "Auth Domain: " . $firebaseConfig['authDomain'] . "\n";
echo "Project ID: " . $firebaseConfig['projectId'] . "\n\n";

// Test Firebase API connectivity
echo "Testing Firebase API connectivity...\n";

$testUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . $firebaseConfig['apiKey'];
$testData = [
    'email' => 'test@example.com',
    'password' => 'testpassword',
    'returnSecureToken' => true
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "❌ Connection Error: " . $curl_error . "\n";
} else {
    echo "✅ Connection successful (HTTP " . $httpCode . ")\n";
    
    $result = json_decode($response, true);
    if (isset($result['error'])) {
        if (strpos($result['error']['message'], 'API_KEY_NOT_VALID') !== false) {
            echo "❌ API Key Error: " . $result['error']['message'] . "\n";
            echo "   Please check your Firebase API key in the .env file\n";
        } else {
            echo "✅ API Key is valid (expected error for test credentials)\n";
            echo "   Error: " . $result['error']['message'] . "\n";
        }
    } else {
        echo "✅ API Key is valid\n";
    }
}

echo "\nTest completed!\n";
?> 