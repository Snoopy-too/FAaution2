<?php
/**
 * Forgot Password Page
 *
 * Allows users to request a password reset link.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Try to create reset token
            $token = createPasswordResetToken($email);

            if ($token) {
                // Get member name for email
                $member = getMemberByEmail($email);
                if ($member) {
                    sendPasswordResetEmail($email, $member['name'], $token);
                }
            }

            // Always show success message for security (don't reveal if email exists)
            $success = 'If an account with that email exists, we have sent password reset instructions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo h($appName); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo h($appName); ?></h1>
                <p>Reset Your Password</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo h($success); ?></div>
                    <p style="text-align: center; margin-top: 20px;">
                        Check your email for a link to reset your password.<br>
                        If it doesn't appear within a few minutes, check your spam folder.
                    </p>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="login.php" class="btn btn-primary">Back to Login</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="margin-bottom: 20px;">
                        Enter your email address and we'll send you instructions to reset your password.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo h($_POST['email'] ?? ''); ?>"
                                   required autofocus autocomplete="email">
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Send Reset Link
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="auth-footer">
                <a href="login.php">Back to Login</a> |
                <a href="register.php">Create an Account</a>
            </div>
        </div>
    </div>
</body>
</html>
