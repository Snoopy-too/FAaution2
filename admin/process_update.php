<?php
// admin/process_update.php

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

/**
 * Recursively delete a directory and its contents
 */
function recursiveDelete($dir) {
    if (!is_dir($dir)) return;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            recursiveDelete($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $step = $_POST['step'] ?? '';

    switch ($step) {
        case 'backup':
            $backupDir = __DIR__ . '/../backups/';
            if (!is_dir($backupDir)) {
                if (!@mkdir($backupDir, 0755, true)) {
                    throw new Exception('Cannot create backup directory. Check write permissions for: ' . realpath(__DIR__ . '/..'));
                }
            }

            // Check if backup directory is writable
            if (!is_writable($backupDir)) {
                throw new Exception('Backup directory is not writable. Check permissions for: ' . realpath($backupDir));
            }

            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = $backupDir . $filename;

            if (!class_exists('ZipArchive')) {
                throw new Exception('PHP Zip extension is not installed or enabled. Please enable php_zip in your php.ini file and restart your web server.');
            }

            $zip = new ZipArchive();
            $zipResult = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($zipResult !== true) {
                $zipErrors = [
                    ZipArchive::ER_EXISTS => 'File already exists',
                    ZipArchive::ER_INCONS => 'Zip archive inconsistent',
                    ZipArchive::ER_INVAL => 'Invalid argument',
                    ZipArchive::ER_MEMORY => 'Memory allocation failure',
                    ZipArchive::ER_NOENT => 'No such file',
                    ZipArchive::ER_NOZIP => 'Not a zip archive',
                    ZipArchive::ER_OPEN => 'Cannot open file',
                    ZipArchive::ER_READ => 'Read error',
                    ZipArchive::ER_SEEK => 'Seek error',
                ];
                $errorMsg = $zipErrors[$zipResult] ?? "Unknown error (code: $zipResult)";
                throw new Exception("Cannot create backup zip: $errorMsg. Check disk space and permissions.");
            }
            
            // Add files recursively
            $rootPath = realpath(__DIR__ . '/../');
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootPath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath) + 1);
                    
                    // Basic exclusions
                    if (strpos($relativePath, 'backups') === 0) continue;
                    if (strpos($relativePath, '.git') === 0) continue;
                    if (strpos($relativePath, 'node_modules') === 0) continue;
                    if (strpos($relativePath, '.vscode') === 0) continue;
                    
                    $zip->addFile($filePath, $relativePath);
                }
            }
            
            $zip->close();
            
            $response['success'] = true;
            $response['message'] = 'Backup created: ' . $filename;
            break;

        case 'download':
            $url = $_POST['url'] ?? '';
            if (!$url) {
                throw new Exception('No download URL provided. Please refresh the update page and try again.');
            }

            $tempDir = __DIR__ . '/../temp_update/';
            if (!is_dir($tempDir)) {
                if (!@mkdir($tempDir, 0755, true)) {
                    throw new Exception('Cannot create temp directory. Check server permissions for: ' . dirname($tempDir));
                }
            }

            $zipFile = $tempDir . 'update.zip';

            // Retry configuration
            $maxRetries = 3;
            $retryDelay = 2; // seconds
            $lastError = '';

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                // Clean up any partial download from previous attempt
                if (file_exists($zipFile)) {
                    @unlink($zipFile);
                }

                $fp = @fopen($zipFile, 'w+');
                if (!$fp) {
                    throw new Exception('Cannot create download file. Check write permissions for: ' . $tempDir);
                }

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 min timeout for large files
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 sec connection timeout
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
                curl_setopt($ch, CURLOPT_USERAGENT, 'FA-Auction-Updater');
                // Handle SSL certificate issues on some servers
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

                $success = curl_exec($ch);
                $curlErrno = curl_errno($ch);
                $curlError = curl_error($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $downloadSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);

                curl_close($ch);
                fclose($fp);

                // Check for success
                if ($curlErrno === 0 && $httpCode === 200 && file_exists($zipFile) && filesize($zipFile) > 1000) {
                    // Download successful
                    $response['success'] = true;
                    $sizeMB = round(filesize($zipFile) / 1048576, 2);
                    $response['message'] = "Update downloaded successfully ({$sizeMB} MB" . ($attempt > 1 ? ", attempt $attempt" : "") . ")";
                    break 2; // Exit both the for loop and the switch case
                }

                // Build detailed error message
                if ($curlErrno) {
                    $lastError = "Network error (code $curlErrno): $curlError";
                } elseif ($httpCode === 403) {
                    $lastError = "GitHub rate limit exceeded (HTTP 403). Please wait a few minutes and try again.";
                    // Don't retry rate limits immediately
                    break;
                } elseif ($httpCode === 404) {
                    $lastError = "Release not found (HTTP 404). The release may have been deleted.";
                    break; // Don't retry 404s
                } elseif ($httpCode !== 200) {
                    $lastError = "Server returned HTTP $httpCode";
                } elseif (!file_exists($zipFile) || filesize($zipFile) < 1000) {
                    $actualSize = file_exists($zipFile) ? filesize($zipFile) : 0;
                    $lastError = "Download incomplete (received $actualSize bytes, expected more)";
                }

                // Clean up failed download
                if (file_exists($zipFile)) {
                    @unlink($zipFile);
                }

                // Wait before retry (unless this was the last attempt)
                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                }
            }

            // If we get here, all retries failed
            if (!$response['success']) {
                throw new Exception("Download failed after $maxRetries attempts. $lastError");
            }
            break;

        case 'install':
            $tempDir = __DIR__ . '/../temp_update/';
            $zipFile = $tempDir . 'update.zip';

            if (!file_exists($zipFile)) {
                throw new Exception('Update file not found. The download may have failed or been interrupted. Please restart the update process.');
            }

            $zipSize = filesize($zipFile);
            if ($zipSize < 1000) {
                throw new Exception("Update file appears corrupted (size: $zipSize bytes). Please restart the update process.");
            }

            if (!class_exists('ZipArchive')) {
                throw new Exception('PHP Zip extension is not installed or enabled. Please enable php_zip in your php.ini file and restart your web server.');
            }

            $zip = new ZipArchive();
            $zipResult = $zip->open($zipFile);
            if ($zipResult !== true) {
                $zipErrors = [
                    ZipArchive::ER_INCONS => 'Archive is inconsistent/corrupted',
                    ZipArchive::ER_NOZIP => 'Not a valid zip archive',
                    ZipArchive::ER_OPEN => 'Cannot open file',
                    ZipArchive::ER_READ => 'Read error',
                ];
                $errorMsg = $zipErrors[$zipResult] ?? "Error code: $zipResult";
                throw new Exception("Failed to open update zip: $errorMsg. Try restarting the update.");
            }

            $extractPath = $tempDir . 'extracted/';
            if (!is_dir($extractPath)) {
                if (!@mkdir($extractPath, 0755, true)) {
                    $zip->close();
                    throw new Exception('Cannot create extraction directory. Check write permissions for: ' . $tempDir);
                }
            }

            $zip->extractTo($extractPath);
            $zip->close();

            // Find root folder in zip (GitHub nests everything in repo-name-commit/)
            $subDirs = glob($extractPath . '*', GLOB_ONLYDIR);

            if (empty($subDirs)) {
                throw new Exception('No directories found in update zip. Extract path: ' . $extractPath);
            }

            $sourceDir = $subDirs[0];

            // Verify source has files
            if (!is_dir($sourceDir)) {
                throw new Exception('Source directory not found: ' . $sourceDir);
            }

            // Copy files
            $destDir = realpath(__DIR__ . '/../');
            $filesCopied = 0;
            $dirsCreated = 0;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                // Normalize path separators to forward slashes for consistent comparison
                $subPath = str_replace('\\', '/', $iterator->getSubPathName());
                $destPath = $destDir . '/' . $subPath;

                // Critical Exclusions - preserve user config files
                if ($subPath === 'config/database.php' && file_exists($destPath)) continue;
                if ($subPath === 'config/installed.php' && file_exists($destPath)) continue;
                // Note: We DO want to overwrite version.txt

                if (strpos($subPath, 'install/') === 0 || $subPath === 'install') continue;
                if (strpos($subPath, 'backups/') === 0 || $subPath === 'backups') continue;
                if (strpos($subPath, 'images/uploads/') === 0) continue;
                if (strpos($subPath, 'assets/uploads/') === 0) continue;
                if (strpos($subPath, 'team_logos/') === 0 && $subPath !== 'team_logos/.gitkeep') continue;
                if (strpos($subPath, 'person_pictures/') === 0 && $subPath !== 'person_pictures/.gitkeep') continue;
                if (strpos($subPath, '.git') === 0) continue;

                if ($item->isDir()) {
                    if (!is_dir($destPath)) {
                         if (!@mkdir($destPath, 0755, true)) {
                             throw new Exception("Permission denied: Cannot create directory $subPath");
                         }
                         $dirsCreated++;
                    }
                } else {
                    $copied = @copy($item->getPathname(), $destPath);
                    if (!$copied) {
                         throw new Exception("Permission denied: Cannot overwrite file $subPath. Check server permissions.");
                    }
                    $filesCopied++;
                }
            }

            if ($filesCopied === 0) {
                throw new Exception("No files were copied. Source: $sourceDir, Found dirs: " . count($subDirs));
            }

            // Cleanup temp directory
            recursiveDelete($tempDir);

            $response['success'] = true;
            $response['message'] = "Files installed successfully ($filesCopied files, $dirsCreated directories)";
            break;
            
        case 'migrate':
            $migrationRunnerPath = __DIR__ . '/../includes/MigrationRunner.php';
            if (!file_exists($migrationRunnerPath)) {
                throw new Exception('MigrationRunner.php not found. The update files may not have been installed correctly. Try restarting the update.');
            }

            require_once $migrationRunnerPath;

            $pdo = getDBConnection();
            if (!$pdo) {
                throw new Exception('Database connection failed. Check your database configuration in config/database.php');
            }

            try {
                $runner = new MigrationRunner($pdo);
                $result = $runner->run();
            } catch (PDOException $e) {
                throw new Exception('Database error during migration: ' . $e->getMessage());
            }

            if (!$result['success']) {
                throw new Exception('Migration failed: ' . $result['error']);
            }

            // Clear the update cache so the dashboard stops showing "Update Available"
            if (function_exists('clearUpdateCache')) {
                clearUpdateCache();
            } else {
                // Fallback if not loaded, though auth.php usually loads it
                $cacheFile = __DIR__ . '/../cache/update_check.json';
                if (file_exists($cacheFile)) @unlink($cacheFile);
            }
            
            $response['success'] = true;
            $response['message'] = "Database updated ({$result['count']} migrations run)";
            break;

        default:
            throw new Exception('Invalid update step');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
