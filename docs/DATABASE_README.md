# Database Implementation for WHMCS Domain Tools

This document explains the MySQL database implementation that stores domain data locally to improve performance and eliminate API timeouts.

## Overview

The database implementation provides:
- **Local storage** of domain data from WHMCS API
- **Batch synchronization** to handle large datasets
- **Fast search and filtering** capabilities
- **Sync history tracking** for monitoring operations
- **Data integrity** with proper indexing and constraints

## Database Schema

### Tables

#### 1. `domains` - Main domain data
```sql
CREATE TABLE domains (
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
);
```

#### 2. `domain_nameservers` - Nameserver information
```sql
CREATE TABLE domain_nameservers (
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
);
```

#### 3. `sync_logs` - Sync operation tracking
```sql
CREATE TABLE sync_logs (
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
);
```

## Setup Instructions

### 1. Database Configuration

1. **Copy environment file:**
   ```bash
   cp env.example .env
   ```

2. **Edit `.env` file** with your MySQL credentials:
   ```env
   # Database Configuration (MySQL)
   DB_HOST=localhost
   DB_NAME=domain_tools
   DB_USER=your_database_username
   DB_PASSWORD=your_database_password
   DB_PORT=3306
   ```

3. **Run database setup:**
   ```
   http://your-domain.com/setup_database.php
   ```

### 2. Database Requirements

- **MySQL 5.7+** or **MariaDB 10.2+**
- **InnoDB storage engine** (for foreign key support)
- **UTF8MB4 character set** (for proper domain name storage)
- **PHP PDO MySQL extension** enabled
- **Database user** with CREATE, DROP, INSERT, UPDATE, DELETE, and SELECT privileges

### 3. File Permissions

Ensure the web server can write to the `.env` file:
```bash
chmod 644 .env
chown www-data:www-data .env  # Adjust user/group as needed
```

## Usage

### 1. Sync Interface

Access the sync interface at:
```
http://your-domain.com/sync_interface.php
```

**Features:**
- Configure batch size and number
- Real-time sync progress tracking
- Sync history and statistics
- Clear old data functionality

### 2. Database View

View domains from the database at:
```
http://your-domain.com/domains_db.php
```

**Features:**
- Fast search and filtering
- Sortable columns
- Pagination
- Domain statistics
- Nameserver information

### 3. API Endpoints

The sync system provides AJAX endpoints:

#### Start Sync
```javascript
fetch('domain_sync.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=sync_batch&batch_number=1&batch_size=200'
})
.then(response => response.json())
.then(data => console.log(data));
```

#### Get Sync Status
```javascript
fetch('domain_sync.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=get_sync_status&log_id=123'
})
.then(response => response.json())
.then(data => console.log(data));
```

## Performance Benefits

### Before Database Implementation
- **API calls on every page load** - Slow response times
- **Timeout issues** with large datasets
- **No offline capability** - Requires constant API connectivity
- **Limited search/filter** capabilities

### After Database Implementation
- **Instant page loads** - Data served from local database
- **No timeout issues** - Batch processing handles large datasets
- **Offline capability** - Works without API connectivity
- **Advanced search/filter** - Fast database queries
- **Sync history** - Track all operations

## Sync Strategy

### Batch Processing
- **Default batch size:** 200 domains per API call
- **Configurable:** 50-500 domains per batch
- **Offset-based:** Sequential batch processing
- **Error handling:** Individual domain failures don't stop the sync

### Data Integrity
- **UPSERT operations:** Insert new domains, update existing ones
- **Foreign key constraints:** Maintain referential integrity
- **Indexed queries:** Fast search and filtering
- **Sync logging:** Track all operations for debugging

### Sync Workflow
1. **Create sync log entry** - Track operation start
2. **Fetch domains from API** - Get batch of domains
3. **Process each domain** - Insert/update database
4. **Fetch nameservers** - Get additional domain data
5. **Update sync log** - Record completion status

## Maintenance

### Clear Old Data
Remove domains that haven't been synced recently:
```php
$db = Database::getInstance();
$db->clearOldData(30); // Remove domains not synced in 30 days
```

### Database Optimization
Regular maintenance tasks:
```sql
-- Analyze table statistics
ANALYZE TABLE domains, domain_nameservers, sync_logs;

-- Optimize tables
OPTIMIZE TABLE domains, domain_nameservers, sync_logs;

-- Check table status
SHOW TABLE STATUS LIKE 'domains';
```

### Backup Strategy
Regular database backups:
```bash
# Backup database
mysqldump -u username -p domain_tools > backup_$(date +%Y%m%d).sql

# Restore database
mysql -u username -p domain_tools < backup_20240101.sql
```

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed
**Symptoms:** "Database connection failed" error
**Solutions:**
- Check MySQL service is running
- Verify database credentials in `.env`
- Ensure database exists
- Check PHP PDO MySQL extension

#### 2. Permission Denied
**Symptoms:** "Access denied" errors
**Solutions:**
- Verify database user privileges
- Check file permissions on `.env`
- Ensure proper MySQL user permissions

#### 3. Sync Timeout
**Symptoms:** Sync operations hang or timeout
**Solutions:**
- Reduce batch size (try 100 instead of 200)
- Check PHP execution time limits
- Monitor server resources
- Use smaller batches for large datasets

#### 4. Memory Issues
**Symptoms:** "Out of memory" errors
**Solutions:**
- Increase PHP memory limit
- Reduce batch size
- Optimize database queries
- Monitor server memory usage

### Debug Mode

Enable debug logging by adding to `.env`:
```env
DEBUG=true
LOG_LEVEL=debug
```

Check error logs:
```bash
tail -f /var/log/apache2/error.log  # Apache
tail -f /var/log/nginx/error.log    # Nginx
```

## Migration from CSV Export

If you're currently using CSV exports, the database provides:

1. **Better performance** - No need to re-export for each query
2. **Real-time data** - Always up-to-date with latest sync
3. **Advanced filtering** - Search by domain, status, registrar, etc.
4. **Statistics** - Real-time domain counts and analytics
5. **History tracking** - See when data was last updated

## Security Considerations

1. **Database credentials** stored in `.env` file (not in code)
2. **User authentication** required for all database operations
3. **SQL injection protection** via prepared statements
4. **Input validation** on all user inputs
5. **Error logging** without exposing sensitive data

## Future Enhancements

Potential improvements:
- **Scheduled syncs** - Automatic background synchronization
- **Incremental updates** - Only sync changed domains
- **Data compression** - Reduce storage requirements
- **Multi-user support** - Separate data per user
- **API rate limiting** - Respect WHMCS API limits
- **Webhook support** - Real-time domain updates

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review error logs for specific error messages
3. Verify database configuration and permissions
4. Test with smaller batch sizes if experiencing timeouts 