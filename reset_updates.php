<?php
// reset_updates.php
require_once 'includes/auth.php'; // Ensures DB connection is available via functions.php/database.php

echo "<h2>Resetting Update System...</h2>";

$pdo = getDBConnection();

// 1. Clear dismissed version setting
$stmt = $pdo->prepare("DELETE FROM settings WHERE setting_key = 'dismissed_update_version'");
$stmt->execute();
if ($stmt->rowCount() > 0) {
    echo "✅ Dismissed update flag cleared from database.<br>";
} else {
    echo "ℹ️ No dismissed update flag found (or already cleared).<br>";
}

// 2. Clear cache file
$cacheFile = __DIR__ . '/cache/update_check.json';
if (file_exists($cacheFile)) {
    if (unlink($cacheFile)) {
        echo "✅ Update cache file deleted.<br>";
    } else {
        echo "❌ Failed to delete cache file.<br>";
    }
} else {
    echo "ℹ️ No cache file found.<br>";
}

echo "<br><b>Done!</b> <a href='admin/index.php'>Go to Dashboard</a>";
?>
