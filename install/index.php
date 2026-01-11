<?php
/**
 * Installation Wizard
 *
 * Guides the user through setting up the application.
 */

session_start();

// Check if already installed
if (file_exists(__DIR__ . '/../config/installed.php')) {
    header('Location: ../auth/login.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1: // Database test
            $host = $_POST['db_host'] ?? 'localhost';
            $name = $_POST['db_name'] ?? '';
            $user = $_POST['db_user'] ?? '';
            $pass = $_POST['db_pass'] ?? '';

            try {
                $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Store in session for next steps
                $_SESSION['install_db'] = compact('host', 'name', 'user', 'pass');
                header('Location: ?step=2');
                exit;
            } catch (PDOException $e) {
                $error = 'Connection failed: ' . $e->getMessage();
            }
            break;

        case 2: // Create tables
            if (!isset($_SESSION['install_db'])) {
                header('Location: ?step=1');
                exit;
            }

            $db = $_SESSION['install_db'];
            try {
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Read and execute schema
                $schema = file_get_contents(__DIR__ . '/schema.sql');
                $pdo->exec($schema);

                header('Location: ?step=3');
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to create tables: ' . $e->getMessage();
            }
            break;

        case 3: // Create admin account
            if (!isset($_SESSION['install_db'])) {
                header('Location: ?step=1');
                exit;
            }

            $email = trim($_POST['admin_email'] ?? '');
            $password = $_POST['admin_password'] ?? '';
            $confirmPassword = $_POST['admin_password_confirm'] ?? '';
            $name = trim($_POST['admin_name'] ?? '');

            if (empty($email) || empty($password) || empty($name)) {
                $error = 'All fields are required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match';
            } else {
                $db = $_SESSION['install_db'];
                try {
                    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Delete the default test admin if exists
                    $pdo->exec("DELETE FROM members WHERE email = 'admin@test.com'");

                    // Insert the new admin
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO members (email, password, name, is_admin, is_active) VALUES (?, ?, ?, 1, 1)");
                    $stmt->execute([$email, $hashedPassword, $name]);

                    header('Location: ?step=4');
                    exit;
                } catch (PDOException $e) {
                    $error = 'Failed to create admin: ' . $e->getMessage();
                }
            }
            break;

        case 4: // Final settings & complete
            if (!isset($_SESSION['install_db'])) {
                header('Location: ?step=1');
                exit;
            }

            $appName = trim($_POST['app_name'] ?? 'FA Auction');

            $db = $_SESSION['install_db'];
            try {
                $pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Update app name
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'app_name'");
                $stmt->execute([$appName]);

                // Update database config file
                $configContent = "<?php
/**
 * Database Configuration
 *
 * This file handles the database connection using PDO.
 */

// Database credentials
define('DB_HOST', " . var_export($db['host'], true) . ");
define('DB_NAME', " . var_export($db['name'], true) . ");
define('DB_USER', " . var_export($db['user'], true) . ");
define('DB_PASS', " . var_export($db['pass'], true) . ");
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection
 */
function getDBConnection() {
    static \$pdo = null;

    if (\$pdo === null) {
        \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;

        \$options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            error_log(\"Database connection failed: \" . \$e->getMessage());
            return null;
        }
    }

    return \$pdo;
}
";
                file_put_contents(__DIR__ . '/../config/database.php', $configContent);

                // Create installed flag file
                file_put_contents(__DIR__ . '/../config/installed.php', '<?php // Installed on ' . date('Y-m-d H:i:s'));

                // Clear session
                unset($_SESSION['install_db']);

                header('Location: ?step=5');
                exit;
            } catch (Exception $e) {
                $error = 'Failed to complete installation: ' . $e->getMessage();
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - FA Auction</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #0d1b2a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .install-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        .install-header {
            background: #2563eb;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .install-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .install-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .steps {
            display: flex;
            justify-content: center;
            padding: 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .step-indicator {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
            margin: 0 8px;
            background: #e2e8f0;
            color: #64748b;
        }
        .step-indicator.active {
            background: #2563eb;
            color: white;
        }
        .step-indicator.completed {
            background: #22c55e;
            color: white;
        }
        .install-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-success {
            background: #22c55e;
            color: white;
        }
        .btn-success:hover {
            background: #16a34a;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .success-icon {
            font-size: 64px;
            text-align: center;
            margin-bottom: 20px;
        }
        .step-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1f2937;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>FA Auction Setup</h1>
            <p>Installation Wizard</p>
        </div>

        <div class="steps">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="step-indicator <?php echo $i < $step ? 'completed' : ($i === $step ? 'active' : ''); ?>">
                    <?php echo $i < $step ? '&#10003;' : $i; ?>
                </div>
            <?php endfor; ?>
        </div>

        <div class="install-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <h3 class="step-title">Step 1: Database Connection</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Database Host</label>
                        <input type="text" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" name="db_name" value="fa_auction2" required>
                    </div>
                    <div class="form-group">
                        <label>Database User</label>
                        <input type="text" name="db_user" value="root" required>
                    </div>
                    <div class="form-group">
                        <label>Database Password</label>
                        <input type="password" name="db_pass" value="">
                    </div>
                    <button type="submit" class="btn btn-primary">Test Connection & Continue</button>
                </form>

            <?php elseif ($step === 2): ?>
                <h3 class="step-title">Step 2: Create Database Tables</h3>
                <p style="margin-bottom: 20px; color: #6b7280;">
                    Click the button below to create the required database tables.
                </p>
                <form method="POST">
                    <button type="submit" class="btn btn-primary">Create Tables</button>
                </form>

            <?php elseif ($step === 3): ?>
                <h3 class="step-title">Step 3: Admin Account</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Admin Name</label>
                        <input type="text" name="admin_name" required>
                    </div>
                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" name="admin_email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="admin_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="admin_password_confirm" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Admin & Continue</button>
                </form>

            <?php elseif ($step === 4): ?>
                <h3 class="step-title">Step 4: Final Settings</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Application Name</label>
                        <input type="text" name="app_name" value="FA Auction" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Complete Installation</button>
                </form>

            <?php elseif ($step === 5): ?>
                <div class="success-icon">&#10003;</div>
                <h3 class="step-title" style="text-align: center;">Installation Complete!</h3>
                <p style="text-align: center; margin-bottom: 20px; color: #6b7280;">
                    Your FA Auction application has been installed successfully.
                </p>
                <a href="../auth/login.php" class="btn btn-success" style="display: block; text-align: center; text-decoration: none;">
                    Go to Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
