-- Archive Feature Migration
-- Run this SQL to add archive support to the application

-- Create archives table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add archive_id column to players table
ALTER TABLE players 
ADD COLUMN archive_id INT DEFAULT NULL AFTER id,
ADD INDEX idx_archive_id (archive_id),
ADD FOREIGN KEY (archive_id) REFERENCES archives(id) ON DELETE CASCADE;

-- Note: All players with archive_id = NULL are considered "active" (current class)
-- Archived players have archive_id set to their archive's ID
