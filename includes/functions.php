<?php
/**
 * Helper Functions
 *
 * Common utility functions used throughout the application.
 */

require_once __DIR__ . '/../config/database.php';

// Safe timezone initialization
function initTimezone() {
    static $initialized = false;
    if ($initialized) return;
    
    // Default to UTC first
    date_default_timezone_set('UTC');
    
    // Try to get setting from DB
    $pdo = getDBConnection();
    if ($pdo) {
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'timezone'");
        $tz = $stmt->fetchColumn();
        if ($tz && in_array($tz, timezone_identifiers_list())) {
            date_default_timezone_set($tz);
        }
        
        // Sync MySQL session timezone
        $offset = date('P');
        $pdo->exec("SET time_zone = '{$offset}'");
    }
    $initialized = true;
}

// Call it immediately
initTimezone();

/**
 * Position code to name mapping
 */
function getPositionName($code) {
    $positions = [
        2  => 'Catcher',
        3  => 'First Base',
        4  => 'Second Base',
        5  => 'Third Base',
        6  => 'Shortstop',
        7  => 'Left Field',
        8  => 'Center Field',
        9  => 'Right Field',
        11 => 'Starting Pitcher',
        12 => 'Reliever',
        13 => 'Closer'
    ];
    return $positions[$code] ?? 'Unknown';
}

/**
 * Position code to abbreviation
 */
function getPositionAbbr($code) {
    $positions = [
        2  => 'C',
        3  => '1B',
        4  => '2B',
        5  => '3B',
        6  => 'SS',
        7  => 'LF',
        8  => 'CF',
        9  => 'RF',
        11 => 'SP',
        12 => 'RP',
        13 => 'CL'
    ];
    return $positions[$code] ?? '??';
}

/**
 * Get all positions for dropdown/filter
 */
function getAllPositions() {
    return [
        2  => 'Catcher',
        3  => 'First Base',
        4  => 'Second Base',
        5  => 'Third Base',
        6  => 'Shortstop',
        7  => 'Left Field',
        8  => 'Center Field',
        9  => 'Right Field',
        11 => 'Starting Pitcher',
        12 => 'Reliever',
        13 => 'Closer'
    ];
}

/**
 * Format money for display
 */
function formatMoney($amount) {
    return '$' . number_format((float)$amount, 0, '.', ',');
}

/**
 * Format contract for display
 */
function formatContract($amountPerYear, $years) {
    $total = $amountPerYear * $years;
    $yearLabel = $years == 1 ? 'year' : 'years';
    return formatMoney($amountPerYear) . '/yr for ' . $years . ' ' . $yearLabel . ' (' . formatMoney($total) . ' total)';
}

/**
 * Get setting value from database
 */
function getSetting($key, $default = null) {
    $pdo = getDBConnection();
    if (!$pdo) return $default;

    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();

    return $result ? $result['setting_value'] : $default;
}

/**
 * Update setting value
 */
function updateSetting($key, $value) {
    $pdo = getDBConnection();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    return $stmt->execute([$key, $value]);
}

/**
 * Get all settings as associative array
 */
