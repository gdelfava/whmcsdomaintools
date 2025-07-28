<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables from .env file if it exists
function loadEnv($file) {
    if (!file_exists($file)) {
        return;
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue; // Skip comments
        }
        
        $pos = strpos($line, '=');
        if ($pos !== false) {
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Load .env file if it exists
loadEnv(__DIR__ . '/.env');

// Helper function to get environment variable with fallback
function getEnvVar($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false ? $value : $default;
}

// === FIREBASE CONFIGURATION ===
// Load from environment variables for security
$firebaseConfig = [
    'apiKey' => getEnvVar('FIREBASE_API_KEY', 'AIzaSyBFuMs8tWaM35HDsTGu6DZW7Onx1hZFR5A'),
    'authDomain' => getEnvVar('FIREBASE_AUTH_DOMAIN', 'whmcs-tools.firebaseapp.com'),
    'projectId' => getEnvVar('FIREBASE_PROJECT_ID', 'whmcs-tools'),
    'storageBucket' => getEnvVar('FIREBASE_STORAGE_BUCKET', 'whmcs-tools.firebasestorage.app'),
    'messagingSenderId' => getEnvVar('FIREBASE_MESSAGING_SENDER_ID', '879726828774'),
    'appId' => getEnvVar('FIREBASE_APP_ID', '1:879726828774:web:ab8732909f6ba873626f27')
];

// Firebase REST API endpoints
$firebaseAuthUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:';
$firebaseApiKey = $firebaseConfig['apiKey'];

// === WHMCS CONFIGURATION ===
// These are now handled by user settings, but keeping commented for reference
// $apiUrl = 'https://yourdomain.com/includes/api.php'; // Replace with your WHMCS API URL
// $apiIdentifier = 'your_api_identifier'; // Replace with your WHMCS API Identifier
// $apiSecret = 'your_api_secret';         // Replace with your WHMCS API Secret
// $logFile = 'ns_update_log.txt';         // Log file to store update results

// $defaultNs1 = 'ns1.yourdomain.com'; // Set your default nameservers here
// $defaultNs2 = 'ns2.yourdomain.com';
?> 