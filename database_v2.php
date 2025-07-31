<?php
require_once 'config.php';

class Database {
    private static $instance = null;
    private $connection;
    
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
        try {
            $host = getEnvVar('DB_HOST', 'localhost');
            $port = getEnvVar('DB_PORT', '3306');
            $database = getEnvVar('DB_NAME', 'domain_tools');
            $username = getEnvVar('DB_USER', 'root');
            $password = getEnvVar('DB_PASSWORD', '');
            
            // For MAMP, try socket connection first, then fallback to TCP
            if ($host === 'localhost' && $port === '8889') {
                // Try MAMP socket connection
                $dsn = "mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;dbname=$database;charset=utf8mb4";
            } else {
                $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
            }
            
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            // Don't throw exception, just set connection to null
            $this->connection = null;
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function isConnected() {
        return $this->connection !== null;
    }
    
    public function createTables() {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(255) NOT NULL,
            company_address TEXT,
            contact_number VARCHAR(50),
            contact_email VARCHAR(255),
            logo_url TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_name (company_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('normal_user', 'server_admin') DEFAULT 'normal_user',
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            UNIQUE KEY unique_email (email),
            INDEX idx_company_id (company_id),
            INDEX idx_role (role),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS domains (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
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
            INDEX idx_company_id (company_id),
            INDEX idx_user_email (user_email),
            INDEX idx_domain_name (domain_name),
            INDEX idx_status (status),
            INDEX idx_batch_number (batch_number),
            INDEX idx_last_synced (last_synced),
            UNIQUE KEY unique_company_user_domain (company_id, user_email, domain_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS domain_nameservers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            domain_id VARCHAR(255) NOT NULL,
            ns1 VARCHAR(255),
            ns2 VARCHAR(255),
            ns3 VARCHAR(255),
            ns4 VARCHAR(255),
            ns5 VARCHAR(255),
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_company_id (company_id),
            INDEX idx_user_email (user_email),
            INDEX idx_domain_id (domain_id),
            UNIQUE KEY unique_company_user_domain (company_id, user_email, domain_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS sync_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
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
            INDEX idx_company_id (company_id),
            INDEX idx_user_email (user_email),
            INDEX idx_batch_number (batch_number),
            INDEX idx_sync_started (sync_started)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            api_url VARCHAR(500) NOT NULL,
            api_identifier TEXT NOT NULL,
            api_secret TEXT NOT NULL,
            default_ns1 VARCHAR(255) NOT NULL,
            default_ns2 VARCHAR(255) NOT NULL,
            logo_url TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_company_user_email (company_id, user_email),
            INDEX idx_company_id (company_id),
            INDEX idx_user_email (user_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role ENUM('normal_user', 'server_admin') NOT NULL,
            permission_name VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role_permission (role, permission_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $this->connection->exec($sql);
            $this->insertDefaultPermissions();
            return true;
        } catch (PDOException $e) {
            error_log("Error creating tables: " . $e->getMessage());
            return false;
        }
    }
    
    private function insertDefaultPermissions() {
        $permissions = [
            ['normal_user', 'view_domains', 'Can view domains'],
            ['normal_user', 'edit_domains', 'Can edit domains'],
            ['normal_user', 'add_domains', 'Can add domains'],
            ['normal_user', 'delete_domains', 'Can delete domains'],
            ['normal_user', 'sync_domains', 'Can sync domains'],
            ['normal_user', 'view_settings', 'Can view settings'],
            ['normal_user', 'edit_settings', 'Can edit settings'],
            ['server_admin', 'view_domains', 'Can view domains'],
            ['server_admin', 'edit_domains', 'Can edit domains'],
            ['server_admin', 'add_domains', 'Can add domains'],
            ['server_admin', 'delete_domains', 'Can delete domains'],
            ['server_admin', 'sync_domains', 'Can sync domains'],
            ['server_admin', 'view_settings', 'Can view settings'],
            ['server_admin', 'edit_settings', 'Can edit settings'],
            ['server_admin', 'database_setup', 'Can access database setup'],
            ['server_admin', 'create_tables', 'Can create database tables'],
            ['server_admin', 'manage_users', 'Can manage users'],
            ['server_admin', 'manage_company', 'Can manage company profile'],
            ['server_admin', 'view_logs', 'Can view system logs']
        ];
        
        $sql = "INSERT IGNORE INTO permissions (role, permission_name, description) VALUES (?, ?, ?)";
        $stmt = $this->connection->prepare($sql);
        
        foreach ($permissions as $permission) {
            $stmt->execute($permission);
        }
    }
    
    // Company Management Methods
    public function createCompany($companyData) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        INSERT INTO companies (company_name, company_address, contact_number, contact_email, logo_url)
        VALUES (:company_name, :company_address, :contact_number, :contact_email, :logo_url)
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'company_name' => $companyData['company_name'],
                'company_address' => $companyData['company_address'] ?? '',
                'contact_number' => $companyData['contact_number'] ?? '',
                'contact_email' => $companyData['contact_email'] ?? '',
                'logo_url' => $companyData['logo_url'] ?? ''
            ]);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating company: " . $e->getMessage());
            return false;
        }
    }
    
    public function getCompany($companyId) {
        if (!$this->isConnected()) {
            return null;
        }
        
        $sql = "SELECT * FROM companies WHERE id = :company_id";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['company_id' => $companyId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting company: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateCompany($companyId, $companyData) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        UPDATE companies SET 
            company_name = :company_name,
            company_address = :company_address,
            contact_number = :contact_number,
            contact_email = :contact_email,
            logo_url = :logo_url,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :company_id
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute([
                'company_id' => $companyId,
                'company_name' => $companyData['company_name'],
                'company_address' => $companyData['company_address'] ?? '',
                'contact_number' => $companyData['contact_number'] ?? '',
                'contact_email' => $companyData['contact_email'] ?? '',
                'logo_url' => $companyData['logo_url'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("Error updating company: " . $e->getMessage());
            return false;
        }
    }
    
    // User Management Methods
    public function createUser($userData) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        INSERT INTO users (company_id, email, password_hash, role, first_name, last_name)
        VALUES (:company_id, :email, :password_hash, :role, :first_name, :last_name)
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'company_id' => $userData['company_id'],
                'email' => $userData['email'],
                'password_hash' => $userData['password_hash'],
                'role' => $userData['role'] ?? 'normal_user',
                'first_name' => $userData['first_name'] ?? '',
                'last_name' => $userData['last_name'] ?? ''
            ]);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserByEmail($email) {
        if (!$this->isConnected()) {
            return null;
        }
        
        $sql = "
        SELECT u.*, c.company_name 
        FROM users u 
        JOIN companies c ON u.company_id = c.id 
        WHERE u.email = :email AND u.is_active = TRUE
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['email' => $email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting user: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateUserLastLogin($userId) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :user_id";
        
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute(['user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error updating user last login: " . $e->getMessage());
            return false;
        }
    }
    
    public function getCompanyUsers($companyId) {
        if (!$this->isConnected()) {
            return [];
        }
        
        $sql = "
        SELECT id, email, role, first_name, last_name, is_active, created_at, last_login
        FROM users 
        WHERE company_id = :company_id 
        ORDER BY created_at DESC
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['company_id' => $companyId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting company users: " . $e->getMessage());
            return [];
        }
    }
    
    // Permission Methods
    public function hasPermission($role, $permission) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        SELECT COUNT(*) as count 
        FROM permissions 
        WHERE role = :role AND permission_name = :permission
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['role' => $role, 'permission' => $permission]);
            $result = $stmt->fetch();
            return ($result['count'] ?? 0) > 0;
        } catch (PDOException $e) {
            error_log("Error checking permission: " . $e->getMessage());
            return false;
        }
    }
    
    // Updated Domain Methods (with company_id)
    public function insertDomain($companyId, $userEmail, $domainData) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        INSERT INTO domains (
            company_id, user_email, domain_id, domain_name, status, registrar, expiry_date, 
            registration_date, next_due_date, amount, currency, notes, batch_number
        ) VALUES (
            :company_id, :user_email, :domain_id, :domain_name, :status, :registrar, :expiry_date,
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
            last_synced = NOW()
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $domainData['company_id'] = $companyId;
            $domainData['user_email'] = $userEmail;
            return $stmt->execute($domainData);
        } catch (PDOException $e) {
            error_log("Error inserting domain: " . $e->getMessage());
            return false;
        }
    }
    
    public function getDomains($companyId, $userEmail, $page = 1, $perPage = 25, $search = '', $status = '', $orderBy = 'domain_name', $orderDir = 'ASC', $registrar = '') {
        if (!$this->isConnected()) {
            return [];
        }
        
        $whereConditions = ["company_id = :company_id", "user_email = :user_email"];
        $params = ['company_id' => $companyId, 'user_email' => $userEmail];
        
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
        $offset = ($page - 1) * $perPage;
        
        $sql = "
        SELECT * FROM domains 
        $whereClause 
        ORDER BY $orderBy $orderDir 
        LIMIT :limit OFFSET :offset
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting domains: " . $e->getMessage());
            return [];
        }
    }
    
