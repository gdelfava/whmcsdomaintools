<?php
require_once 'config.php';
require_once 'database_v2.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: registration.php');
        exit;
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_email']) && 
           isset($_SESSION['company_id']) && 
           isset($_SESSION['user_role']);
}

/**
 * Check if user has specific permission
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $db = Database::getInstance();
    return $db->hasPermission($_SESSION['user_role'], $permission);
}

/**
 * Require specific permission - redirect if not authorized
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied. Insufficient permissions.']);
        exit;
    }
}

/**
 * Check if user is server admin
 */
function isServerAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'server_admin';
}

/**
 * Require server admin role
 */
function requireServerAdmin() {
    if (!isServerAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied. Server admin privileges required.']);
        exit;
    }
}

/**
 * Get current user's company ID
 */
function getCurrentCompanyId() {
    return $_SESSION['company_id'] ?? null;
}

/**
 * Get current user's role
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user's role display name
 */
function getCurrentUserRoleDisplay() {
    $role = $_SESSION['user_role'] ?? null;
    
    if (!$role) {
        return 'User';
    }
    
    // Convert role to display format
    switch ($role) {
        case 'server_admin':
            return 'Server Admin';
        case 'normal_user':
            return 'Normal User';
        default:
            // Convert snake_case to Title Case
            return ucwords(str_replace('_', ' ', $role));
    }
}

/**
 * Get current user's email
 */
function getCurrentUserEmail() {
    return $_SESSION['user_email'] ?? null;
}

/**
 * Get current user's ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Authenticate user with email and password
 */
function authenticateUser($email, $password) {
    $db = Database::getInstance();
    $user = $db->getUserByEmail($email);
    
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid email or password'];
    }
    
    // Check if this is a Google OAuth user
    if (strpos($user['password_hash'], 'GOOGLE_OAUTH_USER_') === 0) {
        return ['success' => false, 'error' => 'This account was created with Google Sign-In. Please use Google Sign-In to log in.'];
    }
    
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email or password'];
    }
    
    if (!$user['is_active']) {
        return ['success' => false, 'error' => 'Account is deactivated'];
    }
    
    // Update last login
    $db->updateUserLastLogin($user['id']);
    
    // Set session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['company_id'] = $user['company_id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['company_name'] = $user['company_name'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['logged_in'] = true;
    
    return ['success' => true, 'user' => $user];
}

/**
 * Logout user
 */
function logoutUser() {
    // Clear session data
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Redirect to login page
    header('Location: login.php');
    exit;
}

/**
 * Create new user account
 */
function createUserAccount($userData) {
    $db = Database::getInstance();
    
    // Check if email already exists
    $existingUser = $db->getUserByEmail($userData['email']);
    if ($existingUser) {
        return ['success' => false, 'error' => 'Email already registered'];
    }
    
    // Hash password
    $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    // Create user
    $userId = $db->createUser([
        'company_id' => $userData['company_id'],
        'email' => $userData['email'],
        'password_hash' => $passwordHash,
        'role' => $userData['role'] ?? 'normal_user',
        'first_name' => $userData['first_name'] ?? '',
        'last_name' => $userData['last_name'] ?? ''
    ]);
    
    if (!$userId) {
        return ['success' => false, 'error' => 'Failed to create user account'];
    }
    
    return ['success' => true, 'user_id' => $userId];
}

/**
 * Create new company
 */
function createCompany($companyData) {
    $db = Database::getInstance();
    
    $companyId = $db->createCompany($companyData);
    
    if (!$companyId) {
        return ['success' => false, 'error' => 'Failed to create company'];
    }
    
    return ['success' => true, 'company_id' => $companyId];
}

/**
 * Get user's full name
 */
function getUserFullName() {
    $firstName = $_SESSION['first_name'] ?? '';
    $lastName = $_SESSION['last_name'] ?? '';
    
    if ($firstName && $lastName) {
        return "$firstName $lastName";
    } elseif ($firstName) {
        return $firstName;
    } elseif ($lastName) {
        return $lastName;
    } else {
        return $_SESSION['user_email'] ?? 'User';
    }
}

/**
 * Get company name
 */
function getCompanyName() {
    return $_SESSION['company_name'] ?? 'Unknown Company';
}

/**
 * Check if user can access server setup features
 */
function canAccessServerSetup() {
    return hasPermission('database_setup') || hasPermission('create_tables');
}

/**
 * Check if user can manage users
 */
function canManageUsers() {
    return hasPermission('manage_users');
}

/**
 * Check if user can manage company profile
 */
function canManageCompany() {
    return hasPermission('manage_company');
}

/**
 * Get user's permissions as array
 */
function getUserPermissions() {
    if (!isLoggedIn()) {
        return [];
    }
    
    $db = Database::getInstance();
    $permissions = [];
    
    // Get all permissions for the user's role
    $sql = "SELECT permission_name FROM permissions WHERE role = :role";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute(['role' => $_SESSION['user_role']]);
    
    while ($row = $stmt->fetch()) {
        $permissions[] = $row['permission_name'];
    }
    
    return $permissions;
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    return $errors;
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitize user input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if user session is expired
 */
function isSessionExpired() {
    $maxSessionTime = 24 * 60 * 60; // 24 hours
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    
    if (time() - $lastActivity > $maxSessionTime) {
        return true;
    }
    
    $_SESSION['last_activity'] = time();
    return false;
}

/**
 * Extend user session
 */
function extendSession() {
    $_SESSION['last_activity'] = time();
}

// Auto-extend session on each request
if (isLoggedIn()) {
    extendSession();
}
?> 