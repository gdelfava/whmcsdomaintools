<?php
require_once 'auth.php';
require_once 'user_settings.php';

$authError = '';
$authSuccess = '';
$showRegister = isset($_GET['register']);

// Handle logout
if (isset($_POST['logout'])) {
    handleLogout();
}

// Handle Firebase token login (for Google Sign-In)
if (isset($_POST['firebase_token_login'])) {
    handleFirebaseTokenLogin();
}

// Handle registration
if (isset($_POST['register'])) {
    $authError = handleRegistration();
}

// Handle login
if (isset($_POST['login'])) {
    $authError = handleLogin();
}

// Check if user is already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Validate session
$sessionError = validateSession();
if ($sessionError) {
    $authError = $sessionError;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $showRegister ? 'Create Account' : 'Login' ?> - WHMCS Domain Tools</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Firebase SDKs -->
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-auth-compat.js"></script>
    
    <!-- Google Sign-In SDK -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    
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
<body class="bg-gray-50 font-sans min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Firebase Configuration Warning -->
        <?php if ($firebaseConfig['apiKey'] === 'YOUR_FIREBASE_API_KEY'): ?>
            <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start space-x-3">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600 mt-0.5"></i>
                    <div>
                        <h3 class="font-semibold text-yellow-800 mb-1">Configuration Required</h3>
                        <p class="text-sm text-yellow-700">Please update the Firebase configuration in the PHP file with your actual Firebase project details.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Auth Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-xl overflow-hidden">
            <!-- Top accent bar -->
            <div class="h-1 bg-gradient-to-r from-primary-500 to-primary-600"></div>
            
            <div class="p-8">
                <!-- App Header -->
                <div class="text-center mb-8">
                    <div class="flex items-center justify-center space-x-3 mb-4">
                        <div class="w-10 h-10 bg-primary-600 rounded-lg flex items-center justify-center">
                            <i data-lucide="globe" class="w-6 h-6 text-white"></i>
                        </div>
                        <div class="text-left">
                            <h1 class="text-xl font-bold text-gray-900">Domain Tools</h1>
                            <p class="text-xs text-gray-500">Management Suite</p>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        <?= $showRegister ? 'Create Account' : 'Welcome Back' ?>
                    </h2>
                    <p class="text-gray-600">
                        <?= $showRegister ? 'Sign up to manage your domains' : 'Sign in to your account' ?>
                    </p>
                </div>
                
                <!-- Error/Success Messages -->
                <?php if ($authError): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="x-circle" class="w-5 h-5 text-red-600"></i>
                            <span class="text-sm text-red-800"><?= htmlspecialchars($authError) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($authSuccess): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                            <span class="text-sm text-green-800"><?= htmlspecialchars($authSuccess) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Auth Form -->
                <form method="POST" class="space-y-6" id="authForm">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                            </div>
                            <input 
                                type="email" 
                                name="email" 
                                id="email" 
                                class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                                required 
                                autocomplete="email" 
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                placeholder="Enter your email"
                            >
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                            </div>
                            <input 
                                type="password" 
                                name="password" 
                                id="password" 
                                class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                                required 
                                autocomplete="<?= $showRegister ? 'new-password' : 'current-password' ?>" 
                                minlength="6" 
                                placeholder="Enter your password"
                            >
                        </div>
                    </div>
                    
                    <?php if ($showRegister): ?>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <input 
                                    type="password" 
                                    name="confirm_password" 
                                    id="confirm_password" 
                                    class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                                    required 
                                    autocomplete="new-password" 
                                    minlength="6" 
                                    placeholder="Confirm your password"
                                >
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button 
                        type="submit" 
                        name="<?= $showRegister ? 'register' : 'login' ?>" 
                        class="w-full bg-primary-600 hover:bg-primary-700 text-white py-2 px-4 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        <i data-lucide="<?= $showRegister ? 'user-plus' : 'log-in' ?>" class="w-4 h-4"></i>
                        <span><?= $showRegister ? 'Create Account' : 'Sign In' ?></span>
                    </button>
                </form>
                
                <!-- Loading Spinner -->
                <div id="loading-spinner" class="hidden mt-4 flex justify-center">
                    <div class="w-6 h-6 border-2 border-gray-200 border-t-primary-600 rounded-full animate-spin"></div>
                </div>
                
                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500 font-medium">OR CONTINUE WITH</span>
                    </div>
                </div>
                
                <!-- Google Sign-In Button -->
                <button 
                    class="w-full bg-white hover:bg-gray-50 text-gray-700 py-2 px-4 rounded-lg font-medium border border-gray-300 transition-colors flex items-center justify-center space-x-3 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                    id="googleSignInBtn"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <span>Continue with Google</span>
                </button>
                
                <!-- Auth Switch -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        <?php if ($showRegister): ?>
                            Already have an account? 
                            <a href="auth_page.php" class="font-medium text-primary-600 hover:text-primary-700 hover:underline">
                                Login here
                            </a>
                        <?php else: ?>
                            Don't have an account? 
                            <a href="auth_page.php?register=1" class="font-medium text-primary-600 hover:text-primary-700 hover:underline">
                                Sign up
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="js/auth.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Initialize Firebase with configuration from PHP
        const firebaseConfig = {
            apiKey: "<?= $firebaseConfig['apiKey'] ?>",
            authDomain: "<?= $firebaseConfig['authDomain'] ?>",
            projectId: "<?= $firebaseConfig['projectId'] ?>",
            storageBucket: "<?= $firebaseConfig['storageBucket'] ?>",
            messagingSenderId: "<?= $firebaseConfig['messagingSenderId'] ?>",
            appId: "<?= $firebaseConfig['appId'] ?>"
        };
        
        initializeFirebase(firebaseConfig);
    </script>
</body>
</html> 