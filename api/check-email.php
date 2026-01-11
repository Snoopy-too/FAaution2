<?php
/**
 * Email Availability Check API
 *
 * Returns JSON response indicating if email is available for registration.
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email = trim($_GET['email'] ?? '');

// Validate email format
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'available' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

// Check availability
$available = isEmailAvailable($email);

echo json_encode([
    'available' => $available,
    'message' => $available ? 'Email is available' : 'Email is already registered'
]);