function getAllSettings() {
    $pdo = getDBConnection();
    if (!$pdo) return [];

    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Check if auction is closed
 */
function isAuctionClosed() {
    $deadlineType = getSetting('deadline_type', 'manual');

    if ($deadlineType === 'manual') {
        return getSetting('auction_closed', '0') === '1';
    } else {
        $deadline = getSetting('deadline_datetime');
        if ($deadline) {
            return strtotime($deadline) < time();
        }
    }
    return false;
}

/**
 * Get highest bid for a player
 */
function getHighestBid($playerId) {
    $pdo = getDBConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT b.*, t.name as team_name, m.name as member_name
        FROM bids b
        JOIN teams t ON b.team_id = t.id
        JOIN members m ON b.member_id = m.id
        WHERE b.player_id = ?
        ORDER BY b.total_value DESC, b.created_at ASC
        LIMIT 1
    ");
    $stmt->execute([$playerId]);
    return $stmt->fetch();
}

/**
 * Get all bids for a player
 */
function getPlayerBids($playerId) {
    $pdo = getDBConnection();
    if (!$pdo) return [];

    $stmt = $pdo->prepare("
        SELECT b.*, t.name as team_name, m.name as member_name
        FROM bids b
        JOIN teams t ON b.team_id = t.id
        JOIN members m ON b.member_id = m.id
        WHERE b.player_id = ?
        ORDER BY b.total_value DESC, b.created_at ASC
    ");
    $stmt->execute([$playerId]);
    return $stmt->fetchAll();
}

/**
 * Count non-opening bids for a team on a player
 */
function countTeamBidsOnPlayer($teamId, $playerId) {
    $pdo = getDBConnection();
    if (!$pdo) return 0;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM bids
        WHERE team_id = ? AND player_id = ? AND is_opening_bid = 0
    ");
    $stmt->execute([$teamId, $playerId]);
    $result = $stmt->fetch();
    return (int)$result['count'];
}

/**
 * Calculate available money for a team
 * Original budget minus sum of all leading bids
 */
function getAvailableMoney($teamId) {
    $pdo = getDBConnection();
    if (!$pdo) return 0;

    // Get team's original budget
    $stmt = $pdo->prepare("SELECT available_money FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $team = $stmt->fetch();
    if (!$team) return 0;

    $originalBudget = (float)$team['available_money'];

    // Get sum of all bids where this team is currently leading AND player is not archived
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(b.amount_per_year), 0) as committed
        FROM bids b
        INNER JOIN players p ON b.player_id = p.id
        WHERE b.team_id = ?
        AND p.archive_id IS NULL
        AND b.id = (
            SELECT b2.id FROM bids b2
            WHERE b2.player_id = b.player_id
            ORDER BY b2.total_value DESC, b2.created_at ASC
            LIMIT 1
        )
    ");
    $stmt->execute([$teamId]);
    $result = $stmt->fetch();
    $committed = (float)$result['committed'];

    return $originalBudget - $committed;
}

/**
 * Get player image path
 * If path starts with '/', it's treated as absolute from web root
 * Otherwise, it's relative to the app's base URL
 */
function getPlayerImagePath($playerNumber) {
    $basePath = getSetting('player_images_path', 'person_pictures/');

    // If it's a relative path (doesn't start with / or http), prepend base URL
    if (!preg_match('#^(/|https?://)#', $basePath)) {
        $basePath = getBaseUrl() . '/' . ltrim($basePath, '/');
    }

    return rtrim($basePath, '/') . '/player_' . $playerNumber . '.png';
}

/**
 * Get player HTML page URL
 */
function getPlayerHtmlUrl($playerNumber) {
    $basePath = getSetting('player_html_path', 'player_pages/');

    // If it's a relative path (doesn't start with / or http), prepend base URL
    if (!preg_match('#^(/|https?://)#', $basePath)) {
        $basePath = getBaseUrl() . '/' . ltrim($basePath, '/');
    }

    return rtrim($basePath, '/') . '/player_' . $playerNumber . '.html';
}

/**
 * Get team logo URL if it exists
 * Returns null if not found
 */
function getTeamLogoUrl($teamName) {
    if (empty($teamName)) return null;

    $pathSetting = getSetting('team_logos_path', 'team_logos/');
    $slug = strtolower(str_replace(' ', '_', trim($teamName)));
    $filename = $slug . '.png';

    // Check filesystem existence (assuming local path relative to app root)
    if (!preg_match('#^(/|https?://)#', $pathSetting)) {
        // Construct filesystem path
        // We are in includes/functions.php, so app root is __DIR__ . '/../'
        $fsPath = __DIR__ . '/../' . rtrim($pathSetting, '/') . '/' . $filename;
        if (!file_exists($fsPath)) {
            return null;
        }

        // Return URL
        return getBaseUrl() . '/' . rtrim($pathSetting, '/') . '/' . $filename;
    }

    // For absolute/remote paths, we can't easily check existence, 
    // but the user requirement implies checking. 
    // We'll return the URL and let the browser try, or assume no custom check.
    // However, to strictly follow "IF an image exists", we should probably stick to local check support.
    return null;
}

/**
 * Render team display (Logo OR Name)
 */
function getTeamDisplayHtml($teamName, $showNameWithLogo = false) {
    if (empty($teamName)) return '-';
    
    $logoUrl = getTeamLogoUrl($teamName);
    
    if ($logoUrl) {
        $img = '<img src="' . h($logoUrl) . '" alt="' . h($teamName) . '" title="' . h($teamName) . '" class="team-logo-small" style="height: 24px; vertical-align: middle;">';
        if ($showNameWithLogo) {
            return $img . ' <span style="vertical-align: middle; margin-left: 5px;">' . h($teamName) . '</span>';
        }
        return $img;
    }
    
    return h($teamName);
}

/**
 * Sanitize output for HTML
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message functions
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format date/time in user's timezone
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    if (!$datetime) return '-';
    // DateTime constructor uses the global timezone set by date_default_timezone_set()
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Get base URL
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    // Remove trailing slashes and get the app root
    $path = rtrim($path, '/\\');
    // Go up one level if we're in a subdirectory
    if (preg_match('/(admin|member|auth|install|api)$/', $path)) {
        $path = dirname($path);
    }
    return $protocol . '://' . $host . $path;
}

/**
 * Check if IP has exceeded registration rate limit
 *
 * @param string $ipAddress IP address to check
 * @return bool True if allowed, false if rate limited
 */
function checkRegistrationRateLimit($ipAddress) {
    $pdo = getDBConnection();
    if (!$pdo) return true; // Fail open if DB error

    $limit = (int)getSetting('registration_rate_limit_count', 5);
    $minutes = (int)getSetting('registration_rate_limit_minutes', 60);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts
        FROM registration_attempts
        WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$ipAddress, $minutes]);
    $result = $stmt->fetch();

    return $result['attempts'] < $limit;
}

