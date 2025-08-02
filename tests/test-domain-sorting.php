<?php
/**
 * Domain Sorting Test Script
 * 
 * This script tests if domain sorting is working correctly.
 */

require_once 'config.php';
require_once 'api.php';
require_once 'user_settings.php';

echo "=== Domain Sorting Test ===\n\n";

// Check if user has settings
if (!userHasSettings()) {
    echo "❌ No user settings found. Please configure API settings first.\n";
    exit(1);
}

$userSettings = getUserSettings();
if (!$userSettings) {
    echo "❌ Unable to load user settings.\n";
    exit(1);
}

echo "✅ User settings loaded successfully.\n";
echo "API URL: " . $userSettings['api_url'] . "\n\n";

// Get domains with caching
echo "Fetching domains from API...\n";
$response = getAllDomains($userSettings['api_url'], $userSettings['api_identifier'], $userSettings['api_secret']);

if (!isset($response['domains']['domain']) || empty($response['domains']['domain'])) {
    echo "❌ No domains found in API response.\n";
    exit(1);
}

$domains = $response['domains']['domain'];
echo "✅ Found " . count($domains) . " domains.\n\n";

// Test sorting
echo "Testing domain sorting...\n";
echo "First 10 domains (should be in alphabetical order):\n";
echo str_repeat("-", 50) . "\n";

for ($i = 0; $i < min(10, count($domains)); $i++) {
    $domain = $domains[$i];
    echo sprintf("%2d. %s\n", $i + 1, $domain['domainname']);
}

echo str_repeat("-", 50) . "\n";

// Verify sorting is correct
$isSorted = true;
for ($i = 1; $i < count($domains); $i++) {
    $prevDomain = strtolower($domains[$i - 1]['domainname']);
    $currentDomain = strtolower($domains[$i]['domainname']);
    
    if (strcmp($prevDomain, $currentDomain) > 0) {
        $isSorted = false;
        echo "❌ Sorting error: '$prevDomain' should come after '$currentDomain'\n";
        break;
    }
}

if ($isSorted) {
    echo "✅ Domain list is properly sorted alphabetically!\n";
} else {
    echo "❌ Domain list is not properly sorted.\n";
}

echo "\nTest completed!\n";
?> 