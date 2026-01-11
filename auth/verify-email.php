<?php
/**
 * Email Verification Page
 *
 * Handles email verification via token and auto-login.
 */

require_once __DIR__ . '/../includes/auth.php';

$appName = getSetting('app_name', 'FA Auction');
$error = '';
$success = '';

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'No verification token provided.';
} else {
    // Verify the token
    $result = verifyEmailToken($token);

    if ($result['success']) {
        // Auto-login the user
        if (loginById($result['member']['id'])) {
            setFlashMessage('success', 'Email verified successfully! Welcome to ' . $appName . '.');

            // Redirect based on user type
            if (isAdmin()) {
                redirect(getBaseUrl() . '/admin/index.php');
            } else {
                redirect(getBaseUrl() . '/member/index.php');
            }
        } else {
            // Login failed but verification succeeded
            $success = 'Email verified successfully! You can now log in.';
        }
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo h($appName); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo h($appName); ?></h1>
                <p>Email Verification</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <strong>Verification Failed</strong><br>
                        <?php echo h($error); ?>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <p>Need a new verification link?</p>
                        <a href="resend-verification.php" class="btn btn-secondary">Resend Verification Email</a>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <strong>Success!</strong><br>
                        <?php echo h($success); ?>
                    </div>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="login.php" class="btn btn-primary">Go to Login</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="auth-footer">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
