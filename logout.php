<?php
require_once 'auth_v2.php';

// Logout the user
logoutUser();

// Redirect to login page
header('Location: login_v2.php');
exit;
?> 