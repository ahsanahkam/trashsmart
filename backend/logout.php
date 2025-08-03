<?php
/**
 * TrashSmart Logout
 * Handles user logout and session cleanup
 */

session_start();

// Destroy all session data
session_unset();
session_destroy();

// Clear any cookies if they exist
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/');
}

// Redirect to home page (index.php)
header('Location: ../frontend/TrashSmart-Project/index.php');
exit;
?>
