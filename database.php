<?php
require_once 'config.php';

class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        // Database configuration from environment variables
        $host = getEnvVar('DB_HOST', 'localhost');
        $database = getEnvVar('DB_NAME', 'domain_tools');
        $username = getEnvVar('DB_USER', 'root');
        $password = getEnvVar('DB_PASSWORD', '');
        $port = getEnvVar('DB_PORT', '3306');
        
        try {
            $this->connection = new PDO(
                "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS domains (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id VARCHAR(255) NOT NULL,
            domain_name VARCHAR(255) NOT NULL,
            status VARCHAR(50) NOT NULL,
            registrar VARCHAR(100),
            expiry_date DATE,
            registration_date DATE,
            next_due_date DATE,
            amount DECIMAL(10,2),
            currency VARCHAR(3),
            notes TEXT,
            batch_number INT DEFAULT 1,
            last_synced TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_domain_name (domain_name),
            INDEX idx_status (status),
            INDEX idx_batch_number (batch_number),
            INDEX idx_last_synced (last_synced),
            UNIQUE KEY unique_domain (domain_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS domain_nameservers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id VARCHAR(255) NOT NULL,
            ns1 VARCHAR(255),
            ns2 VARCHAR(255),
            ns3 VARCHAR(255),
            ns4 VARCHAR(255),
            ns5 VARCHAR(255),
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (domain_id) REFERENCES domains(domain_id) ON DELETE CASCADE,
            INDEX idx_domain_id (domain_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS sync_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_email VARCHAR(255) NOT NULL,
            batch_number INT NOT NULL,
            domains_found INT DEFAULT 0,
            domains_processed INT DEFAULT 0,
            domains_updated INT DEFAULT 0,
            domains_added INT DEFAULT 0,
            errors INT DEFAULT 0,
            sync_started TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sync_completed TIMESTAMP NULL,
            status ENUM('running', 'completed', 'failed') DEFAULT 'running',
            error_message TEXT,
            INDEX idx_user_email (user_email),
            INDEX idx_batch_number (batch_number),
            INDEX idx_sync_started (sync_started)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->connection->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Error creating tables: " . $e->getMessage());
            return false;
        }
    }
    
    public function insertDomain($domainData) {
        $sql = "
        INSERT INTO domains (
            domain_id, domain_name, status, registrar, expiry_date, 
            registration_date, next_due_date, amount, currency, notes, batch_number
        ) VALUES (
            :domain_id, :domain_name, :status, :registrar, :expiry_date,
            :registration_date, :next_due_date, :amount, :currency, :notes, :batch_number
        ) ON DUPLICATE KEY UPDATE
            domain_name = VALUES(domain_name),
            status = VALUES(status),
            registrar = VALUES(registrar),
            expiry_date = VALUES(expiry_date),
            registration_date = VALUES(registration_date),
            next_due_date = VALUES(next_due_date),
            amount = VALUES(amount),
            currency = VALUES(currency),
            notes = VALUES(notes),
            batch_number = VALUES(batch_number),
            last_synced = CURRENT_TIMESTAMP
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($domainData);
        } catch (PDOException $e) {
            error_log("Error inserting domain: " . $e->getMessage());
            return false;
        }
    }
    
    public function insertNameservers($domainId, $nameservers) {
        $sql = "
        INSERT INTO domain_nameservers (
            domain_id, ns1, ns2, ns3, ns4, ns5
        ) VALUES (
            :domain_id, :ns1, :ns2, :ns3, :ns4, :ns5
        ) ON DUPLICATE KEY UPDATE
            ns1 = VALUES(ns1),
            ns2 = VALUES(ns2),
            ns3 = VALUES(ns3),
            ns4 = VALUES(ns4),
            ns5 = VALUES(ns5),
            last_updated = CURRENT_TIMESTAMP
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute([
                'domain_id' => $domainId,
                'ns1' => $nameservers['ns1'] ?? null,
                'ns2' => $nameservers['ns2'] ?? null,
                'ns3' => $nameservers['ns3'] ?? null,
                'ns4' => $nameservers['ns4'] ?? null,
                'ns5' => $nameservers['ns5'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error inserting nameservers: " . $e->getMessage());
            return false;
        }
    }
    
    public function getDomains($page = 1, $perPage = 25, $search = '', $status = '', $orderBy = 'domain_name', $orderDir = 'ASC', $registrar = '') {
        $offset = ($page - 1) * $perPage;
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(d.domain_name LIKE :search OR d.registrar LIKE :search2 OR d.status LIKE :search3)";
            $params['search'] = "%$search%";
            $params['search2'] = "%$search%";
            $params['search3'] = "%$search%";
        }
        
        if (!empty($status)) {
            $whereConditions[] = "d.status = :status";
            $params['status'] = $status;
        }
        
        if (!empty($registrar)) {
            $whereConditions[] = "d.registrar = :registrar";
            $params['registrar'] = $registrar;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "
        SELECT 
            d.*,
            dn.ns1, dn.ns2, dn.ns3, dn.ns4, dn.ns5
        FROM domains d
        LEFT JOIN domain_nameservers dn ON d.domain_id = dn.domain_id
        $whereClause
        ORDER BY d.$orderBy $orderDir
        LIMIT :limit OFFSET :offset
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting domains: " . $e->getMessage());
            return [];
        }
    }
    
    public function getDomainCount($search = '', $status = '', $registrar = '') {
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(domain_name LIKE :search OR registrar LIKE :search2 OR status LIKE :search3)";
            $params['search'] = "%$search%";
            $params['search2'] = "%$search%";
            $params['search3'] = "%$search%";
        }
        
        if (!empty($status)) {
            $whereConditions[] = "status = :status";
            $params['status'] = $status;
        }
        
        if (!empty($registrar)) {
            $whereConditions[] = "registrar = :registrar";
            $params['registrar'] = $registrar;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT COUNT(*) as total FROM domains $whereClause";
        
        try {
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error getting domain count: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getDomainStats() {
        $sql = "
        SELECT 
            COUNT(*) as total_domains,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_domains,
            SUM(CASE WHEN status IN ('Expired', 'Cancelled', 'Terminated') THEN 1 ELSE 0 END) as expired_domains,
            SUM(CASE WHEN status IN ('Pending', 'PendingTransfer', 'PendingRegistration') THEN 1 ELSE 0 END) as pending_domains,
            SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) as suspended_domains
        FROM domains
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting domain stats: " . $e->getMessage());
            return [
                'total_domains' => 0,
                'active_domains' => 0,
                'expired_domains' => 0,
                'pending_domains' => 0,
                'suspended_domains' => 0
            ];
        }
    }
    
    public function createSyncLog($userEmail, $batchNumber) {
        $sql = "
        INSERT INTO sync_logs (user_email, batch_number, sync_started)
        VALUES (:user_email, :batch_number, CURRENT_TIMESTAMP)
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'user_email' => $userEmail,
                'batch_number' => $batchNumber
            ]);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating sync log: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateSyncLog($logId, $data) {
        $sql = "
        UPDATE sync_logs 
        SET domains_found = :domains_found,
            domains_processed = :domains_processed,
            domains_updated = :domains_updated,
            domains_added = :domains_added,
            errors = :errors,
            sync_completed = CURRENT_TIMESTAMP,
            status = :status,
            error_message = :error_message
        WHERE id = :log_id
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute([
                'log_id' => $logId,
                'domains_found' => $data['domains_found'] ?? 0,
                'domains_processed' => $data['domains_processed'] ?? 0,
                'domains_updated' => $data['domains_updated'] ?? 0,
                'domains_added' => $data['domains_added'] ?? 0,
                'errors' => $data['errors'] ?? 0,
                'status' => $data['status'] ?? 'completed',
                'error_message' => $data['error_message'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error updating sync log: " . $e->getMessage());
            return false;
        }
    }
    
    public function getLastSyncInfo($userEmail) {
        $sql = "
        SELECT * FROM sync_logs 
        WHERE user_email = :user_email 
        ORDER BY sync_started DESC 
        LIMIT 1
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['user_email' => $userEmail]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting last sync info: " . $e->getMessage());
            return null;
        }
    }
    
    public function clearOldData($daysOld = 30) {
        $sql = "
        DELETE FROM domains 
        WHERE last_synced < DATE_SUB(NOW(), INTERVAL :days DAY)
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute(['days' => $daysOld]);
        } catch (PDOException $e) {
            error_log("Error clearing old data: " . $e->getMessage());
            return false;
        }
    }
    
    public function getRecentSyncLogs($limit = 10) {
        $sql = "
        SELECT * FROM sync_logs 
        ORDER BY sync_started DESC 
        LIMIT :limit
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting sync logs: " . $e->getMessage());
            return [];
        }
    }
}
?> 