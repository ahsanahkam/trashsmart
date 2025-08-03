<?php
/**
 * TrashSmart Session Helper Functions
 * Utility functions for session management and authentication
 */

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

/**
 * Check if current user is citizen
 */
function isCitizen() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'citizen';
}

/**
 * Require user to be logged in - redirect if not
 */
function requireAuth($redirectTo = '../backend/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Require admin access - redirect if not admin
 */
function requireAdmin($redirectTo = '../backend/login.php') {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: $redirectTo");
        exit;
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user name
 */
function getCurrentUserName() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user_name'] ?? 'User';
}

/**
 * Get current user email
 */
function getCurrentUserEmail() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user_email'] ?? '';
}

/**
 * Get current user type
 */
function getCurrentUserType() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return $_SESSION['user_type'] ?? null;
}

/**
 * Set flash message for next page load
 */
function setFlashMessage($message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    
    // Clear the flash message
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    
    return $message ? ['message' => $message, 'type' => $type] : null;
}
?>
