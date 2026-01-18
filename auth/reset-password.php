<?php
/**
 * Reset Password Page
 *
 * Allows users to set a new password using a reset token.
 */

require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(getBaseUrl() . '/admin/index.php');
    } else {
        redirect(getBaseUrl() . '/member/index.php');
    }
}

$appName = 'FA Auction';
$error = '';
$success = '';
$tokenValid = false;

// Get token from URL
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token)) {
    $error = 'No reset token provided. Please request a new password reset link.';
} else {
    // Validate token
    $tokenData = validatePasswordResetToken($token);

    if (!$tokenData) {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    } else {
        $tokenValid = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify CSRF token
            if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
                $error = 'Invalid security token. Please refresh the page and try again.';
            } else {
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['password_confirm'] ?? '';

                // Validate password strength
                $passwordCheck = validatePasswordStrength($password);
                if (!$passwordCheck['valid']) {
                    $error = implode('. ', $passwordCheck['errors']);
                } elseif ($password !== $confirmPassword) {
                    $error = 'Passwords do not match.';
                } else {
                    // Reset the password
                    $result = resetPasswordWithToken($token, $password);

                    if ($result['success']) {
                        setFlashMessage('success', 'Password reset successfully! You can now log in with your new password.');
                        redirect('login.php');
                    } else {
                        $error = $result['message'];
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo h($appName); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo h($appName); ?></h1>
                <p>Set New Password</p>
            </div>
            <div class="auth-body">
                <?php if ($error && !$tokenValid): ?>
                    <div class="alert alert-error">
                        <strong>Reset Failed</strong><br>
                        <?php echo h($error); ?>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
                    </div>
                <?php elseif ($tokenValid): ?>
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo h($error); ?></div>
                    <?php endif; ?>

                    <p class="text-muted" style="margin-bottom: 20px;">
                        Enter your new password below.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="token" value="<?php echo h($token); ?>">

                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password" class="form-control"
                                   required minlength="8" autofocus autocomplete="new-password">
                            <div id="password-strength" class="password-strength-meter"></div>
                            <small class="text-muted">At least 8 characters with uppercase, lowercase, and numbers</small>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm">Confirm New Password</label>
                            <input type="password" id="password_confirm" name="password_confirm"
                                   class="form-control" required autocomplete="new-password">
                            <span id="password-match" class="field-status"></span>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Reset Password
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="auth-footer">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/registration.js"></script>
</body>
</html>
