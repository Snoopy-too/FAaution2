-- OOTP Baseball Free Agent Auction App - Database Schema
-- Database: fa_auction2

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Teams table
CREATE TABLE IF NOT EXISTS teams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    available_money DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Members table
CREATE TABLE IF NOT EXISTS members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    team_id INT DEFAULT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    email_verification_token VARCHAR(64) DEFAULT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate limiting for registration attempts
CREATE TABLE IF NOT EXISTS registration_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Archives table
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

-- Players table
CREATE TABLE IF NOT EXISTS players (
    id INT PRIMARY KEY AUTO_INCREMENT,
    archive_id INT DEFAULT NULL,
    player_number INT UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    nickname VARCHAR(100) DEFAULT NULL,
    position TINYINT NOT NULL,
    day_of_birth TINYINT UNSIGNED DEFAULT NULL,
    month_of_birth TINYINT UNSIGNED DEFAULT NULL,
    year_of_birth SMALLINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (archive_id) REFERENCES archives(id) ON DELETE CASCADE,
    INDEX idx_archive_id (archive_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bids table
CREATE TABLE IF NOT EXISTS bids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    team_id INT NOT NULL,
    member_id INT NOT NULL,
    amount_per_year DECIMAL(15,2) NOT NULL,
    years INT NOT NULL,
    total_value DECIMAL(15,2) NOT NULL,
    is_opening_bid TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_player_total (player_id, total_value DESC),
    INDEX idx_team_bids (team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrations table (to track executed migrations)
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    batch INT NOT NULL,
    run_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('min_bid_increment_percent', '5'),
('max_contract_years', '5'),
('max_bids_per_player', '3'),
('deadline_type', 'manual'),
('deadline_datetime', NULL),
('auction_closed', '0'),
('player_images_path', 'person_pictures/'),
('player_html_path', 'player_pages/'),
('team_logos_path', 'team_logos/'),
('app_name', 'FA Auction'),
('league_name', ''),
('timezone', 'UTC'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', ''),
('smtp_from_name', ''),
('registration_rate_limit_count', '5'),
('registration_rate_limit_minutes', '60'),
('in_game_date', NULL)
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Insert default admin for testing (password: admin123)
-- This is optional and often removed in production wizards if the user creates their own admin
INSERT INTO members (email, password, name, is_admin, is_active) VALUES
('admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 1, 1)
ON DUPLICATE KEY UPDATE email = email;

-- Pre-fill migrations table to effectively "skip" the migrations that are already part of this schema
INSERT INTO migrations (migration, batch) VALUES
('001_email_verification.sql', 1),
('002_add_archive_support.sql', 1),
('003_add_player_birthdate.sql', 1)
ON DUPLICATE KEY UPDATE migration = migration;
