<?php

class MigrationRunner {
    private $pdo;
    private $migrationsDir;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->migrationsDir = __DIR__ . '/../database/migrations/';
    }

    public function initTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                run_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function getRunMigrations() {
        $stmt = $this->pdo->query("SELECT migration FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function run() {
        $this->initTable();
        
        $ran = $this->getRunMigrations();
        $files = glob($this->migrationsDir . '*.sql');
        
        if (empty($files)) return ['success' => true, 'count' => 0];

        // Determine batch number
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM migrations");
        $batch = ((int) $stmt->fetchColumn()) + 1;

        $count = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $ran)) continue;

            $sql = file_get_contents($file);
            
            try {
                $this->pdo->beginTransaction();
                $this->pdo->exec($sql);
                
                $stmt = $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                $stmt->execute([$filename, $batch]);
                
                $this->pdo->commit();
                $count++;
            } catch (Exception $e) {
                $this->pdo->rollBack();
                return ['success' => false, 'error' => "Error in $filename: " . $e->getMessage()];
            }
        }

        return ['success' => true, 'count' => $count];
    }
}
