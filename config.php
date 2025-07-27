<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === FIREBASE CONFIGURATION ===
// Replace these with your Firebase project details
$firebaseConfig = [
    'apiKey' => 'AIzaSyBFuMs8tWaM35HDsTGu6DZW7Onx1hZFR5A',
    'authDomain' => 'whmcs-tools.firebaseapp.com',
    'projectId' => 'whmcs-tools',
    'storageBucket' => 'whmcs-tools.firebasestorage.app',
    'messagingSenderId' => '879726828774',
    'appId' => '1:879726828774:web:ab8732909f6ba873626f27'
];

// Firebase REST API endpoints
$firebaseAuthUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:';
$firebaseApiKey = $firebaseConfig['apiKey'];

// // === WHMCS CONFIGURATION ===
// $apiUrl = 'https://www.fridgehosting.co.za/clientportal/includes/api.php'; // Replace with your WHMCS API URL
// $apiIdentifier = 'IQ71k2KxUdLonoSHBCNhsgGOrPYbnLVq'; // Replace with your WHMCS API Identifier
// $apiSecret = '539yexT4fAhDn3FbMlnRicHowDKnVe5l';         // Replace with your WHMCS API Secret
// $logFile = 'ns_update_log.txt';         // Log file to store update results

// $defaultNs1 = 'ns1.aredlak.com'; // Set your default nameservers here
// $defaultNs2 = 'ns2.aredlak.com';
?> 