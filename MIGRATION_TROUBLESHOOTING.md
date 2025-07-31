# Migration Troubleshooting Guide

## Common Issues and Solutions

### 1. "Migration failed! No settings were successfully migrated."

**Possible Causes:**
- No JSON files found in `user_settings/` directory
- JSON files are corrupted or invalid
- Database connection issues
- Encryption/decryption problems

**Solutions:**
1. **Check if JSON files exist:**
   - Look in the `user_settings/` directory
   - Files should be named like `[md5_hash].json`
   - If no files exist, use the manual settings entry

2. **Use Manual Settings Entry:**
   - If automatic migration fails, use the manual form
   - Enter your API credentials manually
   - This will save them directly to the database

3. **Check Database Connection:**
   - Verify database settings in `.env` file
   - Ensure database server is running
   - Check database user permissions

### 2. "Failed to decrypt settings"

**Possible Causes:**
- Encryption key mismatch
- Corrupted JSON data
- Server configuration changes

**Solutions:**
1. **Use Manual Settings Entry:**
   - This bypasses the decryption issue
   - Enter your settings manually

2. **Check Server Configuration:**
   - Ensure `ENCRYPTION_KEY` is set in `.env`
   - Verify server name hasn't changed

### 3. "Database connection failed"

**Possible Causes:**
- Database server not running
- Incorrect database credentials
- Network connectivity issues

**Solutions:**
1. **Check Database Configuration:**
   ```bash
   # Check .env file
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=domain_tools
   DB_USER=root
   DB_PASSWORD=your_password
   ```

2. **Test Database Connection:**
   - Use `test_db_settings.php` to verify connection
   - Check database server status

### 4. "Settings already exist for user"

**This is normal behavior:**
- The system prevents overwriting existing settings
- This is a safety feature
- You can manually update settings via the settings page

## Step-by-Step Troubleshooting

### Step 1: Check Current Status
1. Visit `migrate_settings_to_db.php`
2. Look at the "Debug Information" section
3. Check if JSON files are listed
4. Verify database connection status

### Step 2: Try Automatic Migration
1. Click "Start Migration"
2. Check the detailed results
3. Look for specific error messages

### Step 3: Use Manual Entry (if automatic fails)
1. Fill in the manual settings form
2. Enter your API credentials
3. Click "Save Manual Settings"

### Step 4: Verify Migration
1. Visit `test_db_settings.php`
2. Check if settings are loaded correctly
3. Test API connection

## Manual Migration Process

If automatic migration fails, follow these steps:

1. **Gather Your Settings:**
   - API URL (e.g., `https://yourdomain.com/includes/api.php`)
   - API Identifier (from WHMCS admin)
   - API Secret (from WHMCS admin)
   - Primary Nameserver (e.g., `ns1.yourdomain.com`)
   - Secondary Nameserver (e.g., `ns2.yourdomain.com`)

2. **Use Manual Entry Form:**
   - Go to `migrate_settings_to_db.php`
   - Scroll to "Manual Settings Entry"
   - Fill in all required fields
   - Click "Save Manual Settings"

3. **Verify Settings:**
   - Go to `test_db_settings.php`
   - Check that settings are saved
   - Test API connection

## Recovery Options

### Option 1: Restore from Backup
If you have backup files (`.backup` extension):
1. Rename backup files to remove `.backup`
2. Try migration again

### Option 2: Manual Re-entry
1. Use the manual settings form
2. Enter your credentials manually
3. Save to database

### Option 3: Start Fresh
1. Delete old JSON files
2. Use manual entry to set up new settings
3. Configure via settings page

## Getting Help

If you continue to have issues:

1. **Check Error Logs:**
   - PHP error log
   - Application logs in `logs/` directory

2. **Verify Requirements:**
   - PHP 7.4+ with OpenSSL extension
   - MySQL/MariaDB database
   - Proper file permissions

3. **Contact Support:**
   - Provide error messages
   - Include debug information
   - Describe steps taken

## Success Indicators

You'll know the migration was successful when:

✅ Settings appear in `test_db_settings.php`  
✅ API connection test passes  
✅ Settings persist across login sessions  
✅ No more prompts to enter API credentials  
✅ Settings work from different devices 