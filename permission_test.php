<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Server Permission & Capability Diagnostic</h2>";
echo "<p>Running as user: " . get_current_user() . "</p>";

// 1. Check Version File
$versionFile = __DIR__ . '/version.txt';
if (file_exists($versionFile)) {
    echo "✅ version.txt exists.<br>";
    echo "Current Permission: " . substr(sprintf('%o', fileperms($versionFile)), -4) . "<br>";
    if (is_writable($versionFile)) {
        echo "✅ version.txt is WRITABLE.<br>";
    } else {
        echo "❌ version.txt is NOT WRITABLE. <span style='color:red'>Update will fail.</span> (File cannot be overwritten)<br>";
    }
} else {
    echo "❌ version.txt not found.<br>";
}

// 2. Check Directory Write
$dir = __DIR__;
echo "<br>Root Directory ($dir):<br>";
if (is_writable($dir)) {
    echo "✅ Root directory is WRITABLE.<br>";
} else {
    echo "❌ Root directory is NOT WRITABLE. <span style='color:red'>New files cannot be created.</span><br>";
}

// 3. Check ZipArchive
if (class_exists('ZipArchive')) {
    echo "✅ ZipArchive class is available.<br>";
} else {
    echo "❌ ZipArchive class is MISSING. <span style='color:red'>Update cannot extract zip files.</span><br>";
}

// 4. Test Temp Directory
$tempDir = __DIR__ . '/temp_update';
if (!file_exists($tempDir)) {
    echo "Temp dir does not exist. Trying to create... ";
    if (@mkdir($tempDir, 0755, true)) {
        echo "✅ Created successfully.<br>";
        rmdir($tempDir);
    } else {
        echo "❌ Failed to create temp_update directory.<br>";
    }
} else {
    if (is_writable($tempDir)) {
        echo "✅ temp_update directory exists and is writable.<br>";
    } else {
        echo "❌ temp_update directory is NOT writable.<br>";
    }
}

echo "<br>Done.";
?>
