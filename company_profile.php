<?php
require_once 'auth_v2.php';

// Require authentication
requireAuth();

// Require permission to manage company
requirePermission('manage_company');

$db = Database::getInstance();
$companyId = getCurrentCompanyId();
$error = '';
$success = '';

// Get current company data
$company = $db->getCompany($companyId);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = sanitizeInput($_POST['company_name'] ?? '');
    $companyAddress = sanitizeInput($_POST['company_address'] ?? '');
    $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
    $contactEmail = sanitizeInput($_POST['contact_email'] ?? '');
    $logoUrl = sanitizeInput($_POST['logo_url'] ?? '');
    
    // Validate inputs
    $errors = [];
    
    if (empty($companyName)) {
        $errors[] = 'Company name is required';
    }
    
    if (empty($contactEmail) || !validateEmail($contactEmail)) {
        $errors[] = 'Valid contact email is required';
    }
    
    if (!empty($errors)) {
        $error = implode(', ', $errors);
    } else {
        // Update company
        $result = $db->updateCompany($companyId, [
            'company_name' => $companyName,
            'company_address' => $companyAddress,
            'contact_number' => $contactNumber,
            'contact_email' => $contactEmail,
            'logo_url' => $logoUrl
        ]);
        
        if ($result) {
            $success = 'Company profile updated successfully!';
            // Refresh company data
            $company = $db->getCompany($companyId);
        } else {
            $error = 'Failed to update company profile';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile - WHMCS Domain Tools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Sora', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center space-x-4">
                        <a href="main_page.php" class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                                <i data-lucide="globe" class="w-5 h-5 text-white"></i>
                            </div>
                            <div>
                                <h1 class="text-lg font-bold text-gray-900">Domain Tools</h1>
                                <p class="text-xs text-gray-500">Management Suite</p>
                            </div>
                        </a>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars(getUserFullName()) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars(getCompanyName()) ?></div>
                        </div>
                        <a href="logout.php" class="text-gray-500 hover:text-gray-700">
                            <i data-lucide="log-out" class="w-5 h-5"></i>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Company Profile</h1>
                        <p class="text-gray-600 mt-1">Manage your company information and branding</p>
                    </div>
                    <a href="main_page.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                        <div class="text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        <div class="text-sm text-green-800"><?= htmlspecialchars($success) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Company Profile Form -->
            <div class="bg-white shadow-lg rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Company Information</h2>
                    <p class="text-sm text-gray-600 mt-1">Update your company details and contact information</p>
                </div>
                
                <form method="POST" class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Company Name -->
                        <div class="md:col-span-2">
                            <label for="company_name" class="block text-sm font-medium text-gray-700 mb-2">Company Name *</label>
                            <input type="text" id="company_name" name="company_name" required
                                   value="<?= htmlspecialchars($company['company_name'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="Enter company name">
                        </div>
                        
                        <!-- Contact Email -->
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">Contact Email *</label>
                            <input type="email" id="contact_email" name="contact_email" required
                                   value="<?= htmlspecialchars($company['contact_email'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="Enter contact email">
                        </div>
                        
                        <!-- Contact Number -->
                        <div>
                            <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number"
                                   value="<?= htmlspecialchars($company['contact_number'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="Enter contact number">
                        </div>
                        
                        <!-- Company Address -->
                        <div class="md:col-span-2">
                            <label for="company_address" class="block text-sm font-medium text-gray-700 mb-2">Company Address</label>
                            <textarea id="company_address" name="company_address" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                      placeholder="Enter company address"><?= htmlspecialchars($company['company_address'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Logo URL -->
                        <div class="md:col-span-2">
                            <label for="logo_url" class="block text-sm font-medium text-gray-700 mb-2">Logo URL</label>
                            <input type="url" id="logo_url" name="logo_url"
                                   value="<?= htmlspecialchars($company['logo_url'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="Enter logo URL (optional)">
                            <p class="text-xs text-gray-500 mt-1">Enter a URL to your company logo for branding</p>
                        </div>
                    </div>
                    
                    <!-- Company Details Display -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Company Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">Company ID:</span>
                                <span class="text-gray-600 ml-2"><?= htmlspecialchars($company['id'] ?? 'N/A') ?></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Created:</span>
                                <span class="text-gray-600 ml-2"><?= htmlspecialchars($company['created_at'] ?? 'N/A') ?></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Last Updated:</span>
                                <span class="text-gray-600 ml-2"><?= htmlspecialchars($company['updated_at'] ?? 'N/A') ?></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Current User Role:</span>
                                <span class="text-gray-600 ml-2"><?= htmlspecialchars(getCurrentUserRoleDisplay()) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-4">
                        <a href="main_page.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors">
                            Cancel
                        </a>
                        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                            Update Company Profile
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Additional Actions -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- User Management -->
                <?php if (canManageUsers()): ?>
                <div class="bg-white shadow-lg rounded-lg p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                        <h3 class="text-lg font-semibold text-gray-900">User Management</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Manage users within your company</p>
                    <a href="user_management.php" class="inline-flex items-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        <span>Manage Users</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- API Settings -->
                <div class="bg-white shadow-lg rounded-lg p-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <i data-lucide="key" class="w-6 h-6 text-green-600"></i>
                        <h3 class="text-lg font-semibold text-gray-900">API Settings</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Configure your WHMCS API credentials</p>
                    <a href="settings.php" class="inline-flex items-center space-x-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        <span>Configure API</span>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html> 