    public function getDomainCount($companyId, $userEmail, $search = '', $status = '', $registrar = '') {
        if (!$this->isConnected()) {
            return 0;
        }
        
        $whereConditions = ["company_id = :company_id", "user_email = :user_email"];
        $params = ['company_id' => $companyId, 'user_email' => $userEmail];
        
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
    
    public function getDomainStats($companyId, $userEmail) {
        if (!$this->isConnected()) {
            return [
                'total_domains' => 0,
                'active_domains' => 0,
                'expired_domains' => 0,
                'pending_domains' => 0,
                'suspended_domains' => 0
            ];
        }
        
        $sql = "
        SELECT 
            COUNT(*) as total_domains,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_domains,
            SUM(CASE WHEN status IN ('Expired', 'Cancelled', 'Terminated') THEN 1 ELSE 0 END) as expired_domains,
            SUM(CASE WHEN status IN ('Pending', 'PendingTransfer', 'PendingRegistration') THEN 1 ELSE 0 END) as pending_domains,
            SUM(CASE WHEN status = 'Suspended' THEN 1 ELSE 0 END) as suspended_domains
        FROM domains
        WHERE company_id = :company_id AND user_email = :user_email
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['company_id' => $companyId, 'user_email' => $userEmail]);
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
    
    public function getUniqueRegistrars($companyId, $userEmail) {
        if (!$this->isConnected()) {
            return [];
        }
        
        $sql = "SELECT DISTINCT registrar FROM domains WHERE company_id = :company_id AND user_email = :user_email AND registrar IS NOT NULL AND registrar != '' ORDER BY registrar ASC";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['company_id' => $companyId, 'user_email' => $userEmail]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error getting unique registrars: " . $e->getMessage());
            return [];
        }
    }
    
