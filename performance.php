<?php
/**
 * Performance monitoring and optimization utilities
 */

class PerformanceMonitor {
    private $startTime;
    private $markers = [];
    
    public function __construct() {
        $this->startTime = microtime(true);
    }
    
    public function mark($label) {
        $this->markers[$label] = microtime(true) - $this->startTime;
    }
    
    public function getMetrics() {
        return [
            'total_time' => microtime(true) - $this->startTime,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'markers' => $this->markers
        ];
    }
    
    public function output() {
        $metrics = $this->getMetrics();
        return sprintf(
            "<!-- Performance: %.3fs, Memory: %s, Peak: %s -->",
            $metrics['total_time'],
            $this->formatBytes($metrics['memory_usage']),
            $this->formatBytes($metrics['memory_peak'])
        );
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

/**
 * HTTP Cache headers helper
 */
function setOptimalCacheHeaders($type = 'dynamic') {
    switch ($type) {
        case 'static':
            // For CSS, JS, images - cache for 1 year
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Expires: ' . date('r', time() + 31536000));
            break;
            
        case 'api':
            // For API responses - cache for 5 minutes
            header('Cache-Control: public, max-age=300');
            header('Expires: ' . date('r', time() + 300));
            break;
            
        case 'dynamic':
        default:
            // For dynamic content - cache for 1 minute with revalidation
            header('Cache-Control: public, max-age=60, must-revalidate');
            header('Expires: ' . date('r', time() + 60));
            break;
    }
    
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
}

/**
 * Compress output for faster transfer
 */
function enableCompression() {
    if (!headers_sent() && !ob_get_level()) {
        if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            ob_start('ob_gzhandler');
        }
    }
}

/**
 * Resource hints for preloading
 */
function generateResourceHints() {
    $hints = [
        // Preload critical resources
        '<link rel="preload" href="css/optimized.css" as="style">',

        '<link rel="preload" href="https://unpkg.com/lucide@latest/dist/umd/lucide.js" as="script" crossorigin>',
        
        // DNS prefetch for external resources
        '<link rel="dns-prefetch" href="//unpkg.com">',
        '<link rel="dns-prefetch" href="//cdn.tailwindcss.com">',
        
        // Preconnect to critical external domains
        '<link rel="preconnect" href="https://fonts.googleapis.com">',
        '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
    ];
    
    return implode("\n    ", $hints);
}

/**
 * Generate critical CSS inline
 */
function getCriticalCSS() {
    $critical = file_get_contents(__DIR__ . '/css/optimized.css');
    return $critical ? $critical : '';
}

/**
 * Async script loader
 */
function loadScriptAsync($src, $defer = true) {
    $defer_attr = $defer ? ' defer' : '';
    return '<script src="' . htmlspecialchars($src) . '"' . $defer_attr . '></script>';
}

/**
 * Performance debugging (only in development)
 */
function debugPerformance() {
    if (defined('DEBUG_PERFORMANCE') && DEBUG_PERFORMANCE) {
        global $performanceMonitor;
        if ($performanceMonitor) {
            echo $performanceMonitor->output();
        }
    }
}

// Initialize performance monitoring
$performanceMonitor = new PerformanceMonitor();

// Enable compression
enableCompression();
?> 