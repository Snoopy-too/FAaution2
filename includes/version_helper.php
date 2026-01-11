<?php

/**
 * Get the current application version
 * 
 * @return string
 */
function getCurrentVersion() {
    $versionFile = __DIR__ . '/../version.txt';
    if (file_exists($versionFile)) {
        return trim(file_get_contents($versionFile));
    }
    return 'Unknown';
}

/**
 * Check if a new version is available
 * 
 * @param string $latestVersion The version string from the remote source
 * @return bool
 */
function isNewVersionAvailable($latestVersion) {
    if ($latestVersion === 'Unknown') return false;
    
    $currentVersion = getCurrentVersion();
    if ($currentVersion === 'Unknown') return false;
    
    return version_compare($latestVersion, $currentVersion, '>');
}
