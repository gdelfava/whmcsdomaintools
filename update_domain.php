<?php
require_once 'auth.php';
require_once 'database.php';

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
    $requiredFields = ['domain_id', 'domain_name', 'registrar', 'status', 'expiry_date'];
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
    $domainId = trim($_POST['domain_id']);
    $domainName = trim($_POST['domain_name']);
    $registrar = trim($_POST['registrar']);
    $status = trim($_POST['status']);
    $expiryDate = $_POST['expiry_date'];
    $nameservers = isset($_POST['nameservers']) ? trim($_POST['nameservers']) : '';
    
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
    
    // Check if domain exists for this user
    $pdo = $db->getConnection();
    $checkStmt = $pdo->prepare("SELECT id FROM domains WHERE user_email = :user_email AND domain_id = :domain_id LIMIT 1");
    $checkStmt->execute(['user_email' => $userEmail, 'domain_id' => $domainId]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Domain not found in database or not owned by current user']);
        exit;
    }
    
    // Update domain in database
    $sql = "UPDATE domains SET 
            domain_name = :domain_name,
            registrar = :registrar,
            status = :status,
            expiry_date = :expiry_date,
            last_synced = CURRENT_TIMESTAMP
            WHERE user_email = :user_email AND domain_id = :domain_id";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        'user_email' => $userEmail,
        'domain_id' => $domainId,
        'domain_name' => $domainName,
        'registrar' => $registrar,
        'status' => $status,
        'expiry_date' => $expiryDate
    ]);
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Failed to update domain in database']);
        exit;
    }
    
    // Update nameservers if provided
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
            
            // Check if nameserver record exists for this user
            $nsCheckStmt = $pdo->prepare("SELECT id FROM domain_nameservers WHERE user_email = :user_email AND domain_id = :domain_id LIMIT 1");
            $nsCheckStmt->execute(['user_email' => $userEmail, 'domain_id' => $domainId]);
            
            if ($nsCheckStmt->fetch()) {
                // Update existing nameserver record
                $nsSql = "UPDATE domain_nameservers SET ns1 = :ns1, ns2 = :ns2, ns3 = :ns3, ns4 = :ns4, ns5 = :ns5, last_updated = CURRENT_TIMESTAMP WHERE user_email = :user_email AND domain_id = :domain_id";
            } else {
                // Insert new nameserver record
                $nsSql = "INSERT INTO domain_nameservers (user_email, domain_id, ns1, ns2, ns3, ns4, ns5) VALUES (:user_email, :domain_id, :ns1, :ns2, :ns3, :ns4, :ns5)";
            }
            
            $nsStmt = $pdo->prepare($nsSql);
            $nsStmt->execute([
                'user_email' => $userEmail,
                'domain_id' => $domainId,
                'ns1' => $nsData['ns1'],
                'ns2' => $nsData['ns2'],
                'ns3' => $nsData['ns3'],
                'ns4' => $nsData['ns4'],
                'ns5' => $nsData['ns5']
            ]);
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Domain updated successfully',
        'domain_id' => $domainId,
        'domain_name' => $domainName
    ]);
    
} catch (Exception $e) {
    error_log('Update domain error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 