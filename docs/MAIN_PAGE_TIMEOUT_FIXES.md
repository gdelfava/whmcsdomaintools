# Main Page Export Timeout Fixes

## Problem Identified
The export functionality accessed through `main_page.php?view=export` was experiencing timeout errors:
- **Error**: `Maximum execution time of 30 seconds exceeded in main_page.php on line 465`
- **Root Cause**: Multiple API calls and inefficient processing causing PHP execution time limits to be exceeded

## Solutions Implemented

### 1. PHP Timeout Configuration
**File**: `main_page.php`
- Added timeout configuration at the top of the file:
  ```php
  ini_set('max_execution_time', 1200); // 20 minutes
  ini_set('memory_limit', '1024M'); // Increase memory limit
  set_time_limit(1200); // Set script timeout to 20 minutes
  ```

### 2. Export Batch Size Optimization
**File**: `main_page.php` (lines 430-500)
- Reduced batch size from 200 to 50 domains per batch
- Updated all UI references to reflect the new batch size
- This significantly reduces processing time per batch

### 3. API Call Optimization
**File**: `main_page.php` (lines 520-580)
- **Before**: Multiple `getAllDomains()` calls for dashboard stats and recent projects
- **After**: Single `getAllDomains()` call with cached results for both purposes
- This eliminates redundant API calls that were causing timeouts

### 4. Processing Optimizations
**File**: `main_page.php` (export processing loop)
- Reduced delay between API calls from 0.25 to 0.1 seconds
- Added timeout reset during processing loop
- Enhanced error messages for timeout scenarios

### 5. Better Error Handling
- Added specific timeout error detection
- Improved progress reporting
- Added timeout protection indicators

## Key Changes Made

### Export Processing (Lines 430-500)
```php
// Before
$batchSize = 200; // Keep at 200 to avoid timeouts
usleep(250000); // 0.25 second delay

// After  
$batchSize = 50; // Reduced from 200 to 50 to prevent timeouts
usleep(100000); // Reduced to 0.1 second delay for faster processing
```

### Dashboard Data Optimization (Lines 520-580)
```php
// Before: Multiple API calls
$response = getAllDomains(...); // For dashboard stats
$response = getAllDomains(...); // For recent projects

// After: Single API call with caching
$response = getAllDomains(...); // Single call
$allDomains = $response['domains']['domain'];
// Use $allDomains for both dashboard stats and recent projects
```

## Expected Results
- Export process should now complete without timeout errors
- Each batch processes 50 domains instead of 200
- Dashboard loads faster due to single API call
- Total processing time per batch: ~2-3 minutes (instead of timing out)
- Better user feedback during the export process

## Testing
1. Navigate to: `http://localhost:8888/domain-tools-fridge/main_page.php?view=export`
2. Try exporting Batch 1 (50 domains)
3. Process should complete successfully without timeout errors
4. Dashboard should load faster without multiple API calls

## Files Modified
- `main_page.php` - Main page with export functionality and timeout fixes
- `api.php` - API timeout configuration (already optimized)

## Performance Improvements
- **API Calls**: Reduced from 2+ calls to 1 call for dashboard data
- **Batch Size**: Reduced from 200 to 50 domains per batch
- **Processing Speed**: Reduced delays between API calls
- **Memory Usage**: Increased memory limit to 1024M
- **Timeout Protection**: 20-minute script timeout instead of 30 seconds 