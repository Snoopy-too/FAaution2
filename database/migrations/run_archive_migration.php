<?php
/**
 * Run Archive Migration
 * Execute this file once to add archive support to the database
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "Starting archive migration...\n\n";
    
    // Create archives table
    echo "Creating archives table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archives (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            player_count INT DEFAULT 0,
            bid_count INT DEFAULT 0,
            created_by INT,
            FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL,
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Archives table created\n\n";
    
    // Check if archive_id column already exists
    echo "Checking if archive_id column exists...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM players LIKE 'archive_id'");
    if ($stmt->rowCount() == 0) {
        echo "Adding archive_id column to players table...\n";
        $pdo->exec("
            ALTER TABLE players 
            ADD COLUMN archive_id INT DEFAULT NULL AFTER id,
            ADD INDEX idx_archive_id (archive_id),
            ADD FOREIGN KEY (archive_id) REFERENCES archives(id) ON DELETE CASCADE
        ");
        echo "✓ archive_id column added\n\n";
    } else {
        echo "✓ archive_id column already exists\n\n";
    }
    
    echo "Migration completed successfully!\n";
    echo "\nAll players with archive_id = NULL are considered 'active' (current class).\n";
    echo "Archived players have archive_id set to their archive's ID.\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