/**
 * Log a registration attempt
 *
 * @param string $ipAddress IP address to log
 */
function logRegistrationAttempt($ipAddress) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    $stmt = $pdo->prepare("INSERT INTO registration_attempts (ip_address) VALUES (?)");
    $stmt->execute([$ipAddress]);

    // Clean old entries (older than 24 hours)
    $pdo->exec("DELETE FROM registration_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

/**
 * Get team name by ID
 *
 * @param int $teamId Team ID
 * @return string Team name
 */
function getTeamNameById($teamId) {
    $pdo = getDBConnection();
    if (!$pdo || !$teamId) return 'No Team';

    $stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $team = $stmt->fetch();

    return $team ? $team['name'] : 'Unknown Team';
}

/**
 * Get all active admin emails
 *
 * @return array Array of admin email/name pairs
 */
function getActiveAdminEmails() {
    $pdo = getDBConnection();
    if (!$pdo) return [];

    $stmt = $pdo->query("SELECT email, name FROM members WHERE is_admin = 1 AND is_active = 1");
    return $stmt->fetchAll();
}

/**
 * Validate password strength
 *
 * @param string $password Password to validate
 * @return array Array with 'valid' bool and 'errors' array
 */
function validatePasswordStrength($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Check if email is available for registration
 *
 * @param string $email Email to check
 * @return bool True if available
 */
function isEmailAvailable($email) {
    $pdo = getDBConnection();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("SELECT id FROM members WHERE email = ?");
    $stmt->execute([$email]);
    return !$stmt->fetch();
}

/**
 * Commish Code Functions
 */

/**
 * Generate a new Commish Code for member registration
 * Deactivates any existing active codes and creates a new one
 *
 * @param int|null $createdBy Admin ID who generated the code
 * @return array Code details with 'code' and 'expires_at'
 */
function generateCommishCode($createdBy = null) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        // Deactivate all existing active codes
        $pdo->exec("UPDATE commish_codes SET is_active = 0 WHERE is_active = 1");

        // Generate an 8-character alphanumeric code
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Excluding similar chars (0,O,1,I)
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Set expiration to 24 hours from now
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("
            INSERT INTO commish_codes (code, expires_at, created_by, is_active)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$code, $expiresAt, $createdBy]);

        return [
            'success' => true,
            'code' => $code,
            'expires_at' => $expiresAt
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to generate code: ' . $e->getMessage()];
    }
}

/**
 * Get the current active Commish Code
 *
 * @return array|null Code details or null if none active/valid
 */
