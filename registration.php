<?php
require_once 'auth_v2.php';
require_once 'config.php';

// Check if this is the first user (no companies exist)
$db = Database::getInstance();
$firstUser = false;

if ($db->isConnected()) {
    try {
        $sql = "SELECT COUNT(*) as count FROM users";
        $stmt = $db->getConnection()->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        $firstUser = ($result['count'] ?? 0) == 0;
        error_log('Registration: Total users in database: ' . ($result['count'] ?? 0));
        error_log('Registration: Is first user: ' . ($firstUser ? 'true' : 'false'));
    } catch (Exception $e) {
        // If tables don't exist yet, this is the first user
        error_log('Registration: Error checking user count: ' . $e->getMessage());
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
            case 'email_register':
                $email = sanitizeInput($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $passwordConfirm = $_POST['password_confirm'] ?? '';
                $firstName = sanitizeInput($_POST['first_name'] ?? '');
                $lastName = sanitizeInput($_POST['last_name'] ?? '');
                
                // Validate inputs
                $errors = [];
                
                if (empty($email) || !validateEmail($email)) {
                    $errors[] = 'Valid email is required';
                }
                
                if (empty($password)) {
                    $errors[] = 'Password is required';
                } else {
                    $passwordErrors = validatePassword($password);
                    $errors = array_merge($errors, $passwordErrors);
                }
                
                if (empty($passwordConfirm)) {
                    $errors[] = 'Password confirmation is required';
                } elseif ($password !== $passwordConfirm) {
                    $errors[] = 'Passwords do not match';
                }
                
                if (empty($firstName)) {
                    $errors[] = 'First name is required';
                }
                
                if (empty($lastName)) {
                    $errors[] = 'Last name is required';
                }
                
                if (!empty($errors)) {
                    $error = implode(', ', $errors);
                } else {
                    // Check if user already exists
                    $db = Database::getInstance();
                    $existingUser = $db->getUserByEmail($email);
                    
                    if ($existingUser) {
                        $error = 'An account with this email already exists. Please sign in instead.';
                    } else {
                        // Create a new company for this user (if first user, they get server_admin role)
                        $db->getConnection()->beginTransaction();
                        
                        try {
                            // Create a default company
                            $companyId = $db->createCompany([
                                'company_name' => 'My Company', // Default name, can be updated later
                                'company_address' => '',
                                'contact_number' => '',
                                'contact_email' => $email
                            ]);
                            
                            if (!$companyId) {
                                throw new Exception('Failed to create company');
                            }
                            
                            // Create user
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $role = $firstUser ? 'server_admin' : 'normal_user';
                            
                            $userId = $db->createUser([
                                'company_id' => $companyId,
                                'email' => $email,
                                'password_hash' => $passwordHash,
                                'role' => $role,
                                'first_name' => $firstName,
                                'last_name' => $lastName
                            ]);
                            
                            if (!$userId) {
                                throw new Exception('Failed to create user');
                            }
                            
                            // Commit transaction
                            $db->getConnection()->commit();
                            
                            // Auto-login the user
                            $_SESSION['user_id'] = $userId;
                            $_SESSION['user_email'] = $email;
                            $_SESSION['company_id'] = $companyId;
                            $_SESSION['user_role'] = $role;
                            $_SESSION['logged_in'] = true;
                            
                            header('Location: main_page.php');
                            exit;
                            
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
                <p class="mt-2 text-sm text-gray-600">Create your account</p>
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
                    <h3 class="text-lg font-semibold text-gray-900">Create Account</h3>
                    <p class="text-sm text-gray-600 mt-1">Choose your preferred sign-up method</p>
                </div>
                
                <!-- Google Sign-In Button -->
                <div class="mb-6">
                    <button type="button" id="google-signin-btn" onclick="signInWithGoogle()" 
                            class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Sign up with Google
                    </button>
                </div>
                
                <!-- Divider -->
                <div class="relative mb-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Or continue with email</span>
                    </div>
                </div>
                
                <!-- Email Registration Form -->
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="email_register">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="first_name" class="block text-xs font-medium text-gray-700 mb-1">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                   placeholder="First name">
                        </div>
                        
                        <div>
                            <label for="last_name" class="block text-xs font-medium text-gray-700 mb-1">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                                   placeholder="Last name">
                        </div>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-xs font-medium text-gray-700 mb-1">Email Address *</label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                               placeholder="Enter your email">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Password *</label>
                        <input type="password" id="password" name="password" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                               placeholder="Create a password">
                        <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters with uppercase, lowercase, and number</p>
                    </div>
                    
                    <div>
                        <label for="password_confirm" class="block text-xs font-medium text-gray-700 mb-1">Confirm Password *</label>
                        <input type="password" id="password_confirm" name="password_confirm" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm"
                               placeholder="Confirm your password">
                        <p class="text-xs text-gray-500 mt-1" id="password-match-text">Please enter the same password again</p>
                    </div>
                    
                    <div>
                        <button type="submit" 
                                class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            Create Account
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

    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-auth-compat.js"></script>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Firebase configuration
        const firebaseConfig = {
            apiKey: "<?= FIREBASE_API_KEY ?>",
            authDomain: "<?= FIREBASE_AUTH_DOMAIN ?>",
            projectId: "<?= FIREBASE_PROJECT_ID ?>",
            storageBucket: "<?= FIREBASE_STORAGE_BUCKET ?>",
            messagingSenderId: "<?= FIREBASE_MESSAGING_SENDER_ID ?>",
            appId: "<?= FIREBASE_APP_ID ?>"
        };
        
        console.log('Firebase config loaded:', firebaseConfig);
        
        // Initialize Firebase
        try {
            firebase.initializeApp(firebaseConfig);
            console.log('Firebase initialized successfully');
        } catch (error) {
            console.error('Firebase initialization error:', error);
        }
        
        // Google Sign In Function
        function signInWithGoogle() {
            console.log('Starting Google sign-in for registration...');
            
            // Check if Firebase is available
            if (typeof firebase === 'undefined') {
                console.error('Firebase is not loaded');
                alert('Firebase is not loaded. Please refresh the page and try again.');
                return;
            }
            
            if (typeof firebase.auth === 'undefined') {
                console.error('Firebase Auth is not loaded');
                alert('Firebase Auth is not loaded. Please refresh the page and try again.');
                return;
            }
            
            const provider = new firebase.auth.GoogleAuthProvider();
            
            firebase.auth().signInWithPopup(provider)
                .then((result) => {
                    console.log('Google sign-in successful:', result);
                    
                    const user = result.user;
                    console.log('User email:', user.email);
                    console.log('User display name:', user.displayName);
                    
                    // Send the ID token to your server for registration
                    user.getIdToken().then((idToken) => {
                        console.log('Got ID token, sending to server for registration...');
                        
                        fetch('auth.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'google_register',
                                idToken: idToken,
                                email: user.email,
                                displayName: user.displayName
                            })
                        })
                        .then(response => {
                            console.log('Server response status:', response.status);
                            return response.json();
                        })
                        .then(data => {
                            console.log('Server response data:', data);
                            if (data.success) {
                                console.log('Registration successful, redirecting...');
                                window.location.href = 'main_page.php';
                            } else {
                                console.error('Server error:', data.error);
                                alert('Google registration failed: ' + (data.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            alert('Google registration failed. Please try again.');
                        });
                    }).catch(error => {
                        console.error('Get ID token error:', error);
                        alert('Failed to get authentication token.');
                    });
                })
                .catch((error) => {
                    console.error('Google sign-in error:', error);
                    console.error('Error code:', error.code);
                    console.error('Error message:', error.message);
                    
                    let errorMessage = 'Google sign-in failed: ';
                    switch(error.code) {
                        case 'auth/popup-closed-by-user':
                            errorMessage += 'Sign-in was cancelled.';
                            break;
                        case 'auth/popup-blocked':
                            errorMessage += 'Pop-up was blocked. Please allow pop-ups for this site.';
                            break;
                        case 'auth/unauthorized-domain':
                            errorMessage += 'This domain is not authorized for Google sign-in.';
                            break;
                        default:
                            errorMessage += error.message;
                    }
                    alert(errorMessage);
                });
        }
        
        // Password confirmation validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('password_confirm');
        const passwordMatchText = document.getElementById('password-match-text');
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    confirmPasswordInput.classList.remove('border-red-300', 'focus:ring-red-500');
                    confirmPasswordInput.classList.add('border-green-300', 'focus:ring-green-500');
                    passwordMatchText.textContent = '✓ Passwords match';
                    passwordMatchText.className = 'text-xs text-green-600 mt-1';
                } else {
                    confirmPasswordInput.classList.remove('border-green-300', 'focus:ring-green-500');
                    confirmPasswordInput.classList.add('border-red-300', 'focus:ring-red-500');
                    passwordMatchText.textContent = '✗ Passwords do not match';
                    passwordMatchText.className = 'text-xs text-red-600 mt-1';
                }
            } else {
                confirmPasswordInput.classList.remove('border-red-300', 'border-green-300', 'focus:ring-red-500', 'focus:ring-green-500');
                passwordMatchText.textContent = 'Please enter the same password again';
                passwordMatchText.className = 'text-xs text-gray-500 mt-1';
            }
        }
        
        // Add event listeners
        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        // Test Google sign-in button
        const googleBtn = document.getElementById('google-signin-btn');
        if (googleBtn) {
            googleBtn.addEventListener('click', function(e) {
                console.log('Google sign-in button clicked');
            });
        }
    </script>
</body>
</html> 