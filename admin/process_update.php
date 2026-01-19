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
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
            $zipPath = $backupDir . $filename;
            
            if (!class_exists('ZipArchive')) {
                throw new Exception('PHP Zip extension is not enabled. Please enable it in php.ini.');
            }
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Cannot create backup zip');
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
            // For testing/simulation if no URL is provided, we might want to skip or error
            // But typical flow has URL from update info
            if (!$url) {
                // Return success for dry-run if no URL, or error?
                // Let's assume error for production safety
                 throw new Exception('No download URL provided');
            }
            
            $tempDir = __DIR__ . '/../temp_update/';
            if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
            
            $zipFile = $tempDir . 'update.zip';
            
            // Stream download
            $fp = fopen($zipFile, 'w+');
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 min timeout for large files
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'FA-Auction-Updater');
            curl_exec($ch);

            $curlError = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);
            fclose($fp);

            if ($curlError) {
                @unlink($zipFile);
                throw new Exception('Download failed: ' . curl_strerror($curlError));
            }

            if ($httpCode !== 200) {
                @unlink($zipFile);
                throw new Exception("Download failed: HTTP $httpCode. Check that the release exists on GitHub.");
            }

            // Verify file was actually downloaded
            if (!file_exists($zipFile) || filesize($zipFile) < 1000) {
                @unlink($zipFile);
                throw new Exception('Download failed: File is empty or too small');
            }

            $response['success'] = true;
            $response['message'] = 'Update downloaded successfully';
            break;

        case 'install':
            $tempDir = __DIR__ . '/../temp_update/';
            $zipFile = $tempDir . 'update.zip';
            
            if (!file_exists($zipFile)) throw new Exception('Update file not found');
            
            if (!class_exists('ZipArchive')) {
                throw new Exception('PHP Zip extension is not enabled. Please enable it in php.ini.');
            }
            $zip = new ZipArchive();
            if ($zip->open($zipFile) === TRUE) {
                $extractPath = $tempDir . 'extracted/';
                if (!is_dir($extractPath)) mkdir($extractPath, 0755, true);

                $zip->extractTo($extractPath);
                $zip->close();
                
                // Find root folder in zip
                $subDirs = glob($extractPath . '*', GLOB_ONLYDIR);

                // Pick the first subdirectory (GitHub always nests in a folder)
                // If multiple exist from previous failed attempts, use the first one
                if (count($subDirs) >= 1) {
                    $sourceDir = $subDirs[0];
                } else {
                    $sourceDir = $extractPath;
                }
                
                // Copy files
                $destDir = realpath(__DIR__ . '/../');
                
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $item) {
                    $subPath = $iterator->getSubPathName();
                    $destPath = $destDir . DIRECTORY_SEPARATOR . $subPath;
                    
                    // Critical Exclusions - preserve user config files
                    if ($subPath === 'config/database.php' && file_exists($destPath)) continue;
                    if ($subPath === 'config/installed.php' && file_exists($destPath)) continue;
                    // Note: We DO want to overwrite version.txt
                    
                    if (strpos($subPath, 'install') === 0) continue;
                    if (strpos($subPath, 'backups') === 0) continue;
                    if (strpos($subPath, 'images/uploads') === 0) continue;
                    if (strpos($subPath, 'assets/uploads') === 0) continue; // Also exclude assets/uploads if it exists
                    if (strpos($subPath, 'team_logos') === 0) continue;
                    if (strpos($subPath, 'person_pictures') === 0) continue;
                    if (strpos($subPath, '.git') === 0) continue;
                    
                    if ($item->isDir()) {
                        if (!is_dir($destPath)) {
                             if (!@mkdir($destPath, 0755, true)) {
                                 throw new Exception("Permission denied: Cannot create directory $subPath");
                             }
                        }
                    } else {
                        if (!@copy($item, $destPath)) {
                             throw new Exception("Permission denied: Cannot overwrite file $subPath. Check server permissions.");
                        }
                    }
                }
                
                // Cleanup temp directory
                recursiveDelete($tempDir);

                $response['success'] = true;
                $response['message'] = 'Files installed successfully';
            } else {
                throw new Exception('Failed to open update zip');
            }
            break;
            
        case 'migrate':
            require_once __DIR__ . '/../includes/MigrationRunner.php';
            $pdo = getDBConnection();
            $runner = new MigrationRunner($pdo);
            $result = $runner->run();
            
            if (!$result['success']) {
                throw new Exception($result['error']);
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
