<?php
require_once 'auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // User not logged in, redirect to auth page
    header('Location: auth_page.php');
    exit;
} else {
    // User is logged in, show main application
    include 'main_page.php';
}
?> 