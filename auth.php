<?php
require_once 'config.php';

session_start();

function firebaseRequest($endpoint, $data) {
    global $firebaseApiKey;
    
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:' . $endpoint . '?key=' . $firebaseApiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return json_decode($response, true);
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
    
    // Simple token verification - in production you should verify the JWT signature
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . $firebaseConfig['apiKey'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return isset($result['users']) && !empty($result['users']);
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: auth_page.php');
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
?> 