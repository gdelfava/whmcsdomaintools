<?php
require_once 'user_settings_db.php';

// Require authentication
// requireAuth(); // This line is removed as per the edit hint.

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

$message = '';
$messageType = '';

// Handle settings save
if (isset($_POST['save_settings'])) {
    $requiredFields = ['api_url', 'api_identifier', 'api_secret', 'default_ns1', 'default_ns2'];
    $allFieldsProvided = true;
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $allFieldsProvided = false;
            break;
        }
    }
    
    if ($allFieldsProvided) {
        $settings = [
            'api_url' => trim($_POST['api_url']),
            'api_identifier' => trim($_POST['api_identifier']),
            'api_secret' => trim($_POST['api_secret']),
            'default_ns1' => trim($_POST['default_ns1']),
            'default_ns2' => trim($_POST['default_ns2']),
            'logo_url' => trim($_POST['logo_url'] ?? '')
        ];
        
        $userSettings = new UserSettingsDB();
        if ($userSettings->saveSettings($_SESSION['company_id'], $_SESSION['user_email'], $settings)) {
            $message = '✅ Settings saved successfully! Your API data will persist across login sessions and devices.';
            $messageType = 'success';
            
            // Log successful save
            error_log('Settings saved successfully to database for user: ' . $_SESSION['user_email']);
            
            // If there's a redirect parameter, redirect after saving
            if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                $redirectUrl = basename($_GET['redirect']); // Security: only allow filenames
                header("Location: $redirectUrl");
                exit;
            }
        } else {
            $message = '❌ Failed to save settings. Please check database connection and permissions.';
            $messageType = 'error';
            error_log('Failed to save settings to database for user: ' . $_SESSION['user_email']);
        }
    } else {
        $message = '⚠️ Please fill in all required fields.';
        $messageType = 'error';
    }
}

// Handle user profile save
if (isset($_POST['save_profile'])) {
    $db = Database::getInstance();
    $user = $db->getUserByEmail($_SESSION['user_email']);
    
    if ($user) {
        $userData = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? '')
        ];
        
        if ($db->updateUser($user['id'], $userData)) {
            $message = '✅ User profile updated successfully!';
            $messageType = 'success';
            error_log('User profile updated successfully for user: ' . $_SESSION['user_email']);
        } else {
            $message = '❌ Failed to update user profile. Please try again.';
            $messageType = 'error';
            error_log('Failed to update user profile for user: ' . $_SESSION['user_email']);
        }
    } else {
        $message = '❌ User not found. Please log in again.';
        $messageType = 'error';
    }
}

