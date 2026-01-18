<?php
/**
 * Test Update API Diagnostic Tool
 * 
 * Use this tool to verify GitHub API responses and update system configuration.
 * Mentioned in docs/UPDATE_SYSTEM_PRD.md Section 7.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Update System API Diagnostic</h2>";
echo "<p>Testing connection to GitHub API and update configuration...</p><hr>";

// 1. Check current version
$versionFile = __DIR__ . '/version.txt';
echo "<h3>1. Local Version</h3>";
if (file_exists($versionFile)) {
    $currentVersion = trim(file_get_contents($versionFile));
    echo "✅ Current Version: <strong>v{$currentVersion}</strong><br>";
} else {
    echo "❌ version.txt not found!<br>";
    $currentVersion = 'Unknown';
}

// 2. Check cache status
$cacheFile = __DIR__ . '/cache/update_check.json';
echo "<h3>2. Cache Status</h3>";
if (file_exists($cacheFile)) {
    $cacheAge = time() - filemtime($cacheFile);
    $cacheAgeHours = round($cacheAge / 3600, 2);
    $cacheContent = json_decode(file_get_contents($cacheFile), true);
    
    echo "📁 Cache file exists<br>";
    echo "⏱️ Cache age: <strong>{$cacheAgeHours} hours</strong> (expires at 24h)<br>";
    echo "📄 Cache content: <pre>" . htmlspecialchars(json_encode($cacheContent, JSON_PRETTY_PRINT)) . "</pre>";
    
    if ($cacheAge >= 86400) {
        echo "⚠️ Cache is expired and will be refreshed on next check.<br>";
    }
} else {
    echo "ℹ️ No cache file found. Will be created on first update check.<br>";
}

// 3. Test GitHub API directly
echo "<h3>3. GitHub API Test</h3>";
$githubRepo = 'Snoopy-too/FAaution2';
$url = "https://api.github.com/repos/{$githubRepo}/releases/latest";
echo "🔗 API URL: <code>{$url}</code><br><br>";

$response = null;
$error = null;

// Try cURL first
if (function_exists('curl_init')) {
    echo "Using: cURL<br>";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'FA-Auction-App');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
    }
    curl_close($ch);
    
    echo "HTTP Status: <strong>{$httpCode}</strong><br>";
} elseif (ini_get('allow_url_fopen')) {
    echo "Using: file_get_contents (cURL not available)<br>";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: FA-Auction-App\r\n",
            'timeout' => 10
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) {
        $error = "file_get_contents failed";
    }
} else {
    $error = "Neither cURL nor allow_url_fopen is available!";
}

if ($error) {
    echo "❌ Error: <span style='color:red'>{$error}</span><br>";
} elseif ($response) {
    $data = json_decode($response, true);
    
    if (isset($data['tag_name'])) {
        $latestVersion = ltrim($data['tag_name'], 'v');
        echo "✅ Latest Release: <strong>v{$latestVersion}</strong><br>";
        echo "📅 Published: " . ($data['published_at'] ?? 'N/A') . "<br>";
        echo "🔗 Download URL: <code>" . ($data['zipball_url'] ?? 'N/A') . "</code><br>";
        
        // Version comparison
        echo "<br><h4>Version Comparison:</h4>";
        if (version_compare($latestVersion, $currentVersion, '>')) {
            echo "🆕 <span style='color:green'><strong>Update Available!</strong></span> v{$currentVersion} → v{$latestVersion}<br>";
        } elseif (version_compare($latestVersion, $currentVersion, '=')) {
            echo "✅ You are running the latest version.<br>";
        } else {
            echo "🤔 Local version is newer than release? (local: v{$currentVersion}, remote: v{$latestVersion})<br>";
        }
        
        // Changelog
        if (!empty($data['body'])) {
            echo "<br><h4>Changelog:</h4>";
            echo "<pre style='background:#f8f9fa; padding:10px; max-height:200px; overflow:auto;'>" . htmlspecialchars($data['body']) . "</pre>";
        }
    } elseif (isset($data['message'])) {
        echo "⚠️ GitHub API Response: <span style='color:orange'>" . htmlspecialchars($data['message']) . "</span><br>";
        if (strpos($data['message'], 'rate limit') !== false) {
            echo "💡 Tip: GitHub allows 60 requests/hour for unauthenticated calls. Wait and try again.<br>";
        }
    } else {
        echo "❌ Unexpected API response structure.<br>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
}

// 4. Check dismissed version
echo "<h3>4. Dismissed Update Check</h3>";
require_once __DIR__ . '/config/database.php';
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'dismissed_update_version'");
    $stmt->execute();
    $dismissed = $stmt->fetchColumn();
    
    if ($dismissed) {
        echo "🔕 Dismissed version: <strong>v{$dismissed}</strong><br>";
        echo "💡 Use <a href='reset_updates.php'>reset_updates.php</a> to clear this.<br>";
    } else {
        echo "✅ No updates are dismissed.<br>";
    }
} catch (Exception $e) {
    echo "⚠️ Could not check database: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// 5. PHP Requirements
echo "<h3>5. PHP Requirements</h3>";
echo "PHP Version: <strong>" . PHP_VERSION . "</strong> ";
echo (version_compare(PHP_VERSION, '7.4.0', '>=') ? "✅" : "❌ (need 7.4+)") . "<br>";

$extensions = ['zip', 'curl', 'json', 'pdo'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "Extension <code>php-{$ext}</code>: " . ($loaded ? "✅ Loaded" : "❌ Missing") . "<br>";
}

echo "<hr><p>Done. <a href='admin/index.php'>Return to Dashboard</a> | <a href='permission_test.php'>Run Permission Test</a></p>";
?>
