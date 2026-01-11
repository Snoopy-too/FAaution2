<?php
/**
 * Admin Settings Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
requireAdmin();

define('PAGE_TITLE', 'Settings');

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'save_settings';

        if ($action === 'toggle_auction') {
            $currentStatus = getSetting('auction_closed', '0');
            updateSetting('auction_closed', $currentStatus === '1' ? '0' : '1');
            setFlashMessage('success', 'Auction status updated.');
            redirect('index.php');
        } elseif ($action === 'test_email') {
            // Test email configuration
            $testEmail = trim($_POST['test_email'] ?? '');
            if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address for testing.';
            } else {
                $result = testEmailConfiguration($testEmail);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
            }
        } else {
            // Save all settings
            $settingsToSave = [
                'min_bid_increment_percent' => max(1, min(100, (int)($_POST['min_bid_increment_percent'] ?? 5))),
                'max_contract_years' => max(2, min(15, (int)($_POST['max_contract_years'] ?? 5))),
                'max_bids_per_player' => max(1, min(99, (int)($_POST['max_bids_per_player'] ?? 3))),
                'deadline_type' => in_array($_POST['deadline_type'] ?? '', ['manual', 'datetime']) ? $_POST['deadline_type'] : 'manual',
                'deadline_datetime' => $_POST['deadline_datetime'] ?? null,
                'player_images_path' => trim($_POST['player_images_path'] ?? 'person_pictures/'),
                'player_html_path' => trim($_POST['player_html_path'] ?? 'player_pages/'),
                'team_logos_path' => trim($_POST['team_logos_path'] ?? 'team_logos/'),
                'app_name' => trim($_POST['app_name'] ?? 'FA Auction'),
                // SMTP Settings
                'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
                'smtp_username' => trim($_POST['smtp_username'] ?? ''),
                'smtp_password' => trim($_POST['smtp_password'] ?? ''),
                'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', '']) ? $_POST['smtp_encryption'] : 'tls',
                'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
                'smtp_from_name' => trim($_POST['smtp_from_name'] ?? ''),
                'timezone' => $_POST['timezone'] ?? 'UTC',
                // Registration Settings
                'registration_rate_limit_count' => max(1, min(100, (int)($_POST['registration_rate_limit_count'] ?? 5))),
                'registration_rate_limit_minutes' => max(1, min(1440, (int)($_POST['registration_rate_limit_minutes'] ?? 60)))
            ];

            foreach ($settingsToSave as $key => $value) {
                updateSetting($key, $value);
            }

            $success = 'Settings saved successfully.';
        }
    }
}

// Get current settings
$settings = getAllSettings();

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>Auction Settings</h2>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="save_settings">

    <!-- General Settings -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>General Settings</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="timezone">Application Timezone</label>
                    <div class="d-flex gap-2">
                        <select id="timezone" name="timezone" class="form-control" style="flex: 1;">
                            <?php foreach (timezone_identifiers_list() as $tz): ?>
                                <option value="<?php echo $tz; ?>" <?php echo ($settings['timezone'] ?? 'UTC') === $tz ? 'selected' : ''; ?>>
                                    <?php echo $tz; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="detectTimezone()">Detect Local</button>
                    </div>
                    <small class="text-muted">All dates and deadlines will be processed in this timezone.</small>
                </div>

                <script>
                function detectTimezone() {
                    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                    const select = document.getElementById('timezone');
                    if (tz && select) {
                        for (let i = 0; i < select.options.length; i++) {
                            if (select.options[i].value === tz) {
                                select.selectedIndex = i;
                                break;
                            }
                        }
                    }
                }
                </script>
                <div class="form-group">
                    <label for="app_name">Application Name</label>
                    <input type="text" id="app_name" name="app_name" class="form-control"
                           value="<?php echo h($settings['app_name'] ?? 'FA Auction'); ?>">
                </div>
                <div class="form-group">
                    <label for="player_images_path">Player Images Path</label>
                    <input type="text" id="player_images_path" name="player_images_path" class="form-control"
                           value="<?php echo h($settings['player_images_path'] ?? 'person_pictures/'); ?>">
                    <small class="text-muted">Folder containing player_123.png images.</small>
                </div>
                <div class="form-group">
                    <label for="team_logos_path">Team Logos Path</label>
                    <input type="text" id="team_logos_path" name="team_logos_path" class="form-control"
                           value="<?php echo h($settings['team_logos_path'] ?? 'team_logos/'); ?>">
                    <small class="text-muted">Folder containing team_name.png images.</small>
                </div>
                <div class="form-group">
                    <label for="player_html_path">Player HTML Path</label>
                    <input type="text" id="player_html_path" name="player_html_path" class="form-control"
                           value="<?php echo h($settings['player_html_path'] ?? 'player_pages/'); ?>">
                    <small class="text-muted">Folder containing player_123.html files.</small>
                </div>
            </div>
            <div class="form-group">
                <small class="text-muted">Relative paths (e.g., player_pages/) are from app root. Absolute paths start with / or http://</small>
            </div>
        </div>
    </div>

    <!-- Bidding Rules -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>Bidding Rules</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="min_bid_increment_percent">Minimum Bid Increment (%)</label>
                    <input type="number" id="min_bid_increment_percent" name="min_bid_increment_percent"
                           class="form-control" min="1" max="100"
                           value="<?php echo h($settings['min_bid_increment_percent'] ?? '5'); ?>">
                    <small class="text-muted">New bids must exceed the current highest by at least this percentage</small>
                </div>
                <div class="form-group">
                    <label for="max_contract_years">Maximum Contract Years</label>
                    <input type="number" id="max_contract_years" name="max_contract_years"
                           class="form-control" min="2" max="15"
                           value="<?php echo h($settings['max_contract_years'] ?? '5'); ?>">
                    <small class="text-muted">Maximum number of years allowed in a contract (2-15)</small>
                </div>
            </div>
            <div class="form-group">
                <label for="max_bids_per_player">Maximum Bids Per Player</label>
                <input type="number" id="max_bids_per_player" name="max_bids_per_player"
                       class="form-control" min="1" max="99" style="max-width: 200px;"
                       value="<?php echo h($settings['max_bids_per_player'] ?? '3'); ?>">
                <small class="text-muted">Maximum bids each team can make on a single player (opening bid doesn't count)</small>
            </div>
        </div>
    </div>

    <!-- Deadline Settings -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>Auction Deadline</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="deadline_type">Deadline Type</label>
                    <select id="deadline_type" name="deadline_type" class="form-control" style="max-width: 300px;"
                            onchange="toggleDeadlineFields()">
                        <option value="manual" <?php echo ($settings['deadline_type'] ?? 'manual') === 'manual' ? 'selected' : ''; ?>>
                            Manual (Admin closes auction)
                        </option>
                        <option value="datetime" <?php echo ($settings['deadline_type'] ?? '') === 'datetime' ? 'selected' : ''; ?>>
                            Date/Time (Automatic)
                        </option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Current Application Time</label>
                    <div class="form-control" style="background: var(--gray-50); font-weight: 600; border-color: var(--gray-300);">
                        <?php 
                        $now = new DateTime();
                        echo $now->format('M j, Y g:i:s A'); 
                        ?>
                        (<?php echo date_default_timezone_get(); ?>)
                    </div>
                    <small class="text-muted">If this doesn't match your clock, adjust the Timezone setting above.</small>
                </div>
            </div>
            <div class="form-group" id="deadline_datetime_group"
                 style="<?php echo ($settings['deadline_type'] ?? 'manual') === 'manual' ? 'display:none;' : ''; ?>">
                <label for="deadline_datetime">Deadline Date/Time</label>
                <input type="datetime-local" id="deadline_datetime" name="deadline_datetime"
                       class="form-control" style="max-width: 300px;"
                       value="<?php echo h($settings['deadline_datetime'] ?? ''); ?>">
                <small class="text-muted">Auction will automatically close at this date/time</small>
            </div>

            <?php if (($settings['deadline_type'] ?? 'manual') === 'manual'): ?>
                <div class="mt-2">
                    <p class="text-muted">
                        Current status:
                        <?php if (($settings['auction_closed'] ?? '0') === '1'): ?>
                            <span class="badge badge-danger">CLOSED</span>
                        <?php else: ?>
                            <span class="badge badge-success">OPEN</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-small text-muted">Use the button on the Dashboard to open/close the auction manually.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Email Settings -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>Email Settings (SMTP)</h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-2">Configure SMTP settings to enable email features (verification emails, password reset, admin notifications). Leave SMTP Host blank to use PHP's built-in mail() function.</p>

            <div class="form-row">
                <div class="form-group">
                    <label for="smtp_host">SMTP Host</label>
                    <input type="text" id="smtp_host" name="smtp_host" class="form-control"
                           value="<?php echo h($settings['smtp_host'] ?? ''); ?>"
                           placeholder="smtp.gmail.com">
                    <small class="text-muted">Leave blank to use PHP mail()</small>
                </div>
                <div class="form-group">
                    <label for="smtp_port">SMTP Port</label>
                    <input type="number" id="smtp_port" name="smtp_port" class="form-control"
                           value="<?php echo h($settings['smtp_port'] ?? '587'); ?>"
                           min="1" max="65535">
                    <small class="text-muted">Usually 587 (TLS) or 465 (SSL)</small>
                </div>
                <div class="form-group">
                    <label for="smtp_encryption">Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                        <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                        <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="" <?php echo ($settings['smtp_encryption'] ?? '') === '' ? 'selected' : ''; ?>>None</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="smtp_username">SMTP Username</label>
                    <input type="text" id="smtp_username" name="smtp_username" class="form-control"
                           value="<?php echo h($settings['smtp_username'] ?? ''); ?>"
                           placeholder="your-email@gmail.com" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="smtp_password">SMTP Password</label>
                    <input type="password" id="smtp_password" name="smtp_password" class="form-control"
                           value="<?php echo h($settings['smtp_password'] ?? ''); ?>"
                           placeholder="App password or SMTP password" autocomplete="off">
                    <small class="text-muted">For Gmail, use an App Password</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="smtp_from_email">From Email Address</label>
                    <input type="email" id="smtp_from_email" name="smtp_from_email" class="form-control"
                           value="<?php echo h($settings['smtp_from_email'] ?? ''); ?>"
                           placeholder="noreply@example.com">
                </div>
                <div class="form-group">
                    <label for="smtp_from_name">From Name</label>
                    <input type="text" id="smtp_from_name" name="smtp_from_name" class="form-control"
                           value="<?php echo h($settings['smtp_from_name'] ?? ''); ?>"
                           placeholder="<?php echo h($settings['app_name'] ?? 'FA Auction'); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Registration Settings -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>Registration Settings</h3>
        </div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="registration_rate_limit_count">Max Registration Attempts</label>
                    <input type="number" id="registration_rate_limit_count" name="registration_rate_limit_count"
                           class="form-control" min="1" max="100"
                           value="<?php echo h($settings['registration_rate_limit_count'] ?? '5'); ?>">
                    <small class="text-muted">Maximum registration attempts per IP address</small>
                </div>
                <div class="form-group">
                    <label for="registration_rate_limit_minutes">Rate Limit Window (minutes)</label>
                    <input type="number" id="registration_rate_limit_minutes" name="registration_rate_limit_minutes"
                           class="form-control" min="1" max="1440"
                           value="<?php echo h($settings['registration_rate_limit_minutes'] ?? '60'); ?>">
                    <small class="text-muted">Time window for rate limiting (1-1440 minutes)</small>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">Save Settings</button>
</form>

<!-- Test Email Form -->
<div class="card mt-3">
    <div class="card-header">
        <h3>Test Email Configuration</h3>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">Send a test email to verify your SMTP configuration is working correctly.</p>
        <form method="POST" class="form-inline">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="test_email">
            <div class="form-group" style="flex: 1; min-width: 250px;">
                <label for="test_email" class="sr-only">Test Email Address</label>
                <input type="email" id="test_email" name="test_email" class="form-control" style="width: 100%;"
                       placeholder="Enter email address" required>
            </div>
            <button type="submit" class="btn btn-secondary">Send Test Email</button>
        </form>
    </div>
</div>

<script>
function toggleDeadlineFields() {
    var type = document.getElementById('deadline_type').value;
    var datetimeGroup = document.getElementById('deadline_datetime_group');
    datetimeGroup.style.display = type === 'datetime' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