function getActiveCommishCode() {
    $pdo = getDBConnection();
    if (!$pdo) return null;

    $stmt = $pdo->prepare("
        SELECT code, created_at, expires_at, created_by
        FROM commish_codes
        WHERE is_active = 1 AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

/**
 * Validate a Commish Code for registration
 *
 * @param string $code Code to validate
 * @return bool True if code is valid
 */
function validateCommishCode($code) {
    if (empty($code)) return false;

    $pdo = getDBConnection();
    if (!$pdo) return false;

    $stmt = $pdo->prepare("
        SELECT id FROM commish_codes
        WHERE code = ? AND is_active = 1 AND expires_at > NOW()
    ");
    $stmt->execute([strtoupper(trim($code))]);
    return (bool)$stmt->fetch();
}

/**
 * Archive Functions
 */

/**
 * Create a new archive with all current players
 *
 * @param string $name Archive name
 * @param string $description Optional description
 * @param int $createdBy Admin ID
 * @return array Result array with success status and archive_id
 */
function createArchive($name, $description = '', $createdBy = null) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $pdo->beginTransaction();

        // Count current players and bids
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM players WHERE archive_id IS NULL");
        $playerCount = $stmt->fetch()['count'];

        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM bids b 
            JOIN players p ON b.player_id = p.id 
            WHERE p.archive_id IS NULL
        ");
        $bidCount = $stmt->fetch()['count'];

        if ($playerCount == 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'No active players to archive'];
        }

        // Create archive record
        $stmt = $pdo->prepare("
            INSERT INTO archives (name, description, player_count, bid_count, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $playerCount, $bidCount, $createdBy]);
        $archiveId = $pdo->lastInsertId();

        // Update all active players to belong to this archive
        $stmt = $pdo->prepare("UPDATE players SET archive_id = ? WHERE archive_id IS NULL");
        $stmt->execute([$archiveId]);

        $pdo->commit();

        return [
            'success' => true,
            'archive_id' => $archiveId,
            'player_count' => $playerCount,
            'bid_count' => $bidCount
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get all archives
 *
 * @return array List of archives
 */
function getAllArchives() {
    $pdo = getDBConnection();
    if (!$pdo) return [];

    $stmt = $pdo->query("
        SELECT a.*, m.name as creator_name
        FROM archives a
        LEFT JOIN members m ON a.created_by = m.id
        ORDER BY a.created_at DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Get archive by ID
 *
 * @param int $archiveId Archive ID
 * @return array|false Archive data or false
 */
function getArchiveById($archiveId) {
    $pdo = getDBConnection();
    if (!$pdo || !$archiveId) return false;

    $stmt = $pdo->prepare("
        SELECT a.*, m.name as creator_name
        FROM archives a
        LEFT JOIN members m ON a.created_by = m.id
        WHERE a.id = ?
    ");
    $stmt->execute([$archiveId]);
    return $stmt->fetch();
}

/**
 * Get players for an archive
 *
 * @param int $archiveId Archive ID
 * @param int $limit Limit results
 * @param int $offset Offset for pagination
 * @return array List of players
 */
function getArchivePlayers($archiveId, $limit = 50, $offset = 0) {
    $pdo = getDBConnection();
    if (!$pdo || !$archiveId) return [];

    $stmt = $pdo->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM bids b WHERE b.player_id = p.id) as bid_count,
               (SELECT MAX(b.total_value) FROM bids b WHERE b.player_id = p.id) as highest_bid
        FROM players p
        WHERE p.archive_id = ?
        ORDER BY p.last_name, p.first_name
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute([$archiveId]);
    return $stmt->fetchAll();
}

/**
 * Count players in an archive
 *
 * @param int $archiveId Archive ID
 * @return int Player count
 */
function countArchivePlayers($archiveId) {
    $pdo = getDBConnection();
    if (!$pdo || !$archiveId) return 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM players WHERE archive_id = ?");
    $stmt->execute([$archiveId]);
    return $stmt->fetch()['count'];
}

/**
 * Get bids for an archive
 *
 * @param int $archiveId Archive ID
 * @param int $limit Limit results
 * @param int $offset Offset for pagination
 * @return array List of bids
 */
function getArchiveBids($archiveId, $limit = 50, $offset = 0) {
    $pdo = getDBConnection();
    if (!$pdo || !$archiveId) return [];

    $stmt = $pdo->prepare("
        SELECT b.*, p.first_name, p.last_name, t.name as team_name, m.name as member_name
        FROM bids b
        JOIN players p ON b.player_id = p.id
        JOIN teams t ON b.team_id = t.id
        JOIN members m ON b.member_id = m.id
        WHERE p.archive_id = ?
        ORDER BY b.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $stmt->execute([$archiveId]);
    return $stmt->fetchAll();
}

/**
 * Version & Update Functions
 */

/**
 * Get current application version
 *
 * @return string Version number (e.g., "2.0.0")
 */
function getCurrentVersion() {
    $versionFile = __DIR__ . '/../version.txt';
    return file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'Unknown';
}

/**
 * Check for updates from GitHub
 *
 * @return array|false Update info or false if no update
 */
function checkForUpdates() {
    $cacheFile = __DIR__ . '/../cache/update_check.json';
    $cacheTime = 86400; // 24 hours
    
    // Check cache first
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) return $cached;
    }
    
    $currentVersion = getCurrentVersion();
    $url = 'https://api.github.com/repos/Snoopy-too/FAaution2/releases/latest';
    
    // Helper to fetch URL
    $fetchUrl = function($url) {
        // Try cURL first as it's more reliable
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'FA-Auction-App');
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $output = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode == 200 && $output) return $output;
        }

        // Fallback to file_get_contents
        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: FA-Auction-App\r\n",
                    'timeout' => 5
                ]
            ]);
            $response = @file_get_contents($url, false, $context);
            if ($response) return $response;
        }

        return false;
    };

    $response = $fetchUrl($url);
    if (!$response) {
        return ['error' => 'Could not connect to GitHub to check for updates. ' . (function_exists('curl_init') ? 'cURL failed.' : 'cURL not installed.')];
    }
    
    $release = json_decode($response, true);
    if (!$release || !isset($release['tag_name'])) {
        return ['error' => 'Invalid response from GitHub API.'];
    }
    
    $latestVersion = ltrim($release['tag_name'], 'v');
    
    // Compare versions
    if (version_compare($latestVersion, $currentVersion, '>')) {
        $updateInfo = [
            'available' => true,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'download_url' => $release['zipball_url'] ?? null,
            'changelog' => $release['body'] ?? '',
            'release_date' => $release['published_at'] ?? '',
            'checked_at' => time()
        ];
        
        // Cache the result
        @mkdir(dirname($cacheFile), 0755, true);
        @file_put_contents($cacheFile, json_encode($updateInfo));
        
        return $updateInfo;
    }
    
    // No update available - cache this too
    $noUpdate = ['available' => false, 'checked_at' => time()];
    // Ensure cache directory exists before writing
    @mkdir(dirname($cacheFile), 0755, true);
    @file_put_contents($cacheFile, json_encode($noUpdate));
    
    return $noUpdate; // Return the array instead of false so we know it checked successfully
}

