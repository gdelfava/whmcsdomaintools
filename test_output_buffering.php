<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

// Set JSON headers
header('Content-Type: application/json');

// Start output buffering to catch any unexpected output
ob_start();

try {
    require_once 'config.php';
    require_once 'user_settings_db.php';
    
    $userEmail = $_SESSION['user_email'] ?? '';
    $companyId = $_SESSION['company_id'] ?? null;
    
    if (empty($userEmail)) {
        throw new Exception('No user email in session');
    }
    
    if (empty($companyId)) {
        throw new Exception('No company ID in session');
    }
    
    // Get user settings
    $userSettings = new UserSettingsDB();
    $settings = $userSettings->loadSettings($companyId, $userEmail);
    
    if (!$settings) {
        throw new Exception('User settings not found');
    }
    
    // Use settings directly (already decrypted by loadSettings)
    $apiUrl = $settings['api_url'];
    $apiIdentifier = $settings['api_identifier'];
    $apiSecret = $settings['api_secret'];
    
    // Test API call
    require_once 'api.php';
    
    $apiResponse = curlCall($apiUrl, [
        'action' => 'GetClientsDomains',
        'identifier' => $apiIdentifier,
        'secret' => $apiSecret,
        'limitstart' => 0,
        'limitnum' => 1,
        'responsetype' => 'json'
    ]);
    
    // Get any output that was generated
    $unexpectedOutput = ob_get_contents();
    ob_end_clean();
    
    if (!empty($unexpectedOutput)) {
        echo json_encode([
            'success' => false,
            'error' => 'Unexpected output detected before JSON response',
            'output' => $unexpectedOutput
        ]);
        exit;
    }
    
    // Return the API response
    echo json_encode([
        'success' => true,
        'api_response' => $apiResponse
    ]);
    
} catch (Exception $e) {
    $unexpectedOutput = ob_get_contents();
    ob_end_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'unexpected_output' => $unexpectedOutput
    ]);
} 