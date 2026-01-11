<?php
/**
 * Authentication Functions
 *
 * Handles user authentication, session management, and access control.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Attempt to log in a user
 *
 * @param string $email User's email
 * @param string $password User's password
 * @return array Result with success status and message
 */
function login($email, $password) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $stmt = $pdo->prepare("
        SELECT m.*, t.name as team_name
        FROM members m
        LEFT JOIN teams t ON m.team_id = t.id
        WHERE m.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Your account has been deactivated'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['is_admin'] = (bool)$user['is_admin'];
    $_SESSION['team_id'] = $user['team_id'];
    $_SESSION['team_name'] = $user['team_name'];

    return ['success' => true, 'message' => 'Login successful', 'is_admin' => (bool)$user['is_admin']];
}

/**
 * Log out the current user
 */
function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is an admin
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Get current user's ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's team ID
 */
function getCurrentTeamId() {
    return $_SESSION['team_id'] ?? null;
}

/**
 * Get current user's team name
 */
function getCurrentTeamName() {
    return $_SESSION['team_name'] ?? null;
}

/**
 * Get current user's name
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Require user to be logged in, redirect to login if not
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please log in to access this page');
        redirect(getBaseUrl() . '/auth/login.php');
    }
}

/**
 * Require user to be an admin, redirect if not
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlashMessage('error', 'You do not have permission to access this page');
        redirect(getBaseUrl() . '/member/index.php');
    }
}

/**
 * Require user to be a member (not admin) with a team
 */
function requireMember() {
    requireLogin();
    if (isAdmin()) {
        redirect(getBaseUrl() . '/admin/index.php');
    }
    if (!getCurrentTeamId()) {
        setFlashMessage('error', 'You must have a team assigned to access this page');
        redirect(getBaseUrl() . '/auth/login.php');
    }
}

/**
 * Register a new member
 *
 * @param string $email User's email
 * @param string $password User's password
 * @param string $name User's name
 * @param int $teamId Selected team ID
 * @return array Result with success status and message
 */
function registerMember($email, $password, $name, $teamId) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM members WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'This email is already registered. <a href="login.php">Sign in</a> or <a href="forgot-password.php">reset your password</a>.'];
    }

    // Check if team is already taken
    $stmt = $pdo->prepare("SELECT id FROM members WHERE team_id = ? AND is_active = 1");
    $stmt->execute([$teamId]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'This team has already been selected by another member. Please choose a different team.'];
    }

    // Generate email verification token
    $verificationToken = bin2hex(random_bytes(32));

    // Hash password and insert with is_active = 0 until verified
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO members (email, password, name, team_id, is_admin, is_active, email_verification_token)
        VALUES (?, ?, ?, ?, 0, 0, ?)
    ");

    try {
        $stmt->execute([$email, $hashedPassword, $name, $teamId, $verificationToken]);
        $memberId = $pdo->lastInsertId();

        return [
            'success' => true,
            'message' => 'Registration successful',
            'member_id' => $memberId,
            'token' => $verificationToken
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration failed. Please try again later.'];
    }
}

/**
 * Verify email with token
 *
 * @param string $token Verification token
 * @return array Result with success status and member info
 */
function verifyEmailToken($token) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $stmt = $pdo->prepare("
        SELECT id, name, email, team_id FROM members
        WHERE email_verification_token = ? AND is_active = 0
    ");
    $stmt->execute([$token]);
    $member = $stmt->fetch();

    if (!$member) {
        return ['success' => false, 'message' => 'Invalid or expired verification link. The link may have already been used.'];
    }

    $stmt = $pdo->prepare("
        UPDATE members
        SET is_active = 1,
            email_verification_token = NULL,
            email_verified_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$member['id']]);

    return ['success' => true, 'member' => $member];
}

/**
 * Log in a user by ID (for auto-login after verification)
 *
 * @param int $userId User ID
 * @return bool Success status
 */