/**
 * Clear update check cache (force recheck)
 */
function clearUpdateCache() {
    $cacheFile = __DIR__ . '/../cache/update_check.json';
    if (file_exists($cacheFile)) {
        @unlink($cacheFile);
    }
}

/**
 * Check if user has dismissed an update notification
 *
 * @param string $version Version to check
 * @return bool True if dismissed
 */
function isUpdateDismissed($version) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("
        SELECT setting_value FROM settings 
        WHERE setting_key = 'dismissed_update_version'
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result && $result['setting_value'] === $version;
}

/**
 * Dismiss update notification for a specific version
 *
 * @param string $version Version to dismiss
 */
function dismissUpdate($version) {
    $pdo = getDBConnection();
    if (!$pdo) return;

    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES ('dismissed_update_version', ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->execute([$version, $version]);
}

/**
 * Calculate player age based on birth date and in-game date
 *
 * @param int|null $dayOB Day of birth
 * @param int|null $monthOB Month of birth
 * @param int|null $yearOB Year of birth
 * @return int|null Age in years, or null if birth date or in-game date not set
 */
function calculatePlayerAge($dayOB, $monthOB, $yearOB) {
    // Check if we have valid birth date
    if (!$yearOB || !$monthOB || !$dayOB) {
        return null;
    }

    // Get in-game date from settings
    $inGameDate = getSetting('in_game_date');
    if (!$inGameDate) {
        return null;
    }

    try {
        $birthDate = new DateTime("{$yearOB}-{$monthOB}-{$dayOB}");
        $currentDate = new DateTime($inGameDate);

        $age = $currentDate->diff($birthDate)->y;

        return $age;
    } catch (Exception $e) {
        return null;
    }
}
