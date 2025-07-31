# Settings Migration to Database

This document describes the migration of user settings from JSON files to a database table for better persistence across devices and improved security.

## Overview

Previously, user settings (API credentials, nameservers, etc.) were stored in encrypted JSON files in the `user_settings/` directory. This has been migrated to a database table for the following benefits:

- **Cross-device persistence**: Settings are now available when users log in from different computers
- **Better security**: Database storage with proper encryption
- **Improved performance**: Faster access to settings
- **Centralized management**: Easier backup and maintenance

## Changes Made

### 1. Database Schema

Added a new `user_settings` table to the database:

```sql
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
```

### 2. New Files Created

- **`user_settings_db.php`**: New database-based settings class
- **`migrate_settings_to_db.php`**: Migration tool to move JSON settings to database
- **`test_db_settings.php`**: Test script to verify database settings work

### 3. Updated Files

- **`database.php`**: Added user settings methods and table creation
- **`settings.php`**: Updated to use database-based settings
- **`main_page.php`**: Updated all settings function calls

## Migration Process

### Step 1: Run Migration

1. Navigate to `migrate_settings_to_db.php` in your browser
2. Click "Start Migration" to move existing JSON settings to database
3. Original JSON files will be backed up with `.backup` extension

### Step 2: Verify Migration

1. Use `test_db_settings.php` to verify database settings work
2. Check that settings are accessible in the main application
3. Test API connections with the new database settings

### Step 3: Clean Up (Optional)

After confirming everything works:
1. Delete the original JSON files from `user_settings/` directory
2. Remove the backup files (`.backup` extension)

## Security Features

- **Encryption**: Sensitive data (API credentials) are encrypted using AES-256-CBC
- **Server-specific keys**: Encryption keys are derived from server configuration
- **Database security**: Settings are stored in the database with proper access controls

## Function Mapping

| Old Function | New Function | Purpose |
|-------------|-------------|---------|
| `getUserSettings()` | `getUserSettingsDB()` | Get current user's settings |
| `userHasSettings()` | `userHasSettingsDB()` | Check if user has settings |
| `validateSettingsCompleteness()` | `validateSettingsCompletenessDB()` | Validate settings completeness |
| `getLogoUrl()` | `getLogoUrlDB()` | Get logo URL with fallback |

## Testing

### Test Database Settings
```bash
# Access the test page
http://your-domain.com/test_db_settings.php
```

### Test Migration
```bash
# Access the migration page
http://your-domain.com/migrate_settings_to_db.php
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database configuration in `.env` file
   - Ensure database server is running
   - Verify database user permissions

2. **Migration Failed**
   - Check file permissions on `user_settings/` directory
   - Verify JSON files are readable
   - Check database table creation

3. **Settings Not Loading**
   - Verify user is logged in
   - Check database connection
   - Review error logs

### Error Logs

Check the following for error messages:
- PHP error log
- Application logs in `logs/` directory
- Database error logs

## Rollback Plan

If issues occur, you can rollback by:

1. Restoring original JSON files from `.backup` extensions
2. Temporarily switching back to `user_settings.php` in affected files
3. Removing the `user_settings` table from database if needed

## Benefits After Migration

- ✅ Settings persist across different devices
- ✅ No need to re-enter API credentials on new computers
- ✅ Centralized settings management
- ✅ Better security with database storage
- ✅ Improved performance and reliability
- ✅ Easier backup and restore procedures

## Support

If you encounter any issues during migration:

1. Check the error logs
2. Use the test scripts to verify functionality
3. Review the troubleshooting section above
4. Contact support if problems persist 