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
    if (!isset($_POST['domain_id']) || empty(trim($_POST['domain_id']))) {
        echo json_encode(['success' => false, 'error' => 'Domain ID is required']);
        exit;
    }
    
    $domainId = trim($_POST['domain_id']);
    $pdo = $db->getConnection();
    
    // Check if domain exists
    $checkStmt = $pdo->prepare("SELECT id, domain_name FROM domains WHERE domain_id = :domain_id LIMIT 1");
    $checkStmt->execute(['domain_id' => $domainId]);
    $domain = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$domain) {
        echo json_encode(['success' => false, 'error' => 'Domain not found in database']);
        exit;
    }
    
    $domainName = $domain['domain_name'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Delete nameserver records first (foreign key constraint)
        $deleteNsStmt = $pdo->prepare("DELETE FROM domain_nameservers WHERE domain_id = :domain_id");
        $deleteNsStmt->execute(['domain_id' => $domainId]);
        
        // Delete domain record
        $deleteDomainStmt = $pdo->prepare("DELETE FROM domains WHERE domain_id = :domain_id");
        $result = $deleteDomainStmt->execute(['domain_id' => $domainId]);
        
        if (!$result) {
            throw new Exception('Failed to delete domain from database');
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Domain deleted successfully',
            'domain_id' => $domainId,
            'domain_name' => $domainName
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Delete domain error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 