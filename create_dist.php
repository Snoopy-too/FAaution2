<?php
// create_dist.php
$version = '2.0.1';
$zipFile = __DIR__ . "/faAuction_v{$version}_dist.zip";

if (file_exists($zipFile)) {
    unlink($zipFile);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Cannot create zip file\n");
}

$rootPath = __DIR__;
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

echo "Creating distribution zip v{$version}...\n";

foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($rootPath) + 1);
        
        // Normalize slashes for consistency
        $relativePath = str_replace('\\', '/', $relativePath);

        // Exclusions
        if (strpos($relativePath, '.git') === 0) continue;
        if (strpos($relativePath, '.vscode') === 0) continue;
        if (strpos($relativePath, '.idea') === 0) continue;
        if (strpos($relativePath, 'node_modules') === 0) continue;
        if (strpos($relativePath, 'temp_update') === 0) continue;
        if (strpos($relativePath, 'backups') === 0) continue;
        if (strpos($relativePath, 'cache') === 0) continue;
        
        // Exclude test/dev files
        if ($relativePath === 'create_dist.php') continue;
        if ($relativePath === 'test_update.php') continue;
        if (strpos($relativePath, 'test_') === 0) continue;
        if (strpos($relativePath, 'uploaded_image_') === 0) continue; // Screenshots?
        
        // Exclude user content but maybe keep folder structure?
        // Usually distributables don't have user uploads. We'll rely on install to create them or include empty.
        // Let's include the empty folders via gitkeep if they exist, or just filter files.
        // If we want fresh install, we shouldn't have old uploads.
        if (strpos($relativePath, 'images/uploads/') === 0 && basename($relativePath) !== '.gitkeep') continue;
        if (strpos($relativePath, 'team_logos/') === 0 && basename($relativePath) !== '.gitkeep') continue;
        if (strpos($relativePath, 'person_pictures/') === 0 && basename($relativePath) !== '.gitkeep') continue;
        
        // Exclude config/database.php so we don't overwrite user's DB config? 
        // Or include it as sample? 
        // For a DISTRIB zip, usually we include `config/database.sample.php` and NOT `config/database.php`.
        // Let's check if we have database.php and rename it inside zip to sample if sample doesn't exist?
        // Or just exclude database.php if we assume installer creates it.
        if ($relativePath === 'config/database.php') {
             // Check if sample exists, if not, maybe add this as sample?
             // But valid dist should probably have database.sample.php
             continue; 
        }

        $zip->addFile($filePath, $relativePath);
    }
}

// Add empty folders explicitly if needed (ZipArchive doesn't store empty dirs easily without files)
// We rely on .gitkeep usually.

$zip->close();

echo "Done! Created: " . basename($zipFile) . " (" . filesize($zipFile) . " bytes)\n";
?>
