<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/../includes/auth.php';

logout();
setFlashMessage('success', 'You have been logged out successfully.');
redirect(getBaseUrl() . '/auth/login.php');