function loginById($userId) {
    $user = getMemberById($userId);
    if (!$user || !$user['is_active']) {
        return false;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['is_admin'] = (bool)$user['is_admin'];
    $_SESSION['team_id'] = $user['team_id'];
    $_SESSION['team_name'] = $user['team_name'];

    return true;
}

/**
 * Get member by email
 *
 * @param string $email Email address
 * @return array|null Member data or null
 */
function getMemberByEmail($email) {
    $pdo = getDBConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT m.*, t.name as team_name
        FROM members m
        LEFT JOIN teams t ON m.team_id = t.id
        WHERE m.email = ?
    ");
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

/**
 * Create password reset token
 *
 * @param string $email User's email
 * @return string|null Token or null if user not found
 */
function createPasswordResetToken($email) {
    $pdo = getDBConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("SELECT id, name FROM members WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $member = $stmt->fetch();

    if (!$member) {
        return null; // Don't reveal if email exists
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $pdo->prepare("
        INSERT INTO password_reset_tokens (member_id, token, expires_at)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$member['id'], $token, $expiresAt]);

    return $token;
}

/**
 * Validate password reset token
 *
 * @param string $token Reset token
 * @return array|null Token data or null if invalid
 */
function validatePasswordResetToken($token) {
    $pdo = getDBConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT prt.id, prt.member_id, m.email, m.name
        FROM password_reset_tokens prt
        JOIN members m ON prt.member_id = m.id
        WHERE prt.token = ?
        AND prt.expires_at > NOW()
        AND prt.used_at IS NULL
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/**
 * Reset password with token
 *
 * @param string $token Reset token
 * @param string $newPassword New password
 * @return array Result with success status
 */
function resetPasswordWithToken($token, $newPassword) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $tokenData = validatePasswordResetToken($token);

    if (!$tokenData) {
        return ['success' => false, 'message' => 'Invalid or expired reset link. Please request a new one.'];
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $stmt = $pdo->prepare("UPDATE members SET password = ? WHERE id = ?");
    $stmt->execute([$hashedPassword, $tokenData['member_id']]);

    // Mark token as used
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
    $stmt->execute([$tokenData['id']]);

    return ['success' => true, 'message' => 'Password reset successful'];
}

/**
 * Resend verification email
 *
 * @param string $email User's email
 * @return array Result with success status and token
 */
function resendVerificationEmail($email) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    $stmt = $pdo->prepare("
        SELECT id, name, email FROM members
        WHERE email = ? AND is_active = 0 AND email_verification_token IS NOT NULL
    ");
    $stmt->execute([$email]);
    $member = $stmt->fetch();

    if (!$member) {
        return ['success' => false, 'message' => 'No pending verification found for this email.'];
    }

    // Generate new token
    $newToken = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("UPDATE members SET email_verification_token = ? WHERE id = ?");
    $stmt->execute([$newToken, $member['id']]);

    return [
        'success' => true,
        'token' => $newToken,
        'name' => $member['name'],
        'email' => $member['email']
    ];
}

/**
 * Get available teams (not selected by any active member)
 */
function getAvailableTeams() {
    $pdo = getDBConnection();
    if (!$pdo) return [];

    $stmt = $pdo->query("
        SELECT t.*
        FROM teams t
        WHERE t.id NOT IN (
            SELECT team_id FROM members WHERE team_id IS NOT NULL AND is_active = 1
        )
        ORDER BY t.name
    ");
    return $stmt->fetchAll();
}

/**
 * Get all teams
 */
function getAllTeams() {
    $pdo = getDBConnection();
    if (!$pdo) return [];

    $stmt = $pdo->query("SELECT * FROM teams ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get all members
 */
function getAllMembers() {
    $pdo = getDBConnection();
    if (!$pdo) return [];

    $stmt = $pdo->query("
        SELECT m.*, t.name as team_name
        FROM members m
        LEFT JOIN teams t ON m.team_id = t.id
        ORDER BY m.name
    ");
    return $stmt->fetchAll();
}

/**
 * Get member by ID
 */
function getMemberById($id) {
    $pdo = getDBConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT m.*, t.name as team_name
        FROM members m
        LEFT JOIN teams t ON m.team_id = t.id
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Update member
 */
function updateMember($id, $data) {
    $pdo = getDBConnection();
    if (!$pdo) return false;

    $fields = [];
    $values = [];

    if (isset($data['email'])) {
        $fields[] = 'email = ?';
        $values[] = $data['email'];
    }
    if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $values[] = $data['name'];
    }
    if (isset($data['team_id'])) {
        $fields[] = 'team_id = ?';
        $values[] = $data['team_id'] ?: null;
    }
    if (isset($data['is_active'])) {
        $fields[] = 'is_active = ?';
        $values[] = $data['is_active'];
    }
    if (!empty($data['password'])) {
        $fields[] = 'password = ?';
        $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    if (empty($fields)) return true;

    $values[] = $id;
    $sql = "UPDATE members SET " . implode(', ', $fields) . " WHERE id = ?";

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Refresh session data (call after team changes, etc.)
 */
function refreshSession() {
    if (!isLoggedIn()) return;

    $user = getMemberById(getCurrentUserId());
    if ($user) {
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['team_id'] = $user['team_id'];
        $_SESSION['team_name'] = $user['team_name'];
        $_SESSION['is_admin'] = (bool)$user['is_admin'];
    }
}
