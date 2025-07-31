<?php
// require_once 'auth.php';
// require_once 'database.php';
// If needed, use:
// require_once 'auth_v2.php';
// require_once 'database_v2.php';

// Require authentication
requireAuth();

// Set JSON header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Validate required fields
    $requiredFields = ['domain_name', 'registrar', 'status', 'expiry_date'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields: ' . implode(', ', $missingFields)]);
        exit;
    }
    
    // Sanitize and validate input
    $domainName = trim($_POST['domain_name']);
    $registrar = trim($_POST['registrar']);
    $status = trim($_POST['status']);
    $registrationDate = isset($_POST['registration_date']) ? $_POST['registration_date'] : null;
    $expiryDate = $_POST['expiry_date'];
    $nextDueDate = isset($_POST['next_due_date']) ? $_POST['next_due_date'] : null;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.00;
    $currency = isset($_POST['currency']) ? trim($_POST['currency']) : 'ZAR';
    $nameservers = isset($_POST['nameservers']) ? trim($_POST['nameservers']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
    
    // Validate domain name format
    if (!filter_var($domainName, FILTER_VALIDATE_DOMAIN) && !preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domainName)) {
        echo json_encode(['success' => false, 'error' => 'Invalid domain name format']);
        exit;
    }
    
    // Validate status
    $validStatuses = ['Active', 'Expired', 'Pending', 'Cancelled', 'Grace', 'Redemption', 'Transferred Away'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }
    

    
    // Get current user email
    $userEmail = $_SESSION['user_email'] ?? '';
    if (empty($userEmail)) {
        echo json_encode(['success' => false, 'error' => 'User not authenticated']);
        exit;
    }
    
    // Check if domain already exists for this user
    $pdo = $db->getConnection();
    $checkStmt = $pdo->prepare("SELECT id FROM domains WHERE user_email = :user_email AND domain_name = :domain_name LIMIT 1");
    $checkStmt->execute(['user_email' => $userEmail, 'domain_name' => $domainName]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Domain already exists in database']);
        exit;
    }
    
    // Generate a unique domain ID (similar to WHMCS format)
    $domainId = time() . rand(100, 999);
    
    // Insert domain into database using the multi-user method
    $domainData = [
        'domain_id' => $domainId,
        'domain_name' => $domainName,
        'registrar' => $registrar,
        'status' => $status,
        'registration_date' => $registrationDate,
        'expiry_date' => $expiryDate,
        'next_due_date' => $nextDueDate,
        'amount' => $amount,
        'currency' => $currency,
        'notes' => $notes,
        'batch_number' => 1
    ];
    
    $result = $db->insertDomain($userEmail, $domainData);
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Failed to insert domain into database']);
        exit;
    }
    
    $domainDbId = $pdo->lastInsertId();
    
    // Insert nameservers if provided
    if (!empty($nameservers)) {
        $nameserverArray = array_map('trim', explode(',', $nameservers));
        $nameserverArray = array_filter($nameserverArray); // Remove empty values
        
        if (!empty($nameserverArray)) {
            // Prepare nameserver data for the existing schema (ns1, ns2, ns3, ns4, ns5)
            $nsData = [
                'ns1' => $nameserverArray[0] ?? null,
                'ns2' => $nameserverArray[1] ?? null,
                'ns3' => $nameserverArray[2] ?? null,
                'ns4' => $nameserverArray[3] ?? null,
                'ns5' => $nameserverArray[4] ?? null
            ];
            
            // Use the multi-user method to insert nameservers
            $db->insertNameservers($userEmail, $domainId, $nsData);
        }
    }
    
    // Log the manual addition
    $logSql = "INSERT INTO sync_logs (user_email, batch_number, domains_found, domains_processed, domains_added, domains_updated, errors, status, sync_started, sync_completed) VALUES (:user_email, 0, 1, 1, 1, 0, 0, 'completed', NOW(), NOW())";
    $logStmt = $pdo->prepare($logSql);
    $logStmt->execute(['user_email' => $_SESSION['user_email'] ?? '']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Domain added successfully',
        'domain_id' => $domainId,
        'domain_name' => $domainName
    ]);
    
} catch (Exception $e) {
    error_log('Add domain error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 