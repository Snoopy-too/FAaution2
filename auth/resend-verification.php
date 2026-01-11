<?php
/**
 * Resend Verification Email Page
 *
 * Allows users to request a new verification email.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

$appName = getSetting('app_name', 'FA Auction');
$error = '';
$success = '';

// Pre-fill email from query string if provided
$prefillEmail = $_GET['email'] ?? '';

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
            $result = resendVerificationEmail($email);

            if ($result['success']) {
                // Send new verification email
                sendVerificationEmail($result['email'], $result['name'], $result['token']);
                $success = 'A new verification email has been sent to ' . h($email) . '. Please check your inbox.';
            } else {
                // Show generic message for security (don't reveal if email exists)
                $success = 'If an account with that email exists and is pending verification, a new verification email has been sent.';
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
    <title>Resend Verification - <?php echo h($appName); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo h($appName); ?></h1>
                <p>Resend Verification Email</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="login.php" class="btn btn-primary">Back to Login</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="margin-bottom: 20px;">
                        Enter your email address and we'll send you a new verification link.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo h($prefillEmail ?: ($_POST['email'] ?? '')); ?>"
                                   required autofocus>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Resend Verification Email
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
