<?php
// Test if main_page.php can be included without errors
try {
    ob_start();
    include 'main_page.php';
    $output = ob_get_clean();
    echo "✅ main_page.php loaded successfully!\n";
} catch (Exception $e) {
    echo "❌ Error loading main_page.php: " . $e->getMessage() . "\n";
}
?> 