<?php

class UpdateChecker {
    private $versionFile;
    private $cacheFile;
    private $githubRepo;
    private $cacheDuration = 86400; // 24 hours

    public function __construct() {
        $this->versionFile = __DIR__ . '/../version.txt';
        $this->cacheFile = __DIR__ . '/../cache/update_check.json';
        $this->githubRepo = 'Snoopy-too/FAaution2';
    }

    public function getCurrentVersion() {
        return file_exists($this->versionFile) 
            ? trim(file_get_contents($this->versionFile)) 
            : 'Unknown';
    }

    public function checkForUpdates($force = false) {
        // Check cache first
        if (!$force && file_exists($this->cacheFile)) {
            $cacheAge = time() - filemtime($this->cacheFile);
            if ($cacheAge < $this->cacheDuration) {
                $cached = json_decode(file_get_contents($this->cacheFile), true);
                if ($cached) return $cached;
            }
        }

        // Fetch from GitHub
        $updateInfo = $this->fetchFromGitHub();
        
        // Cache result
        $this->cacheResult($updateInfo);

        return $updateInfo;
    }

    private function fetchFromGitHub() {
        $url = "https://api.github.com/repos/{$this->githubRepo}/releases/latest";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: FA-Auction-App',
                    'Accept: application/vnd.github.v3+json'
                ],
                'timeout' => 5
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if (!$response) {
            return ['available' => false, 'error' => 'Failed to connect to GitHub'];
        }

        $data = json_decode($response, true);
        if (!isset($data['tag_name'])) {
            return ['available' => false, 'error' => 'Invalid response from GitHub'];
        }

        $latestVersion = ltrim($data['tag_name'], 'v');
        $currentVersion = $this->getCurrentVersion();

        return [
            'available' => version_compare($latestVersion, $currentVersion, '>'),
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'download_url' => $data['zipball_url'] ?? null,
            'changelog' => $data['body'] ?? '',
            'published_at' => $data['published_at'] ?? null,
            'checked_at' => time()
        ];
    }

    private function cacheResult($data) {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($this->cacheFile, json_encode($data));
    }
}