// Handle company settings save
if (isset($_POST['save_company'])) {
    $db = Database::getInstance();
    
    $companyData = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'company_address' => trim($_POST['company_address'] ?? ''),
        'contact_number' => trim($_POST['contact_number'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'logo_url' => trim($_POST['company_logo_url'] ?? '')
    ];
    
    if ($db->updateCompany($_SESSION['company_id'], $companyData)) {
        $message = '✅ Company settings updated successfully!';
        $messageType = 'success';
        error_log('Company settings updated successfully for company: ' . $_SESSION['company_id']);
    } else {
        $message = '❌ Failed to update company settings. Please try again.';
        $messageType = 'error';
        error_log('Failed to update company settings for company: ' . $_SESSION['company_id']);
    }
}

// Handle settings test
if (isset($_POST['test_settings'])) {
    $userSettings = getUserSettingsDB();
    if ($userSettings) {
        // Include API functions to test the connection
        require_once 'api.php';
        
        // Test API connection
        $testResponse = curlCall($userSettings['api_url'], [
            'action' => 'GetClients',
            'identifier' => $userSettings['api_identifier'],
            'secret' => $userSettings['api_secret'],
            'responsetype' => 'json',
            'limitnum' => 1
        ]);
        
        if (isset($testResponse['result']) && $testResponse['result'] === 'success') {
            $message = 'API connection test successful!';
            $messageType = 'success';
        } else {
            $error = $testResponse['message'] ?? 'Unknown error';
            $message = 'API connection test failed: ' . htmlspecialchars($error);
            $messageType = 'error';
        }
    } else {
        $message = 'No settings found. Please save your settings first.';
        $messageType = 'error';
    }
}

// Load existing settings
$currentSettings = getUserSettingsDB();

// Debug: If no settings found in database, try old system
if (!$currentSettings) {
    require_once 'user_settings.php';
    $currentSettings = getUserSettings();
    if ($currentSettings) {
        error_log('Settings loaded from JSON file as fallback');
    }
}

// Load user profile data
$db = Database::getInstance();
$currentUser = $db->getUserByEmail($_SESSION['user_email'] ?? '');
$currentCompany = $db->getCompany($_SESSION['company_id'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <div class="page-wrapper">
        <div class="container">
            <!-- Navigation Bar -->
            <nav class="navbar">
                <div class="navbar-brand">
                    <img src="<?= htmlspecialchars(getLogoUrlDB()) ?>" alt="Logo" onerror="this.style.display='none'">
                    <div>
                        <div class="font-semibold text-gray-900">WHMCS Domain Tools</div>
                        <div class="text-xs text-gray-500">API Configuration</div>
                    </div>
                </div>
                <div class="navbar-user">
                    <span>Logged in as <span class="user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></span></span>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="logout" class="logout-btn">Logout</button>
                    </form>
                </div>
            </nav>

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <?php 
                $backUrl = 'main_page.php';
                if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                    $backUrl = basename($_GET['redirect']);
                }
                ?>
                <a href="<?= htmlspecialchars($backUrl) ?>">Dashboard</a>
                <span class="breadcrumb-separator">/</span>
                <span>Settings</span>
            </nav>

            <!-- Main Settings Card -->
            <div class="main-card">
                <!-- Card Header -->
                <div class="card-header">
                                    <div class="card-header-content">
                    <h1 class="page-title">⚙️ Settings & Profile</h1>
                    <p class="page-subtitle">Configure your API credentials, user profile, and company settings</p>
                </div>
                </div>

                <!-- Card Body -->
                <div class="card-body">
                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <div class="flex items-center gap-3">
                                <span style="font-size: 1.25rem;">
                                    <?= $messageType === 'success' ? '✅' : '❌' ?>
                                </span>
                                <div><?= htmlspecialchars($message) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Security Notice -->
                    <div class="alert alert-info">
                        <div class="flex items-center gap-3">
                            <span style="font-size: 1.5rem;">🔒</span>
                            <div>
                                <div class="font-semibold">Security Notice</div>
                                <div class="text-sm mt-1">Your API credentials are encrypted using AES-256-CBC and stored securely. They are only accessible to your account and never shared with third parties.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Form -->
                    <form method="POST" class="space-y-6">
                        <!-- WHMCS API Configuration Section -->
                        <div class="card-section">
                            <h3 class="section-title">
                                <span class="icon">🔗</span>
                                WHMCS API Configuration
                            </h3>
                            
                            <div class="grid grid-cols-1 gap-6">
                                <div class="form-group">
                                    <label for="api_url" class="form-label">WHMCS API URL *</label>
                                    <input 
                                        type="url" 
                                        id="api_url" 
                                        name="api_url" 
                                        class="form-input"
                                        required 
                                        value="<?= htmlspecialchars($currentSettings['api_url'] ?? '') ?>"
                                        placeholder="https://yourdomain.com/includes/api.php"
                                    >
                                    <div class="form-help">The complete URL to your WHMCS API endpoint</div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="form-group">
                                        <label for="api_identifier" class="form-label">API Identifier *</label>
                                        <input 
                                            type="text" 
                                            id="api_identifier" 
                                            name="api_identifier" 
                                            class="form-input"
                                            required
                                            value="<?= htmlspecialchars($currentSettings['api_identifier'] ?? '') ?>"
                                            placeholder="Your API Identifier"
                                        >
                                        <div class="form-help">From WHMCS Admin → System Settings → API Credentials</div>
                                    </div>

                                    <div class="form-group">
                                        <label for="api_secret" class="form-label">API Secret *</label>
                                        <input 
                                            type="password" 
                                            id="api_secret" 
                                            name="api_secret" 
                                            class="form-input"
                                            required
                                            value="<?= htmlspecialchars($currentSettings['api_secret'] ?? '') ?>"
                                            placeholder="Your API Secret"
                                        >
                                        <div class="form-help">The secret key associated with your API identifier</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Default Nameservers Section -->
                        <div class="card-section">
                            <h3 class="section-title">
                                <span class="icon">🌐</span>
                                Default Nameservers
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label for="default_ns1" class="form-label">Primary Nameserver *</label>
                                    <input 
                                        type="text" 
                                        id="default_ns1" 
                                        name="default_ns1" 
                                        class="form-input"
                                        required
                                        value="<?= htmlspecialchars($currentSettings['default_ns1'] ?? '') ?>"
                                        placeholder="ns1.yourdomain.com"
                                    >
                                    <div class="form-help">The primary nameserver for domain updates</div>
                                </div>

                                <div class="form-group">
                                    <label for="default_ns2" class="form-label">Secondary Nameserver *</label>
                                    <input 
                                        type="text" 
                                        id="default_ns2" 
                                        name="default_ns2" 
                                        class="form-input"
                                        required
                                        value="<?= htmlspecialchars($currentSettings['default_ns2'] ?? '') ?>"
                                        placeholder="ns2.yourdomain.com"
                                    >
                                    <div class="form-help">The secondary nameserver for domain updates</div>
                                </div>
                            </div>
                        </div>

                        <!-- Customization Section -->
                        <div class="card-section">
                            <h3 class="section-title">
                                <span class="icon">🎨</span>
                                Customization
                            </h3>
                            
                            <div class="form-group">
                                <label for="logo_url" class="form-label">Custom Logo URL</label>
                                <input 
                                    type="url" 
                                    id="logo_url" 
                                    name="logo_url" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($currentSettings['logo_url'] ?? '') ?>"
                                    placeholder="https://yourdomain.com/logo.png"
                                    oninput="updateLogoPreview()"
                                    onblur="updateLogoPreview()"
                                >
                                <div class="form-help">
                                    Optional: Enter a URL to your custom logo. This will replace the default logo on the login page and dashboard. 
                                    Recommended size: 200x60 pixels. Leave empty to use the default logo.
                                </div>
                                
                                <div id="logo_preview_container" class="mt-3 p-3 bg-gray-50 rounded-md" style="display: <?= !empty($currentSettings['logo_url']) ? 'block' : 'none' ?>;">
                                    <div class="text-sm font-medium text-gray-700 mb-2">Logo Preview:</div>
                                    <img id="logo_preview" 
                                         src="<?= htmlspecialchars($currentSettings['logo_url'] ?? '') ?>" 
                                         alt="Custom Logo" 
                                         class="max-h-12 max-w-full object-contain"
                                         onerror="showLogoError()"
                                         onload="hideLogoError()">
                                    <div id="logo_error" class="text-sm text-red-600" style="display: none;">⚠️ Logo not accessible</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button type="submit" name="save_settings" class="btn btn-primary flex-1">
                                <span>💾</span>
                                <span>Save Settings</span>
                            </button>
                            <?php if ($currentSettings): ?>
                                <button type="submit" name="test_settings" class="btn btn-secondary">
                                    <span>🧪</span>
                                    <span>Test Connection</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- User Profile Section -->
                    <div class="card-section">
                        <h3 class="section-title">
                            <span class="icon">👤</span>
                            User Profile
                        </h3>
                        
                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input 
                                        type="text" 
                                        id="first_name" 
                                        name="first_name" 
                                        class="form-input"
                                        value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>"
                                        placeholder="Enter your first name"
                                    >
                                    <div class="form-help">Your first name for display purposes</div>
                                </div>

                                <div class="form-group">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input 
                                        type="text" 
                                        id="last_name" 
                                        name="last_name" 
                                        class="form-input"
                                        value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>"
                                        placeholder="Enter your last name"
                                    >
                                    <div class="form-help">Your last name for display purposes</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="user_email" class="form-label">Email Address</label>
                                <input 
                                    type="email" 
                                    id="user_email" 
                                    name="user_email" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>"
                                    disabled
                                >
                                <div class="form-help">Your email address (cannot be changed)</div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-3">
                                <button type="submit" name="save_profile" class="btn btn-primary">
                                    <span>💾</span>
                                    <span>Save Profile</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Company Settings Section -->
                    <div class="card-section">
                        <h3 class="section-title">
                            <span class="icon">🏢</span>
                            Company Settings
                        </h3>
                        
                        <form method="POST" class="space-y-6">
                            <div class="form-group">
                                <label for="company_name" class="form-label">Company Name *</label>
                                <input 
                                    type="text" 
                                    id="company_name" 
                                    name="company_name" 
                                    class="form-input"
                                    required
                                    value="<?= htmlspecialchars($currentCompany['company_name'] ?? '') ?>"
                                    placeholder="Enter your company name"
                                >
                                <div class="form-help">The name of your company or organization</div>
                            </div>

                            <div class="form-group">
                                <label for="company_address" class="form-label">Company Address</label>
                                <textarea 
                                    id="company_address" 
                                    name="company_address" 
                                    class="form-textarea"
                                    rows="3"
                                    placeholder="Enter your company address"
                                ><?= htmlspecialchars($currentCompany['company_address'] ?? '') ?></textarea>
                                <div class="form-help">Your company's physical address</div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="form-group">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input 
                                        type="tel" 
                                        id="contact_number" 
                                        name="contact_number" 
                                        class="form-input"
                                        value="<?= htmlspecialchars($currentCompany['contact_number'] ?? '') ?>"
                                        placeholder="+1 (555) 123-4567"
                                    >
                                    <div class="form-help">Primary contact phone number</div>
                                </div>

                                <div class="form-group">
                                    <label for="contact_email" class="form-label">Contact Email</label>
                                    <input 
                                        type="email" 
                                        id="contact_email" 
                                        name="contact_email" 
                                        class="form-input"
                                        value="<?= htmlspecialchars($currentCompany['contact_email'] ?? '') ?>"
                                        placeholder="contact@yourcompany.com"
                                    >
                                    <div class="form-help">Primary contact email address</div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="company_logo_url" class="form-label">Company Logo URL</label>
                                <input 
                                    type="url" 
                                    id="company_logo_url" 
                                    name="company_logo_url" 
                                    class="form-input"
                                    value="<?= htmlspecialchars($currentCompany['logo_url'] ?? '') ?>"
                                    placeholder="https://yourcompany.com/logo.png"
                                    oninput="updateCompanyLogoPreview()"
                                    onblur="updateCompanyLogoPreview()"
                                >
                                <div class="form-help">
                                    Optional: Enter a URL to your company logo. This will be used throughout the application.
                                    Recommended size: 200x60 pixels. Leave empty to use the default logo.
                                </div>
                                
                                <div id="company_logo_preview_container" class="mt-3 p-3 bg-gray-50 rounded-md" style="display: <?= !empty($currentCompany['logo_url']) ? 'block' : 'none' ?>;">
                                    <div class="text-sm font-medium text-gray-700 mb-2">Company Logo Preview:</div>
                                    <img id="company_logo_preview" 
                                         src="<?= htmlspecialchars($currentCompany['logo_url'] ?? '') ?>" 
                                         alt="Company Logo" 
                                         class="max-h-12 max-w-full object-contain"
                                         onerror="showCompanyLogoError()"
                                         onload="hideCompanyLogoError()">
                                    <div id="company_logo_error" class="text-sm text-red-600" style="display: none;">⚠️ Logo not accessible</div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-3">
                                <button type="submit" name="save_company" class="btn btn-primary">
                                    <span>💾</span>
                                    <span>Save Company Settings</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Settings Status -->
                    <?php if ($currentSettings): ?>
                        <div class="card-section bg-success-50 border-success-200">
                            <div class="flex items-center gap-4">
                                <span style="font-size: 2rem;">✅</span>
                                <div>
                                    <h4 class="font-semibold text-success-800 mb-1">Settings Configured & Persistent</h4>
                                    <div class="text-sm text-success-700">
                                        Your API settings are saved and will persist across login sessions.
                                    </div>
                                    <div class="text-sm text-success-700 mt-1">
                                        Last updated: <?= htmlspecialchars($currentSettings['updated_at'] ?? 'Unknown') ?>
                                    </div>
                                    <div class="text-sm text-success-700 mt-1">
                                        API URL: <?= htmlspecialchars(parse_url($currentSettings['api_url'], PHP_URL_HOST) ?? 'N/A') ?>
                                    </div>
                                    <div class="text-sm text-success-700 mt-1">
                                        User: <?= htmlspecialchars($_SESSION['user_email'] ?? 'Unknown') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-section bg-warning-50 border-warning-200">
                            <div class="flex items-center gap-4">
                                <span style="font-size: 2rem;">⚠️</span>
                                <div>
                                    <h4 class="font-semibold text-warning-800 mb-1">No Settings Configured</h4>
                                    <div class="text-sm text-warning-700">
                                        Configure your API settings once and they'll be saved for future logins.
                                    </div>
                                    <div class="text-sm text-warning-700 mt-1">
                                        User: <?= htmlspecialchars($_SESSION['user_email'] ?? 'Unknown') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Help Section -->
                    <div class="card-section">
                        <h3 class="section-title">
                            <span class="icon">❓</span>
                            Need Help?
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white p-4 rounded-lg border border-gray-200">
                                <h4 class="font-semibold text-gray-900 mb-2">🔑 Finding API Credentials</h4>
                                <p class="text-sm text-gray-600 mb-3">API credentials can be found in your WHMCS admin area under System Settings → API Credentials.</p>
                                <a href="https://docs.whmcs.com/API_Authentication" target="_blank" class="text-primary-600 text-sm font-medium">View Documentation →</a>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-gray-200">
                                <h4 class="font-semibold text-gray-900 mb-2">🌐 Nameserver Setup</h4>
                                <p class="text-sm text-gray-600 mb-3">Configure your default nameservers that will be used when updating domain DNS settings.</p>
                                <a href="https://docs.whmcs.com/Domains" target="_blank" class="text-primary-600 text-sm font-medium">Domain Documentation →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .space-y-6 > * + * {
            margin-top: 1.5rem;
        }
        .flex-1 {
            flex: 1 1 0%;
        }
        .grid {
            display: grid;
        }
        .grid-cols-1 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        .grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .gap-3 {
            gap: 0.75rem;
        }
        .gap-4 {
            gap: 1rem;
        }
        .gap-6 {
            gap: 1.5rem;
        }
        @media (min-width: 640px) {
            .sm\\:flex-row {
                flex-direction: row;
            }
        }
        @media (min-width: 768px) {
            .md\\:grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .bg-success-50 {
            background-color: var(--success-50);
        }
        .border-success-200 {
            border-color: var(--success-200);
        }
        .text-success-700 {
            color: var(--success-700);
        }
        .text-success-800 {
            color: var(--success-800);
        }
        .bg-warning-50 {
            background-color: var(--warning-50);
        }
        .border-warning-200 {
            border-color: var(--warning-200);
        }
        .text-warning-700 {
            color: var(--warning-600);
        }
        .text-warning-800 {
            color: var(--warning-600);
        }
        .bg-white {
            background-color: white;
        }
        .border-gray-200 {
            border-color: var(--gray-200);
        }
        .text-gray-900 {
            color: var(--gray-900);
        }
        .text-gray-600 {
            color: var(--gray-600);
        }
        .text-primary-600 {
            color: var(--primary-600);
        }
    </style>
    
    <script>
        // Logo preview functionality
        function updateLogoPreview() {
            const logoUrlInput = document.getElementById('logo_url');
            const logoPreviewContainer = document.getElementById('logo_preview_container');
            const logoPreview = document.getElementById('logo_preview');
            const logoError = document.getElementById('logo_error');
            
            const logoUrl = logoUrlInput.value.trim();
            
            if (logoUrl) {
                // Show the preview container
                logoPreviewContainer.style.display = 'block';
                
                // Update the image source
                logoPreview.src = logoUrl;
                
                // Hide any previous error
                logoError.style.display = 'none';
                logoPreview.style.display = 'block';
            } else {
                // Hide the preview container if no URL
                logoPreviewContainer.style.display = 'none';
            }
        }
        
        function showLogoError() {
            const logoPreview = document.getElementById('logo_preview');
            const logoError = document.getElementById('logo_error');
            
            logoPreview.style.display = 'none';
            logoError.style.display = 'block';
        }
        
        function hideLogoError() {
            const logoPreview = document.getElementById('logo_preview');
            const logoError = document.getElementById('logo_error');
            
            logoPreview.style.display = 'block';
            logoError.style.display = 'none';
        }
        
        // Initialize logo preview on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateLogoPreview();
            updateCompanyLogoPreview();
        });
        
        // Company logo preview functionality
        function updateCompanyLogoPreview() {
            const logoUrlInput = document.getElementById('company_logo_url');
            const logoPreviewContainer = document.getElementById('company_logo_preview_container');
            const logoPreview = document.getElementById('company_logo_preview');
            const logoError = document.getElementById('company_logo_error');
            
            const logoUrl = logoUrlInput.value.trim();
            
            if (logoUrl) {
                // Show the preview container
                logoPreviewContainer.style.display = 'block';
                
                // Update the image source
                logoPreview.src = logoUrl;
                
                // Hide any previous error
                logoError.style.display = 'none';
                logoPreview.style.display = 'block';
            } else {
                // Hide the preview container if no URL
                logoPreviewContainer.style.display = 'none';
            }
        }
        
        function showCompanyLogoError() {
            const logoPreview = document.getElementById('company_logo_preview');
            const logoError = document.getElementById('company_logo_error');
            
            logoPreview.style.display = 'none';
            logoError.style.display = 'block';
        }
        
        function hideCompanyLogoError() {
            const logoPreview = document.getElementById('company_logo_preview');
            const logoError = document.getElementById('company_logo_error');
            
            logoPreview.style.display = 'block';
            logoError.style.display = 'none';
        }
    </script>
</body>
</html> 