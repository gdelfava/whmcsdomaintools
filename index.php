<?php
// require_once 'auth.php';
// If needed, use:
// require_once 'auth_v2.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // User not logged in, redirect to auth page
    header('Location: login.php');
    exit;
} else {
    // User is logged in, show main application
    include 'main_page.php';
}
?> 