<?php
/**
 * Login Page
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

$error = '';
$appName = getSetting('app_name', 'FA Auction');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter your email and password';
        } else {
            $result = login($email, $password);
            if ($result['success']) {
                if ($result['is_admin']) {
                    redirect(getBaseUrl() . '/admin/index.php');
                } else {
                    redirect(getBaseUrl() . '/member/index.php');
                }
            } else {
                // Check if account is pending verification
                if ($result['message'] === 'Your account has been deactivated') {
                    $member = getMemberByEmail($email);
                    if ($member && !empty($member['email_verification_token'])) {
                        $error = 'Please verify your email before logging in. <a href="resend-verification.php?email=' . urlencode($email) . '">Resend verification email</a>';
                    } else {
                        $error = 'Your account has been deactivated. Please contact the administrator.';
                    }
                } elseif ($result['message'] === 'Invalid email or password') {
                    $error = 'The email or password you entered is incorrect. <a href="forgot-password.php">Forgot your password?</a>';
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo h($appName); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo h($appName); ?></h1>
                <p>Sign in to your account</p>
            </div>
            <div class="auth-body">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?>">
                        <?php echo h($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?php echo h($_POST['email'] ?? ''); ?>" required autofocus autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        Sign In
                    </button>
                </form>
            </div>
            <div class="auth-footer">
                Don't have an account? <a href="register.php">Register here</a><br>
                <a href="forgot-password.php">Forgot your password?</a>
            </div>
        </div>
    </div>
</body>
</html>
