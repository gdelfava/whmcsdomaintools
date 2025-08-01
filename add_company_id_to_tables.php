<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "Please log in first.";
    exit;
}

require_once 'config.php';
require_once 'database_v2.php';

echo "<h1>Add Company ID to Tables Migration</h1>";

try {
    $db = Database::getInstance();
    
    if (!$db->isConnected()) {
        echo "<p>❌ <strong>Database connection failed</strong></p>";
        exit;
    }
    
    echo "<p>✅ <strong>Database connected successfully</strong></p>";
    
    $connection = $db->getConnection();
    
    // Check if company_id column exists in domains table
    $checkDomainsSql = "SHOW COLUMNS FROM domains LIKE 'company_id'";
    $stmt = $connection->prepare($checkDomainsSql);
    $stmt->execute();
    $companyIdExists = $stmt->fetch();
    
    if (!$companyIdExists) {
        echo "<h2>Adding company_id to domains table...</h2>";
        
        try {
            // Add company_id column to domains table
            $addCompanyIdSql = "ALTER TABLE domains ADD COLUMN company_id INT NOT NULL DEFAULT 1 AFTER id";
            $connection->exec($addCompanyIdSql);
            echo "<p>✅ Added company_id column</p>";
            
            // Add index for company_id
            $addIndexSql = "ALTER TABLE domains ADD INDEX idx_company_id (company_id)";
            $connection->exec($addIndexSql);
            echo "<p>✅ Added company_id index</p>";
            
            // Check if unique_user_domain index exists before trying to drop it
            $checkIndexSql = "SHOW INDEX FROM domains WHERE Key_name = 'unique_user_domain'";
            $stmt = $connection->prepare($checkIndexSql);
            $stmt->execute();
            $indexExists = $stmt->fetch();
            
            if ($indexExists) {
                // Update unique key to include company_id
                $dropUniqueSql = "ALTER TABLE domains DROP INDEX unique_user_domain";
                $connection->exec($dropUniqueSql);
                echo "<p>✅ Dropped old unique index</p>";
            } else {
                echo "<p>ℹ️ Old unique index doesn't exist, skipping drop</p>";
            }
            
            $addUniqueSql = "ALTER TABLE domains ADD UNIQUE KEY unique_company_user_domain (company_id, user_email, domain_id)";
            $connection->exec($addUniqueSql);
            echo "<p>✅ Added new unique index</p>";
            
            echo "<p>✅ <strong>Successfully added company_id to domains table</strong></p>";
        } catch (PDOException $e) {
            echo "<p>❌ <strong>Error adding company_id to domains table:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            throw $e;
        }
    } else {
        echo "<p>✅ <strong>company_id already exists in domains table</strong></p>";
    }
    
    // Check if company_id column exists in domain_nameservers table
    $checkNameserversSql = "SHOW COLUMNS FROM domain_nameservers LIKE 'company_id'";
    $stmt = $connection->prepare($checkNameserversSql);
    $stmt->execute();
    $companyIdExists = $stmt->fetch();
    
    if (!$companyIdExists) {
        echo "<h2>Adding company_id to domain_nameservers table...</h2>";
        
        try {
            // Add company_id column to domain_nameservers table
            $addCompanyIdSql = "ALTER TABLE domain_nameservers ADD COLUMN company_id INT NOT NULL DEFAULT 1 AFTER id";
            $connection->exec($addCompanyIdSql);
            echo "<p>✅ Added company_id column</p>";
            
            // Add index for company_id
            $addIndexSql = "ALTER TABLE domain_nameservers ADD INDEX idx_company_id (company_id)";
            $connection->exec($addIndexSql);
            echo "<p>✅ Added company_id index</p>";
            
            // Check if unique_user_domain index exists before trying to drop it
            $checkIndexSql = "SHOW INDEX FROM domain_nameservers WHERE Key_name = 'unique_user_domain'";
            $stmt = $connection->prepare($checkIndexSql);
            $stmt->execute();
            $indexExists = $stmt->fetch();
            
            if ($indexExists) {
                // Update unique key to include company_id
                $dropUniqueSql = "ALTER TABLE domain_nameservers DROP INDEX unique_user_domain";
                $connection->exec($dropUniqueSql);
                echo "<p>✅ Dropped old unique index</p>";
            } else {
                echo "<p>ℹ️ Old unique index doesn't exist, skipping drop</p>";
            }
            
            $addUniqueSql = "ALTER TABLE domain_nameservers ADD UNIQUE KEY unique_company_user_domain (company_id, user_email, domain_id)";
            $connection->exec($addUniqueSql);
            echo "<p>✅ Added new unique index</p>";
            
            echo "<p>✅ <strong>Successfully added company_id to domain_nameservers table</strong></p>";
        } catch (PDOException $e) {
            echo "<p>❌ <strong>Error adding company_id to domain_nameservers table:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            throw $e;
        }
    } else {
        echo "<p>✅ <strong>company_id already exists in domain_nameservers table</strong></p>";
    }
    
    // Check if company_id column exists in sync_logs table
    $checkSyncLogsSql = "SHOW COLUMNS FROM sync_logs LIKE 'company_id'";
    $stmt = $connection->prepare($checkSyncLogsSql);
    $stmt->execute();
    $companyIdExists = $stmt->fetch();
    
    if (!$companyIdExists) {
        echo "<h2>Adding company_id to sync_logs table...</h2>";
        
        try {
            // Add company_id column to sync_logs table
            $addCompanyIdSql = "ALTER TABLE sync_logs ADD COLUMN company_id INT NOT NULL DEFAULT 1 AFTER id";
            $connection->exec($addCompanyIdSql);
            echo "<p>✅ Added company_id column</p>";
            
            // Add index for company_id
            $addIndexSql = "ALTER TABLE sync_logs ADD INDEX idx_company_id (company_id)";
            $connection->exec($addIndexSql);
            echo "<p>✅ Added company_id index</p>";
            
            echo "<p>✅ <strong>Successfully added company_id to sync_logs table</strong></p>";
        } catch (PDOException $e) {
            echo "<p>❌ <strong>Error adding company_id to sync_logs table:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            throw $e;
        }
    } else {
        echo "<p>✅ <strong>company_id already exists in sync_logs table</strong></p>";
    }
    
    // Update existing records to have company_id = 1 (default company)
    echo "<h2>Updating existing records...</h2>";
    
    $updateDomainsSql = "UPDATE domains SET company_id = 1 WHERE company_id = 0 OR company_id IS NULL";
    $affectedDomains = $connection->exec($updateDomainsSql);
    echo "<p>Updated {$affectedDomains} records in domains table</p>";
    
    $updateNameserversSql = "UPDATE domain_nameservers SET company_id = 1 WHERE company_id = 0 OR company_id IS NULL";
    $affectedNameservers = $connection->exec($updateNameserversSql);
    echo "<p>Updated {$affectedNameservers} records in domain_nameservers table</p>";
    
    $updateSyncLogsSql = "UPDATE sync_logs SET company_id = 1 WHERE company_id = 0 OR company_id IS NULL";
    $affectedSyncLogs = $connection->exec($updateSyncLogsSql);
    echo "<p>Updated {$affectedSyncLogs} records in sync_logs table</p>";
    
    // Show table structure
    echo "<h2>Updated Table Structure</h2>";
    
    $tables = ['domains', 'domain_nameservers', 'sync_logs'];
    
    foreach ($tables as $table) {
        echo "<h3>{$table} table:</h3>";
        $describeSql = "DESCRIBE {$table}";
        $stmt = $connection->prepare($describeSql);
        $stmt->execute();
        $columns = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($column['Type'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($column['Null'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($column['Key'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    }
    
    echo "<h2>Migration Summary</h2>";
    echo "<ul>";
    echo "<li>✅ <strong>domains table:</strong> Added company_id column and updated indexes</li>";
    echo "<li>✅ <strong>domain_nameservers table:</strong> Added company_id column and updated indexes</li>";
    echo "<li>✅ <strong>sync_logs table:</strong> Added company_id column and updated indexes</li>";
    echo "<li>✅ <strong>Data migration:</strong> Updated existing records with company_id = 1</li>";
    echo "</ul>";
    
    echo "<p><strong>Migration completed successfully!</strong> You can now try the sync again.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Migration failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<p>❌ <strong>Fatal error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<p><a href='sync_interface.php'>← Back to Sync Interface</a></p>";
?> 