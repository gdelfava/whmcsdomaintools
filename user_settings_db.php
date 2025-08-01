<?php
require_once 'auth_v2.php';
require_once 'database_v2.php';

class UserSettingsDB {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function saveSettings($companyId, $userEmail, $settings) {
        // Encrypt sensitive data before saving to database
        $encryptedSettings = [
            'api_url' => $settings['api_url'],
            'api_identifier' => $this->encrypt($settings['api_identifier']),
            'api_secret' => $this->encrypt($settings['api_secret']),
            'default_ns1' => $settings['default_ns1'],
            'default_ns2' => $settings['default_ns2']
        ];
        
        return $this->db->saveUserSettings($companyId, $userEmail, $encryptedSettings);
    }
    
    public function loadSettings($companyId, $userEmail) {
        $settings = $this->db->getUserSettings($companyId, $userEmail);
        
        if (!$settings) {
            error_log("UserSettingsDB: No settings found for user: $userEmail in company: $companyId");
            return null;
        }
        
        try {
            // Decrypt sensitive data
            $decryptedSettings = [
                'api_url' => $settings['api_url'] ?? '',
                'api_identifier' => $this->decrypt($settings['api_identifier'] ?? ''),
                'api_secret' => $this->decrypt($settings['api_secret'] ?? ''),
                'default_ns1' => $settings['default_ns1'] ?? '',
                'default_ns2' => $settings['default_ns2'] ?? '',
                'created_at' => $settings['created_at'] ?? null,
                'updated_at' => $settings['updated_at'] ?? null
            ];
            
            error_log("UserSettingsDB: Successfully loaded and decrypted settings for user: $userEmail in company: $companyId");
            return $decryptedSettings;
            
        } catch (Exception $e) {
            error_log("UserSettingsDB: Decryption failed for user $userEmail in company $companyId: " . $e->getMessage());
            return null;
        }
    }
    
    public function hasSettings($companyId, $userEmail) {
        return $this->db->hasUserSettings($companyId, $userEmail);
    }
    
    public function deleteSettings($companyId, $userEmail) {
        return $this->db->deleteUserSettings($companyId, $userEmail);
    }
    
    private function encrypt($data) {
        $key = $this->getEncryptionKey();
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt($data) {
        $key = $this->getEncryptionKey();
        $cipher = "AES-256-CBC";
        $data = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($data, 0, $ivlen);
        $encrypted = substr($data, $ivlen);
        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    }
    
    private function getEncryptionKey() {
        // Use a combination of server-specific data for encryption key
        $serverKey = $_SERVER['SERVER_NAME'] ?? 'default_server';
        $configKey = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_encryption_key_2024';
        return hash('sha256', $serverKey . $configKey);
    }
    
    // Public methods for testing purposes
    public function encryptPublic($data) {
        return $this->encrypt($data);
    }
    
    public function decryptPublic($data) {
        return $this->decrypt($data);
    }
}

// Helper function to get current user's settings (database version)
function getUserSettingsDB() {
    if (!isset($_SESSION['user_email']) || !isset($_SESSION['company_id'])) {
        error_log('getUserSettingsDB: No user email or company_id in session');
        return null;
    }
    
    $userSettings = new UserSettingsDB();
    $settings = $userSettings->loadSettings($_SESSION['company_id'], $_SESSION['user_email']);
    
    if ($settings) {
        error_log('getUserSettingsDB: Successfully loaded settings for user: ' . $_SESSION['user_email']);
    } else {
        error_log('getUserSettingsDB: No settings found for user: ' . $_SESSION['user_email']);
    }
    
    return $settings;
}

// Helper function to check if user has configured settings (database version)
function userHasSettingsDB() {
    if (!isset($_SESSION['user_email']) || !isset($_SESSION['company_id'])) {
        return false;
    }
    
    $userSettings = new UserSettingsDB();
    return $userSettings->hasSettings($_SESSION['company_id'], $_SESSION['user_email']);
}

// Helper function to validate settings completeness and return missing fields (database version)
function validateSettingsCompletenessDB() {
    if (!isset($_SESSION['user_email']) || !isset($_SESSION['company_id'])) {
        return ['missing' => ['authentication'], 'message' => 'User not authenticated'];
    }
    
    $userSettings = new UserSettingsDB();
    $settings = $userSettings->loadSettings($_SESSION['company_id'], $_SESSION['user_email']);
    
    if (!$settings) {
        return ['missing' => ['all'], 'message' => 'No settings configured'];
    }
    
    $missing = [];
    
    if (empty($settings['api_url'])) {
        $missing[] = 'API URL';
    }
    
    if (empty($settings['api_identifier'])) {
        $missing[] = 'API Identifier';
    }
    
    if (empty($settings['api_secret'])) {
        $missing[] = 'API Secret';
    }
    
    if (empty($settings['default_ns1'])) {
        $missing[] = 'Primary Nameserver';
    }
    
    if (empty($settings['default_ns2'])) {
        $missing[] = 'Secondary Nameserver';
    }
    
    if (!empty($missing)) {
        return [
            'missing' => $missing,
            'message' => 'Missing required configuration: ' . implode(', ', $missing)
        ];
    }
    
    return ['missing' => [], 'message' => 'All settings configured'];
}

// Helper function to get logo URL with fallback (database version)
function getLogoUrlDB() {
    if (!isset($_SESSION['user_email']) || !isset($_SESSION['company_id'])) {
        return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgMTIwIDQwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cmVjdCB3aWR0aD0iMTIwIiBoZWlnaHQ9IjQwIiByeD0iOCIgZmlsbD0iI0Y5RkFGRiIvPgo8dGV4dCB4PSI2MCIgeT0iMjUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzM3NDE1MSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+V0hNQ1MgVG9vbHM8L3RleHQ+Cjwvc3ZnPgo=';
    }
    
    // Get company logo from database
    $db = Database::getInstance();
    $company = $db->getCompany($_SESSION['company_id']);
    
    if ($company && !empty($company['logo_url'])) {
        return $company['logo_url'];
    }
    
    // Fallback to a generic logo (base64 encoded SVG)
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgMTIwIDQwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cmVjdCB3aWR0aD0iMTIwIiBoZWlnaHQ9IjQwIiByeD0iOCIgZmlsbD0iI0Y5RkFGRiIvPgo8dGV4dCB4PSI2MCIgeT0iMjUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzM3NDE1MSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+V0hNQ1MgVG9vbHM8L3RleHQ+Cjwvc3ZnPgo=';
} 