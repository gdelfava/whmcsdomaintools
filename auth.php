<?php
require_once 'config.php';
require_once 'database_v2.php';

// Start session with error handling
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log('Session start error: ' . $e->getMessage());
        // Continue without session for debugging
    }
}

function firebaseRequest($endpoint, $data) {
    global $firebaseApiKey;
    
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:' . $endpoint . '?key=' . $firebaseApiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Add timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Connection timeout
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Log Firebase connection issues
    if ($curl_error) {
        error_log('Firebase connection error: ' . $curl_error);
        return ['error' => 'Firebase connection failed: ' . $curl_error];
    }
    
    if ($httpCode !== 200) {
        error_log('Firebase HTTP error: ' . $httpCode);
        return ['error' => 'Firebase HTTP error: ' . $httpCode];
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Firebase JSON decode error: ' . json_last_error_msg());
        return ['error' => 'Firebase response decode error'];
    }
    
    return $decoded;
}

function registerUser($email, $password) {
    $data = [
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => true
    ];
    
    return firebaseRequest('signUp', $data);
}

function loginUser($email, $password) {
    $data = [
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => true
    ];
    
    return firebaseRequest('signInWithPassword', $data);
}

function verifyIdToken($idToken) {
    global $firebaseConfig;
    
    try {
        // Simple token verification - in production you should verify the JWT signature
        $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . $firebaseConfig['apiKey'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log('Firebase token verification error: ' . $curl_error);
            return false;
        }
        
        $result = json_decode($response, true);
        return isset($result['users']) && !empty($result['users']);
    } catch (Exception $e) {
        error_log('Firebase token verification exception: ' . $e->getMessage());
        return false;
    }
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
}

function requireAuth() {
    try {
        if (!isLoggedIn()) {
            // For AJAX requests, return JSON error instead of redirect
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required',
                    'redirect' => 'login.php'
                ]);
                exit;
            }
            
            header('Location: login.php');
            exit;
        }
    } catch (Exception $e) {
        error_log('Auth check error: ' . $e->getMessage());
        
        // For AJAX requests, return JSON error
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Authentication system error',
                'debug' => $e->getMessage()
            ]);
            exit;
        }
        
        // For regular requests, redirect to login
        header('Location: login.php');
        exit;
    }
}

function handleLogout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

function handleFirebaseTokenLogin() {
    $idToken = $_POST['id_token'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (!empty($idToken) && verifyIdToken($idToken)) {
        $_SESSION['firebase_token'] = $idToken;
        $_SESSION['user_email'] = $email;
        $_SESSION['logged_in'] = true;
        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
}

function handleRegistration() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($email) || empty($password)) {
        return 'Email and password are required.';
    } elseif ($password !== $confirmPassword) {
        return 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        return 'Password must be at least 6 characters long.';
    } else {
        $result = registerUser($email, $password);
        
        if (isset($result['idToken'])) {
            $_SESSION['firebase_token'] = $result['idToken'];
            $_SESSION['user_email'] = $result['email'];
            $_SESSION['logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $error = $result['error']['message'] ?? 'Registration failed.';
            // Make error messages more user-friendly
            if (strpos($error, 'EMAIL_EXISTS') !== false) {
                return 'An account with this email already exists.';
            } elseif (strpos($error, 'WEAK_PASSWORD') !== false) {
                return 'Password is too weak. Please choose a stronger password.';
            } elseif (strpos($error, 'INVALID_EMAIL') !== false) {
                return 'Please enter a valid email address.';
            }
            return $error;
        }
    }
}

function handleLogin() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        return 'Email and password are required.';
    } else {
        $result = loginUser($email, $password);
        
        if (isset($result['idToken'])) {
            $_SESSION['firebase_token'] = $result['idToken'];
            $_SESSION['user_email'] = $result['email'];
            $_SESSION['logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $error = $result['error']['message'] ?? 'Login failed.';
            // Make error messages more user-friendly
            if (strpos($error, 'INVALID_PASSWORD') !== false || strpos($error, 'EMAIL_NOT_FOUND') !== false) {
                return 'Invalid email or password.';
            } elseif (strpos($error, 'USER_DISABLED') !== false) {
                return 'This account has been disabled.';
            } elseif (strpos($error, 'TOO_MANY_ATTEMPTS_TRY_LATER') !== false) {
                return 'Too many failed attempts. Please try again later.';
            }
            return $error;
        }
    }
}

function validateSession() {
    if (isLoggedIn() && isset($_SESSION['firebase_token'])) {
        // Optionally verify the token is still valid
        if (!verifyIdToken($_SESSION['firebase_token'])) {
            // Token is invalid, logout user
            session_destroy();
            return 'Session expired. Please login again.';
        }
    }
    return null;
}

