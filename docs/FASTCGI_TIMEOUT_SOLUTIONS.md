# FastCGI Timeout Solutions

## Problem Identified
The export functionality was still timing out even after PHP timeout fixes because **MAMP's FastCGI has its own 30-second timeout** that overrides PHP settings:

```
FastCGI: comm with server "/Applications/MAMP/fcgi-bin/php.fcgi" aborted: idle timeout (30 sec)
```

## Root Cause
- MAMP uses FastCGI to handle PHP requests
- FastCGI has a default 30-second idle timeout
- This timeout occurs at the web server level, not PHP level
- PHP timeout settings are ignored when FastCGI times out first

## Solutions Implemented

### 1. .htaccess Configuration
**File**: `.htaccess`
- Added FastCGI timeout overrides:
  ```apache
  FastCgiConfig -idle-timeout 1200
  FastCgiConfig -process-timeout 1200
  FastCgiConfig -connect-timeout 1200
  ```

### 2. PHP Headers for FastCGI
**Files**: `export_domains.php`, `main_page.php`
- Added FastCGI timeout headers:
  ```php
  if (function_exists('fastcgi_finish_request')) {
      header('X-FastCGI-Timeout: 1200');
  }
  ignore_user_abort(true);
  ```

### 3. Custom FastCGI Script
**File**: `custom_php_fcgi.sh`
- Created custom FastCGI script with extended timeout environment variables:
  ```bash
  export PHP_FCGI_IDLE_TIMEOUT=1200
  export PHP_FCGI_PROCESS_TIMEOUT=1200
  ```

### 4. Progress-Based Export System
**File**: `export_progress.php`
- Created AJAX-based export system that processes domains one at a time
- Each request is short (under 30 seconds)
- Progress is tracked via JavaScript
- Prevents long-running requests that trigger FastCGI timeout

## Testing the Solutions

### Option 1: Try the Original Export
1. Navigate to: `http://localhost:8888/domain-tools-fridge/main_page.php?view=export`
2. Try exporting Batch 2
3. The .htaccess and PHP headers should prevent the 30-second timeout

### Option 2: Use Progress-Based Export
1. Navigate to: `http://localhost:8888/domain-tools-fridge/export_progress.php`
2. Enter batch number and click "Start Export"
3. Watch real-time progress as domains are processed individually
4. No timeout issues since each request is short

## Expected Results

### With .htaccess and PHP Headers
- FastCGI timeout extended to 20 minutes
- Export should complete without 30-second timeout
- May still have issues if MAMP ignores .htaccess settings

### With Progress-Based Export
- Each domain processed in separate request
- No long-running requests that trigger FastCGI timeout
- Real-time progress feedback
- More reliable for large exports

## Files Modified
- `.htaccess` - FastCGI timeout overrides
- `export_domains.php` - Added FastCGI headers
- `main_page.php` - Added FastCGI headers
- `custom_php_fcgi.sh` - Custom FastCGI script
- `export_progress.php` - Progress-based export system

## Next Steps
If the .htaccess solution doesn't work (MAMP may ignore it):
1. Use the progress-based export system (`export_progress.php`)
2. Consider switching to a different web server configuration
3. Implement a queue-based system for very large exports 