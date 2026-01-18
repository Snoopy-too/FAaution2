<?php
/**
 * Email Service
 *
 * Handles all email sending functionality using PHPMailer.
 */

require_once __DIR__ . '/functions.php';

// Check if PHPMailer exists before loading
$phpmailerPath = __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
define('PHPMAILER_AVAILABLE', file_exists($phpmailerPath));

if (PHPMAILER_AVAILABLE) {
    require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Get configured PHPMailer instance
 *
 * @return PHPMailer|null
 */
function getMailer() {
    if (!PHPMAILER_AVAILABLE) {
        error_log('PHPMailer not available - vendor/phpmailer files missing');
        return null;
    }

    $mail = new PHPMailer(true);

    try {
        $smtpHost = getSetting('smtp_host', '');

        if (empty($smtpHost)) {
            // No SMTP configured, use PHP mail()
            $mail->isMail();
        } else {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->Port = (int)getSetting('smtp_port', 587);

            $username = getSetting('smtp_username', '');
            $password = getSetting('smtp_password', '');

            if (!empty($username) && !empty($password)) {
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
            }

            $encryption = getSetting('smtp_encryption', 'tls');
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            // Disable SSL verification for local development
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }

        $fromEmail = getSetting('smtp_from_email', '');
        $fromName = getSetting('smtp_from_name', 'FA Auction');

        if (!empty($fromEmail)) {
            $mail->setFrom($fromEmail, $fromName);
        }

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        return $mail;
    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Send an email
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML content
 * @param string $textBody Plain text content (optional)
 * @return bool Success status
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    $mail = getMailer();

    if (!$mail) {
        error_log('Failed to initialize mailer');
        return false;
    }

    try {
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        if (!empty($textBody)) {
            $mail->AltBody = $textBody;
        } else {
            // Generate plain text from HTML
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        }

        return $mail->send();
    } catch (Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get email template wrapper
 *
 * @param string $content Inner HTML content
 * @return string Complete HTML email
 */
function getEmailTemplate($content) {
    $appName = 'FA Auction';

    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($appName) . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 30px; margin: 20px 0; }
        .header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid #eee; margin-bottom: 20px; }
        .header h1 { color: #2563eb; margin: 0; font-size: 24px; }
        .content { padding: 20px 0; }
        .button { display: inline-block; padding: 12px 24px; background-color: #2563eb; color: #fff !important; text-decoration: none; border-radius: 6px; font-weight: 500; margin: 20px 0; }
        .button:hover { background-color: #1d4ed8; }
        .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eee; margin-top: 20px; font-size: 12px; color: #666; }
        .text-muted { color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>' . htmlspecialchars($appName) . '</h1>
            </div>
            <div class="content">
                ' . $content . '
            </div>
            <div class="footer">
                <p>This is an automated message from ' . htmlspecialchars($appName) . '.</p>
            </div>
        </div>
    </div>
</body>
</html>';
}

/**
 * Send email verification email
 *
 * @param string $email Recipient email
 * @param string $name User's name
 * @param string $token Verification token
 * @return bool Success status
 */
function sendVerificationEmail($email, $name, $token) {
    $appName = 'FA Auction';
    $baseUrl = getBaseUrl();
    $verifyUrl = $baseUrl . '/auth/verify-email.php?token=' . urlencode($token);

    $content = '
        <h2>Verify Your Email Address</h2>
        <p>Hello ' . htmlspecialchars($name) . ',</p>
        <p>Thank you for registering with ' . htmlspecialchars($appName) . '! Please click the button below to verify your email address:</p>
        <p style="text-align: center;">
            <a href="' . htmlspecialchars($verifyUrl) . '" class="button">Verify Email Address</a>
        </p>
        <p class="text-muted">If you did not create an account, no further action is required.</p>
        <p class="text-muted">If the button doesn\'t work, copy and paste this link into your browser:</p>
        <p class="text-muted" style="word-break: break-all;">' . htmlspecialchars($verifyUrl) . '</p>
    ';

    return sendEmail($email, 'Verify Your Email - ' . $appName, getEmailTemplate($content));
}

/**
 * Send welcome email after registration
 *
 * @param string $email Recipient email
 * @param string $name User's name
 * @param string $teamName Team name
 * @return bool Success status
 */
function sendWelcomeEmail($email, $name, $teamName = '') {
    $appName = 'FA Auction';
    $baseUrl = getBaseUrl();

    $teamInfo = !empty($teamName) ? '<p>You have been assigned to team: <strong>' . htmlspecialchars($teamName) . '</strong></p>' : '';

    $content = '
        <h2>Welcome to ' . htmlspecialchars($appName) . '!</h2>
        <p>Hello ' . htmlspecialchars($name) . ',</p>
        <p>Your account has been successfully created. Welcome to our free agent auction platform!</p>
        ' . $teamInfo . '
        <p>Once your email is verified, you can:</p>
        <ul>
            <li>Browse available free agents</li>
            <li>Place bids on players</li>
            <li>Track your team\'s budget and bids</li>
        </ul>
        <p style="text-align: center;">
            <a href="' . htmlspecialchars($baseUrl) . '/auth/login.php" class="button">Go to Login</a>
        </p>
        <p class="text-muted">If you have any questions, please contact your league administrator.</p>
    ';

    return sendEmail($email, 'Welcome to ' . $appName, getEmailTemplate($content));
}

/**
 * Send password reset email
 *
 * @param string $email Recipient email
 * @param string $name User's name
 * @param string $token Reset token
 * @return bool Success status
 */
function sendPasswordResetEmail($email, $name, $token) {
    $appName = 'FA Auction';
    $baseUrl = getBaseUrl();
    $resetUrl = $baseUrl . '/auth/reset-password.php?token=' . urlencode($token);

    $content = '
        <h2>Reset Your Password</h2>
        <p>Hello ' . htmlspecialchars($name) . ',</p>
        <p>We received a request to reset your password. Click the button below to set a new password:</p>
        <p style="text-align: center;">
            <a href="' . htmlspecialchars($resetUrl) . '" class="button">Reset Password</a>
        </p>
        <p class="text-muted">This link will expire in 1 hour.</p>
        <p class="text-muted">If you did not request a password reset, you can safely ignore this email.</p>
        <p class="text-muted">If the button doesn\'t work, copy and paste this link into your browser:</p>
        <p class="text-muted" style="word-break: break-all;">' . htmlspecialchars($resetUrl) . '</p>
    ';

    return sendEmail($email, 'Reset Your Password - ' . $appName, getEmailTemplate($content));
}

/**
 * Notify all active admins of a new user registration
 *
 * @param string $memberName New member's name
 * @param string $memberEmail New member's email
 * @param string $teamName Team name
 * @return int Number of admins notified
 */
function notifyAdminsOfNewUser($memberName, $memberEmail, $teamName = '') {
    $appName = 'FA Auction';
    $admins = getActiveAdminEmails();
    $notified = 0;

    $teamInfo = !empty($teamName) ? '<p><strong>Team:</strong> ' . htmlspecialchars($teamName) . '</p>' : '';

    $content = '
        <h2>New User Registration</h2>
        <p>A new user has registered on ' . htmlspecialchars($appName) . ':</p>
        <div style="background: #f9fafb; padding: 15px; border-radius: 6px; margin: 15px 0;">
            <p><strong>Name:</strong> ' . htmlspecialchars($memberName) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($memberEmail) . '</p>
            ' . $teamInfo . '
            <p><strong>Registered:</strong> ' . date('F j, Y g:i A') . '</p>
        </div>
        <p class="text-muted">The user will need to verify their email before they can log in.</p>
    ';

    $htmlEmail = getEmailTemplate($content);

    foreach ($admins as $admin) {
        if (sendEmail($admin['email'], 'New User Registration - ' . $appName, $htmlEmail)) {
            $notified++;
        }
    }

    return $notified;
}

/**
 * Test email configuration
 *
 * @param string $testEmail Email to send test to
 * @return array Result with success status and message
 */
function testEmailConfiguration($testEmail) {
    $appName = 'FA Auction';

    $content = '
        <h2>Email Configuration Test</h2>
        <p>This is a test email from ' . htmlspecialchars($appName) . '.</p>
        <p>If you received this email, your SMTP configuration is working correctly!</p>
        <p class="text-muted">Sent at: ' . date('F j, Y g:i:s A') . '</p>
    ';

    $result = sendEmail($testEmail, 'Test Email - ' . $appName, getEmailTemplate($content));

    if ($result) {
        return ['success' => true, 'message' => 'Test email sent successfully to ' . $testEmail];
    } else {
        return ['success' => false, 'message' => 'Failed to send test email. Please check your SMTP settings.'];
    }
}