    // User Settings Methods (with company_id)
    public function saveUserSettings($companyId, $userEmail, $settings) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        INSERT INTO user_settings (
            company_id, user_email, api_url, api_identifier, api_secret, 
            default_ns1, default_ns2, logo_url
        ) VALUES (
            :company_id, :user_email, :api_url, :api_identifier, :api_secret,
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
                'company_id' => $companyId,
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
    
    public function getUserSettings($companyId, $userEmail) {
        if (!$this->isConnected()) {
            return null;
        }
        
        $sql = "
        SELECT * FROM user_settings 
        WHERE company_id = :company_id AND user_email = :user_email
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['company_id' => $companyId, 'user_email' => $userEmail]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting user settings: " . $e->getMessage());
            return null;
        }
    }
    
    public function hasUserSettings($companyId, $userEmail) {
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        SELECT COUNT(*) as count FROM user_settings 
        WHERE company_id = :company_id AND user_email = :user_email
        ";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['company_id' => $companyId, 'user_email' => $userEmail]);
            $result = $stmt->fetch();
            return ($result['count'] ?? 0) > 0;
        } catch (PDOException $e) {
            error_log("Error checking user settings: " . $e->getMessage());
            return false;
        }
    }
    
    // Sync Log Methods
    public function getLastSyncInfo($userEmail) {
        if (!$this->isConnected()) {
            return null;
        }
        
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
    
    public function createSyncLog($userEmail, $batchNumber) {
        if (!$this->isConnected()) {
            return false;
        }
        
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
        if (!$this->isConnected()) {
            return false;
        }
        
        $sql = "
        UPDATE sync_logs SET 
            domains_found = :domains_found,
            domains_processed = :domains_processed,
            domains_updated = :domains_updated,
            domains_added = :domains_added,
            errors = :errors,
            sync_completed = CURRENT_TIMESTAMP,
            status = :status
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
                'status' => $data['status'] ?? 'completed'
            ]);
        } catch (PDOException $e) {
            error_log("Error updating sync log: " . $e->getMessage());
            return false;
        }
    }
}
?> 