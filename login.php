<?php
require_once 'auth_v2.php';
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: main_page.php');
    exit;
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WHMCS Domain Tools - Login</title>
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
                <p class="mt-2 text-sm text-gray-600">Sign in to your account</p>
            </div>

            <!-- Error Messages -->
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-600"></i>
                        <div class="text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Database Connection Warning -->
            <?php 
            $db = Database::getInstance();
            if (!$db->isConnected()): 
            ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600"></i>
                        <div>
                            <div class="text-sm font-medium text-yellow-800">Database Connection Required</div>
                            <div class="text-xs text-yellow-700 mt-1">Please ensure your database is properly configured in the .env file</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div class="bg-white py-8 px-6 shadow-lg rounded-lg">
                <form method="POST" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="email" name="email" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="Enter your email">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" id="password" name="password" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="Enter your password">
                    </div>
                    
                    <div>
                        <button type="submit" 
                                class="w-full bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            Sign In
                        </button>
                    </div>
                </form>
                
                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">Or continue with</span>
                    </div>
                </div>
                
                <!-- Google Sign In Button -->
                <div class="mt-6">
                    <button type="button" 
                            onclick="signInWithGoogle()"
                            class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Sign in with Google
                    </button>
                </div>
            </div>

            <!-- Registration Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">Don't have an account?</p>
                                        <a href="registration.php" class="text-primary-600 hover:text-primary-700 font-medium text-sm">
                    Register your company
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
            apiKey: "<?= $firebaseConfig['apiKey'] ?>",
            authDomain: "<?= $firebaseConfig['authDomain'] ?>",
            projectId: "<?= $firebaseConfig['projectId'] ?>",
            storageBucket: "<?= $firebaseConfig['storageBucket'] ?>",
            messagingSenderId: "<?= $firebaseConfig['messagingSenderId'] ?>",
            appId: "<?= $firebaseConfig['appId'] ?>"
        };
        
        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);
        
        // Google Sign In Function
        function signInWithGoogle() {
            console.log('Starting Google sign-in...');
            
            const provider = new firebase.auth.GoogleAuthProvider();
            
            firebase.auth().signInWithPopup(provider)
                .then((result) => {
                    console.log('Google sign-in successful:', result);
                    
                    // This gives you a Google Access Token
                    const credential = result.credential;
                    const token = credential.accessToken;
                    const user = result.user;
                    
                    console.log('User email:', user.email);
                    console.log('User display name:', user.displayName);
                    
                    // Send the ID token to your server
                    user.getIdToken().then((idToken) => {
                        console.log('Got ID token, sending to server...');
                        
                        // Send token to server for verification
                        fetch('auth.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'google_signin',
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
                                console.log('Sign-in successful, redirecting...');
                                window.location.href = 'main_page.php';
                            } else {
                                console.error('Server error:', data.error);
                                alert('Google sign-in failed: ' + (data.error || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            alert('Google sign-in failed. Please try again.');
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
    </script>
</body>
</html> 