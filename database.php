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
            user_email VARCHAR(255) NOT NULL,
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
            INDEX idx_user_email (user_email),
            INDEX idx_domain_name (domain_name),
            INDEX idx_status (status),
            INDEX idx_batch_number (batch_number),
            INDEX idx_last_synced (last_synced),
            UNIQUE KEY unique_user_domain (user_email, domain_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS domain_nameservers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_email VARCHAR(255) NOT NULL,
            domain_id VARCHAR(255) NOT NULL,
            ns1 VARCHAR(255),
            ns2 VARCHAR(255),
            ns3 VARCHAR(255),
            ns4 VARCHAR(255),
            ns5 VARCHAR(255),
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_email (user_email),
            INDEX idx_domain_id (domain_id),
            UNIQUE KEY unique_user_domain (user_email, domain_id)
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
        
        CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_email VARCHAR(255) NOT NULL,
            api_url VARCHAR(500) NOT NULL,
            api_identifier TEXT NOT NULL,
            api_secret TEXT NOT NULL,
            default_ns1 VARCHAR(255) NOT NULL,
            default_ns2 VARCHAR(255) NOT NULL,
            logo_url TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_email (user_email),
            INDEX idx_user_email (user_email)
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
    
    public function insertDomain($userEmail, $domainData) {
        $sql = "
        INSERT INTO domains (
            user_email, domain_id, domain_name, status, registrar, expiry_date, 
            registration_date, next_due_date, amount, currency, notes, batch_number
        ) VALUES (
            :user_email, :domain_id, :domain_name, :status, :registrar, :expiry_date,
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
            $params = array_merge(['user_email' => $userEmail], $domainData);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error inserting domain: " . $e->getMessage());
            return false;
        }
    }
    
    public function insertNameservers($userEmail, $domainId, $nameservers) {
        $sql = "
        INSERT INTO domain_nameservers (
            user_email, domain_id, ns1, ns2, ns3, ns4, ns5
        ) VALUES (
            :user_email, :domain_id, :ns1, :ns2, :ns3, :ns4, :ns5
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
                'user_email' => $userEmail,
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
    
    public function getDomains($userEmail, $page = 1, $perPage = 25, $search = '', $status = '', $orderBy = 'domain_name', $orderDir = 'ASC', $registrar = '') {
        $offset = ($page - 1) * $perPage;
        
        $whereConditions = ["d.user_email = :user_email"];
        $params = ['user_email' => $userEmail];
        
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
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $sql = "
        SELECT 
            d.*,
            dn.ns1, dn.ns2, dn.ns3, dn.ns4, dn.ns5
        FROM domains d
        LEFT JOIN domain_nameservers dn ON d.domain_id = dn.domain_id AND dn.user_email = d.user_email
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
    
    public function getDomainCount($userEmail, $search = '', $status = '', $registrar = '') {
        $whereConditions = ["user_email = :user_email"];
        $params = ['user_email' => $userEmail];
        
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
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
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
    
    public function getDomainStats($userEmail) {
        $sql = "
        SELECT 
            COUNT(*) as total_domains,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_domains,
            SUM(CASE WHEN status IN ('Expired', 'Cancelled', 'Terminated') THEN 1 ELSE 0 END) as expired_domains,
            SUM(CASE WHEN status IN ('Pending', 'PendingTransfer', 'PendingRegistration') THEN 1 ELSE 0 END) as pending_domains,
            SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) as suspended_domains
        FROM domains
        WHERE user_email = :user_email
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['user_email' => $userEmail]);
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
    
    public function getUniqueRegistrars($userEmail) {
        $sql = "SELECT DISTINCT registrar FROM domains WHERE user_email = :user_email AND registrar IS NOT NULL AND registrar != '' ORDER BY registrar ASC";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['user_email' => $userEmail]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error getting unique registrars: " . $e->getMessage());
            return [];
        }
    }
    
    // User Settings Methods
    public function saveUserSettings($userEmail, $settings) {
        $sql = "
        INSERT INTO user_settings (
            user_email, api_url, api_identifier, api_secret, 
            default_ns1, default_ns2, logo_url
        ) VALUES (
            :user_email, :api_url, :api_identifier, :api_secret,
            :default_ns1, :default_ns2, :logo_url
        ) ON DUPLICATE KEY UPDATE
            api_url = VALUES(api_url),
            api_identifier = VALUES(api_identifier),
            api_secret = VALUES(api_secret),
            default_ns1 = VALUES(default_ns1),
            default_ns2 = VALUES(default_ns2),
            logo_url = VALUES(logo_url),
            updated_at = CURRENT_TIMESTAMP
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute([
                'user_email' => $userEmail,
                'api_url' => $settings['api_url'],
                'api_identifier' => $settings['api_identifier'],
                'api_secret' => $settings['api_secret'],
                'default_ns1' => $settings['default_ns1'],
                'default_ns2' => $settings['default_ns2'],
                'logo_url' => $settings['logo_url'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Error saving user settings: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserSettings($userEmail) {
        $sql = "
        SELECT * FROM user_settings 
        WHERE user_email = :user_email
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['user_email' => $userEmail]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting user settings: " . $e->getMessage());
            return null;
        }
    }
    
    public function hasUserSettings($userEmail) {
        $sql = "
        SELECT COUNT(*) as count FROM user_settings 
        WHERE user_email = :user_email
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['user_email' => $userEmail]);
            $result = $stmt->fetch();
            return ($result['count'] ?? 0) > 0;
        } catch (PDOException $e) {
            error_log("Error checking user settings: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteUserSettings($userEmail) {
        $sql = "
        DELETE FROM user_settings 
        WHERE user_email = :user_email
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute(['user_email' => $userEmail]);
        } catch (PDOException $e) {
            error_log("Error deleting user settings: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAllUserSettings() {
        $sql = "SELECT * FROM user_settings ORDER BY updated_at DESC";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting all user settings: " . $e->getMessage());
            return [];
        }
    }
}
?> 