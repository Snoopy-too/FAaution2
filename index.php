<?php
/**
 * FA Auction - Entry Point
 *
 * Redirects to appropriate page based on installation status and login state.
 */

// Check if installed
if (!file_exists(__DIR__ . '/config/installed.php')) {
    header('Location: install/index.php');
    exit;
}

require_once __DIR__ . '/includes/auth.php';

// Redirect based on login state
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/index.php');
    } else {
        header('Location: member/index.php');
    }
} else {
    header('Location: auth/login.php');
}
exit;
