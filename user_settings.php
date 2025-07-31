<?php
// require_once 'auth.php';
// If needed, use:
// require_once 'auth_v2.php';

class UserSettings {
    private $settingsDir = 'user_settings';
    
    public function __construct() {
        // Create settings directory if it doesn't exist
        if (!is_dir($this->settingsDir)) {
            mkdir($this->settingsDir, 0755, true);
        }
    }
    
    private function getSettingsFile($userId) {
        return $this->settingsDir . '/' . md5($userId) . '.json';
    }
    
    public function saveSettings($userId, $settings) {
        $settingsFile = $this->getSettingsFile($userId);
        
        // Encrypt sensitive data
        $encryptedSettings = [
            'api_url' => $settings['api_url'],
            'api_identifier' => $this->encrypt($settings['api_identifier']),
            'api_secret' => $this->encrypt($settings['api_secret']),
            'default_ns1' => $settings['default_ns1'],
            'default_ns2' => $settings['default_ns2'],
            'logo_url' => $settings['logo_url'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return file_put_contents($settingsFile, json_encode($encryptedSettings, JSON_PRETTY_PRINT)) !== false;
    }
    
    public function loadSettings($userId) {
        $settingsFile = $this->getSettingsFile($userId);
        
        if (!file_exists($settingsFile)) {
            error_log("UserSettings: Settings file not found for user: $userId");
            return null;
        }
        
        $content = file_get_contents($settingsFile);
        if ($content === false) {
            error_log("UserSettings: Failed to read settings file for user: $userId");
            return null;
        }
        
        $settings = json_decode($content, true);
        
        if (!$settings) {
            error_log("UserSettings: Failed to decode JSON for user: $userId");
            return null;
        }
        
        try {
            // Decrypt sensitive data with error handling
            $decryptedSettings = [
                'api_url' => $settings['api_url'] ?? '',
                'api_identifier' => $this->decrypt($settings['api_identifier'] ?? ''),
                'api_secret' => $this->decrypt($settings['api_secret'] ?? ''),
                'default_ns1' => $settings['default_ns1'] ?? '',
                'default_ns2' => $settings['default_ns2'] ?? '',
                'logo_url' => $settings['logo_url'] ?? '',
                'created_at' => $settings['created_at'] ?? null,
                'updated_at' => $settings['updated_at'] ?? null
            ];
            
            error_log("UserSettings: Successfully loaded and decrypted settings for user: $userId");
            return $decryptedSettings;
            
        } catch (Exception $e) {
            error_log("UserSettings: Decryption failed for user $userId: " . $e->getMessage());
            return null;
        }
    }
    
    public function hasSettings($userId) {
        $settingsFile = $this->getSettingsFile($userId);
        return file_exists($settingsFile);
    }
    
    public function deleteSettings($userId) {
        $settingsFile = $this->getSettingsFile($userId);
        if (file_exists($settingsFile)) {
            return unlink($settingsFile);
        }
        return true;
    }
    
    // Public methods for testing purposes
    public function encryptPublic($data) {
        return $this->encrypt($data);
    }
    
    public function decryptPublic($data) {
        return $this->decrypt($data);
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
}

// Helper function to get current user's settings
function getUserSettings() {
    if (!isset($_SESSION['user_email'])) {
        error_log('getUserSettings: No user email in session');
        return null;
    }
    
    $userSettings = new UserSettings();
    $settings = $userSettings->loadSettings($_SESSION['user_email']);
    
    if ($settings) {
        error_log('getUserSettings: Successfully loaded settings for user: ' . $_SESSION['user_email']);
    } else {
        error_log('getUserSettings: No settings found for user: ' . $_SESSION['user_email']);
    }
    
    return $settings;
}

// Helper function to check if user has configured settings
function userHasSettings() {
    if (!isset($_SESSION['user_email'])) {
        return false;
    }
    
    $userSettings = new UserSettings();
    return $userSettings->hasSettings($_SESSION['user_email']);
}

// Helper function to validate settings completeness and return missing fields
function validateSettingsCompleteness() {
    if (!isset($_SESSION['user_email'])) {
        return ['missing' => ['authentication'], 'message' => 'User not authenticated'];
    }
    
    $userSettings = new UserSettings();
    $settings = $userSettings->loadSettings($_SESSION['user_email']);
    
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
    
    // Logo URL is optional, so we don't add it to missing array
    // But we'll include it in the validation result for reference
    
    if (!empty($missing)) {
        return [
            'missing' => $missing,
            'message' => 'Missing required configuration: ' . implode(', ', $missing)
        ];
    }
    
    return ['missing' => [], 'message' => 'All settings configured'];
}

// Helper function to get logo URL with fallback
function getLogoUrl() {
    if (!isset($_SESSION['user_email'])) {
        return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgMTIwIDQwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cmVjdCB3aWR0aD0iMTIwIiBoZWlnaHQ9IjQwIiByeD0iOCIgZmlsbD0iI0Y5RkFGRiIvPgo8dGV4dCB4PSI2MCIgeT0iMjUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzM3NDE1MSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+V0hNQ1MgVG9vbHM8L3RleHQ+Cjwvc3ZnPgo=';
    }
    
    $userSettings = new UserSettings();
    $settings = $userSettings->loadSettings($_SESSION['user_email']);
    
    if ($settings && !empty($settings['logo_url'])) {
        return $settings['logo_url'];
    }
    
    // Fallback to a generic logo (base64 encoded SVG)
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgMTIwIDQwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cmVjdCB3aWR0aD0iMTIwIiBoZWlnaHQ9IjQwIiByeD0iOCIgZmlsbD0iI0Y5RkFGRiIvPgo8dGV4dCB4PSI2MCIgeT0iMjUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzM3NDE1MSIgdGV4dC1hbmNob3I9Im1pZGRsZSI+V0hNQ1MgVG9vbHM8L3RleHQ+Cjwvc3ZnPgo=';
}
?> 