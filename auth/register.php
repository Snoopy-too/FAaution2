<?php
/**
 * Registration Page
 *
 * Handles new user registration with email verification.
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

$error = '';
$success = '';
$appName = 'FA Auction';
$availableTeams = getAvailableTeams();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    }
    // Check rate limiting
    elseif (!checkRegistrationRateLimit($_SERVER['REMOTE_ADDR'])) {
        $minutes = (int)getSetting('registration_rate_limit_minutes', 60);
        $error = "Too many registration attempts. Please try again in {$minutes} minutes.";
    }
    else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';
        $teamId = (int)($_POST['team_id'] ?? 0);

        // Validate required fields
        if (empty($name) || empty($email) || empty($password) || empty($teamId)) {
            $error = 'All fields are required';
        }
        // Validate name length
        elseif (strlen($name) < 2) {
            $error = 'Name must be at least 2 characters';
        }
        // Validate email format
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        }
        // Validate password strength
        else {
            $passwordCheck = validatePasswordStrength($password);
            if (!$passwordCheck['valid']) {
                $error = implode('. ', $passwordCheck['errors']);
            }
            // Validate password match
            elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match';
            }
            else {
                // Log registration attempt
                logRegistrationAttempt($_SERVER['REMOTE_ADDR']);

                // Attempt registration
                $result = registerMember($email, $password, $name, $teamId);

                if ($result['success']) {
                    // Send verification email
                    sendVerificationEmail($email, $name, $result['token']);

                    // Send welcome email
                    $teamName = getTeamNameById($teamId);
                    sendWelcomeEmail($email, $name, $teamName);

                    // Notify admins
                    notifyAdminsOfNewUser($name, $email, $teamName);

                    setFlashMessage('success', 'Registration successful! Please check your email to verify your account.');
                    redirect('login.php');
                } else {
                    $error = $result['message'];
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
    <title>Register - <?php echo h($appName); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo h($appName); ?></h1>
                <p>Create your account</p>
            </div>
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (empty($availableTeams)): ?>
                    <div class="alert alert-warning">
                        No teams are currently available. Please contact the administrator.
                    </div>
                <?php else: ?>
                    <form method="POST" id="registerForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="form-group">
                            <label for="name">Your Name</label>
                            <input type="text" id="name" name="name" class="form-control"
                                   value="<?php echo h($_POST['name'] ?? ''); ?>"
                                   required minlength="2" autocomplete="name">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?php echo h($_POST['email'] ?? ''); ?>"
                                   required autocomplete="email">
                            <span id="email-status" class="email-status"></span>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control"
                                   required minlength="8" autocomplete="new-password">
                            <div id="password-strength" class="password-strength-meter"></div>
                            <small class="text-muted">At least 8 characters with uppercase, lowercase, and numbers</small>
                        </div>

                        <div class="form-group">
                            <label for="password_confirm">Confirm Password</label>
                            <input type="password" id="password_confirm" name="password_confirm"
                                   class="form-control" required autocomplete="new-password">
                            <span id="password-match" class="field-status"></span>
                        </div>

                        <div class="form-group">
                            <label for="team_id">Select Your Team</label>
                            <select id="team_id" name="team_id" class="form-control" required>
                                <option value="">-- Choose a team --</option>
                                <?php foreach ($availableTeams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>"
                                        <?php echo (($_POST['team_id'] ?? '') == $team['id']) ? 'selected' : ''; ?>>
                                        <?php echo h($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Register
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="auth-footer">
                Already have an account? <a href="login.php">Sign in</a><br>
                <a href="forgot-password.php">Forgot your password?</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/registration.js"></script>
</body>
</html>
