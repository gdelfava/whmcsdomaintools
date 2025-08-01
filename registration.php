<?php
require_once 'auth_v2.php';

// Check if this is the first user (no companies exist)
$db = Database::getInstance();
$firstUser = false;

if ($db->isConnected()) {
    try {
        $sql = "SELECT COUNT(*) as count FROM companies";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        $firstUser = ($result['count'] ?? 0) == 0;
    } catch (Exception $e) {
        // If tables don't exist yet, this is the first user
        $firstUser = true;
    }
} else {
    // If database is not connected, this is the first user
    $firstUser = true;
}

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: main_page.php');
    exit;
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                $email = sanitizeInput($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                
                if (empty($email) || empty($password)) {
                    $error = 'Please enter both email and password';
                } else {
                    $db = Database::getInstance();
                    if (!$db->isConnected()) {
                        $error = 'Database connection not available. Please check your database configuration.';
                    } else {
                        $result = authenticateUser($email, $password);
                        if ($result['success']) {
                            header('Location: main_page.php');
                            exit;
                        } else {
                            $error = $result['error'];
                        }
                    }
                }
                break;
                
            case 'register':
                $companyName = sanitizeInput($_POST['company_name'] ?? '');
                $companyAddress = sanitizeInput($_POST['company_address'] ?? '');
                $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
                $contactEmail = sanitizeInput($_POST['contact_email'] ?? '');
                $adminEmail = sanitizeInput($_POST['admin_email'] ?? '');
                $adminPassword = $_POST['admin_password'] ?? '';
                $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';
                $adminFirstName = sanitizeInput($_POST['admin_first_name'] ?? '');
                $adminLastName = sanitizeInput($_POST['admin_last_name'] ?? '');
                
                // Validate inputs
                $errors = [];
                
                if (empty($companyName)) {
                    $errors[] = 'Company name is required';
                }
                
                if (empty($contactEmail) || !validateEmail($contactEmail)) {
                    $errors[] = 'Valid contact email is required';
                }
                
                if (empty($adminEmail) || !validateEmail($adminEmail)) {
                    $errors[] = 'Valid admin email is required';
                }
                
                if (empty($adminPassword)) {
                    $errors[] = 'Admin password is required';
                } else {
                    $passwordErrors = validatePassword($adminPassword);
                    $errors = array_merge($errors, $passwordErrors);
                }
                
                if (empty($adminPasswordConfirm)) {
                    $errors[] = 'Password confirmation is required';
                } elseif ($adminPassword !== $adminPasswordConfirm) {
                    $errors[] = 'Passwords do not match';
                }
                
                if (empty($adminFirstName)) {
                    $errors[] = 'Admin first name is required';
                }
                
                if (empty($adminLastName)) {
                    $errors[] = 'Admin last name is required';
                }
                
                if (!empty($errors)) {
                    $error = implode(', ', $errors);
                } else {
                    // Create company and admin user
                    $db = Database::getInstance();
                    
                    if (!$db->isConnected()) {
                        $error = 'Database connection not available. Please check your database configuration.';
                    } else {
                        // Start transaction
                        $db->getConnection()->beginTransaction();
                        
                        try {
                            // Create company
                            $companyId = $db->createCompany([
                                'company_name' => $companyName,
                                'company_address' => $companyAddress,
                                'contact_number' => $contactNumber,
                                'contact_email' => $contactEmail
                            ]);
                            
                            if (!$companyId) {
                                throw new Exception('Failed to create company');
                            }
                            
                            // Create admin user (automatically Server Admin)
                            $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                            $userId = $db->createUser([
                                'company_id' => $companyId,
                                'email' => $adminEmail,
                                'password_hash' => $passwordHash,
                                'role' => 'server_admin', // First user is always Server Admin
                                'first_name' => $adminFirstName,
                                'last_name' => $adminLastName
                            ]);
                            
                            if (!$userId) {
                                throw new Exception('Failed to create admin user');
                            }
                            
                            // Commit transaction
                            $db->getConnection()->commit();
                            
                            $success = 'Registration successful! You can now log in with your email and password.';
                            
                        } catch (Exception $e) {
                            // Rollback transaction
                            $db->getConnection()->rollBack();
                            $error = 'Registration failed: ' . $e->getMessage();
                        }
                    }
                }
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Registration</title>
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
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-12 w-12 bg-primary-600 rounded-lg flex items-center justify-center mb-4">
                    <i data-lucide="globe" class="w-6 h-6 text-white"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-900">Domain Tools</h2>
                <p class="mt-2 text-sm text-gray-600">Register your company account</p>
            </div>

            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                        <div class="text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                        <div class="text-sm text-green-800"><?= htmlspecialchars($success) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Database Connection Warning -->
            <?php if (!$db->isConnected()): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600"></i>
                        <div>
                            <div class="text-sm font-medium text-yellow-800">Database Connection Required</div>
                            <div class="text-xs text-yellow-700 mt-1">Please ensure your database is properly configured in the .env file</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <div class="bg-white py-8 px-6 shadow-lg rounded-lg">
                <div class="text-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Company Registration</h3>
                    <p class="text-sm text-gray-600 mt-1">Create your company and admin account</p>
                    <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="shield-check" class="w-4 h-4 text-blue-600"></i>
                            <span class="text-xs text-blue-800 font-medium">You will be assigned Server Admin role</span>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="register">
                    
                    <!-- Company Information -->
                    <div class="border-b border-gray-200 pb-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Company Information</h4>
                        
                        <div class="space-y-3">
                            <div>
                                <label for="company_name" class="block text-xs font-medium text-gray-700 mb-1">Company Name *</label>
                                <input type="text" id="company_name" name="company_name" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="Enter company name">
                            </div>
                            
                            <div>
                                <label for="company_address" class="block text-xs font-medium text-gray-700 mb-1">Company Address</label>
                                <textarea id="company_address" name="company_address" rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                          placeholder="Enter company address"></textarea>
                            </div>
                            
                            <div>
                                <label for="contact_number" class="block text-xs font-medium text-gray-700 mb-1">Contact Number</label>
                                <input type="tel" id="contact_number" name="contact_number"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="Enter contact number">
                            </div>
                            
                            <div>
                                <label for="contact_email" class="block text-xs font-medium text-gray-700 mb-1">Contact Email *</label>
                                <input type="email" id="contact_email" name="contact_email" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="Enter contact email">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Admin User Information -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Admin Account Information</h4>
                        
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="admin_first_name" class="block text-xs font-medium text-gray-700 mb-1">First Name *</label>
                                    <input type="text" id="admin_first_name" name="admin_first_name" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                           placeholder="First name">
                                </div>
                                
                                <div>
                                    <label for="admin_last_name" class="block text-xs font-medium text-gray-700 mb-1">Last Name *</label>
                                    <input type="text" id="admin_last_name" name="admin_last_name" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                           placeholder="Last name">
                                </div>
                            </div>
                            
                            <div>
                                <label for="admin_email" class="block text-xs font-medium text-gray-700 mb-1">Admin Email *</label>
                                <input type="email" id="admin_email" name="admin_email" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="Enter admin email">
                            </div>
                            
                            <div>
                                <label for="admin_password" class="block text-xs font-medium text-gray-700 mb-1">Admin Password *</label>
                                <input type="password" id="admin_password" name="admin_password" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="Enter admin password">
                                <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters with uppercase, lowercase, and number</p>
                            </div>
                            
                            <div>
                                <label for="admin_password_confirm" class="block text-xs font-medium text-gray-700 mb-1">Confirm Password *</label>
                                <input type="password" id="admin_password_confirm" name="admin_password_confirm" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                       placeholder="Confirm admin password">
                                <p class="text-xs text-gray-500 mt-1">Please enter the same password again</p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" 
                                class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            Register Company
                        </button>
                    </div>
                </form>
            </div>

            <!-- Login Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">Already have an account?</p>
                <a href="login.php" class="text-primary-600 hover:text-primary-700 font-medium text-sm">
                    Sign in here
                </a>
            </div>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Password confirmation validation
        const passwordInput = document.getElementById('admin_password');
        const confirmPasswordInput = document.getElementById('admin_password_confirm');
        const confirmPasswordLabel = confirmPasswordInput.nextElementSibling;
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    confirmPasswordInput.classList.remove('border-red-300', 'focus:ring-red-500');
                    confirmPasswordInput.classList.add('border-green-300', 'focus:ring-green-500');
                    if (confirmPasswordLabel) {
                        confirmPasswordLabel.textContent = '✓ Passwords match';
                        confirmPasswordLabel.className = 'text-xs text-green-600 mt-1';
                    }
                } else {
                    confirmPasswordInput.classList.remove('border-green-300', 'focus:ring-green-500');
                    confirmPasswordInput.classList.add('border-red-300', 'focus:ring-red-500');
                    if (confirmPasswordLabel) {
                        confirmPasswordLabel.textContent = '✗ Passwords do not match';
                        confirmPasswordLabel.className = 'text-xs text-red-600 mt-1';
                    }
                }
            } else {
                confirmPasswordInput.classList.remove('border-red-300', 'border-green-300', 'focus:ring-red-500', 'focus:ring-green-500');
                if (confirmPasswordLabel) {
                    confirmPasswordLabel.textContent = 'Please enter the same password again';
                    confirmPasswordLabel.className = 'text-xs text-gray-500 mt-1';
                }
            }
        }
        
        // Add event listeners
        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
    </script>
</body>
</html> 