function handleGoogleSignIn() {
    error_log('Google sign-in request received');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        error_log('Google sign-in: Invalid JSON input');
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    $idToken = $input['idToken'] ?? '';
    $email = $input['email'] ?? '';
    $displayName = $input['displayName'] ?? '';
    
    error_log('Google sign-in: Email = ' . $email . ', Display name = ' . $displayName);
    
    if (empty($idToken) || empty($email)) {
        error_log('Google sign-in: Missing required data');
        echo json_encode(['success' => false, 'error' => 'Missing required data']);
        exit;
    }
    
    // Verify the ID token with Firebase
    error_log('Google sign-in: Verifying token with Firebase');
    if (verifyIdToken($idToken)) {
        error_log('Google sign-in: Token verified successfully');
        
        // Check if user exists in our database
        $db = Database::getInstance();
        $user = $db->getUserByEmail($email);
        
        if ($user) {
            error_log('Google sign-in: User found in database, creating session');
            // User exists, log them in
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['firebase_token'] = $idToken;
            $_SESSION['logged_in'] = true;
            
            echo json_encode(['success' => true]);
            exit;
        } else {
            error_log('Google sign-in: User not found in database - ' . $email);
            // User doesn't exist in our database
            echo json_encode(['success' => false, 'error' => 'No account found with this email. Please register first.']);
            exit;
        }
    } else {
        error_log('Google sign-in: Token verification failed');
        echo json_encode(['success' => false, 'error' => 'Invalid Google token']);
        exit;
    }
}

function handleGoogleRegister() {
    error_log('Google registration request received');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        error_log('Google registration: Invalid JSON input');
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    $idToken = $input['idToken'] ?? '';
    $email = $input['email'] ?? '';
    $displayName = $input['displayName'] ?? '';
    
    error_log('Google registration: Email = ' . $email . ', Display name = ' . $displayName);
    
    if (empty($idToken) || empty($email)) {
        error_log('Google registration: Missing required data');
        echo json_encode(['success' => false, 'error' => 'Missing required data']);
        exit;
    }
    
    // Verify the ID token with Firebase
    error_log('Google registration: Verifying token with Firebase');
    if (verifyIdToken($idToken)) {
        error_log('Google registration: Token verified successfully');
        
        // Check if user already exists
        $db = Database::getInstance();
        $existingUser = $db->getUserByEmail($email);
        
        if ($existingUser) {
            error_log('Google registration: User already exists - ' . $email);
            echo json_encode(['success' => false, 'error' => 'An account with this email already exists. Please sign in instead.']);
            exit;
        }
        
        // Check if this is the first user
        $firstUser = false;
        try {
            $sql = "SELECT COUNT(*) as count FROM users";
            $stmt = $db->getConnection()->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            $firstUser = ($result['count'] ?? 0) == 0;
            error_log('Google registration: Total users in database: ' . ($result['count'] ?? 0));
            error_log('Google registration: Is first user: ' . ($firstUser ? 'true' : 'false'));
        } catch (Exception $e) {
            error_log('Google registration: Error checking user count: ' . $e->getMessage());
            $firstUser = true;
        }
        
        // Create a new company and user
        $db->getConnection()->beginTransaction();
        
        try {
            // Parse display name into first and last name
            $nameParts = explode(' ', $displayName, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
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
            $role = $firstUser ? 'server_admin' : 'normal_user';
            
            error_log('Google registration: Creating user with role: ' . $role);
            error_log('Google registration: Company ID: ' . $companyId);
            error_log('Google registration: Email: ' . $email);
            error_log('Google registration: First name: ' . $firstName);
            error_log('Google registration: Last name: ' . $lastName);
            
            $userId = $db->createUser([
                'company_id' => $companyId,
                'email' => $email,
                'password_hash' => '', // No password for Google users
                'role' => $role,
                'first_name' => $firstName,
                'last_name' => $lastName
            ]);
            
            if (!$userId) {
                error_log('Google registration: createUser returned false');
                throw new Exception('Failed to create user');
            }
            
            error_log('Google registration: User created successfully with ID: ' . $userId);
            
            // Commit transaction
            $db->getConnection()->commit();
            
            error_log('Google registration: User created successfully - ' . $email);
            
            // Auto-login the user
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_email'] = $email;
            $_SESSION['company_id'] = $companyId;
            $_SESSION['user_role'] = $role;
            $_SESSION['firebase_token'] = $idToken;
            $_SESSION['logged_in'] = true;
            
            echo json_encode(['success' => true]);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction
            $db->getConnection()->rollBack();
            error_log('Google registration: Error creating user - ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()]);
            exit;
        }
    } else {
        error_log('Google registration: Token verification failed');
        echo json_encode(['success' => false, 'error' => 'Invalid Google token']);
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for JSON input first (Google sign-in/register)
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['action'])) {
        switch ($input['action']) {
            case 'google_signin':
                handleGoogleSignIn();
                break;
            case 'google_register':
                handleGoogleRegister();
                break;
        }
        exit;
    }
    
    // Check for regular POST data
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'google_signin':
                handleGoogleSignIn();
                break;
            case 'google_register':
                handleGoogleRegister();
                break;
        }
    }
}
?> 