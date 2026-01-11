-- Migration: Add email verification and password reset functionality
-- Run this on existing databases to add the new features

-- Add email verification columns to members table
ALTER TABLE members ADD COLUMN email_verification_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE members ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL;

-- Create rate limiting table
CREATE TABLE IF NOT EXISTS registration_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create password reset tokens table
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

-- Add email settings (use ON DUPLICATE KEY to avoid errors if already exist)
INSERT INTO settings (setting_key, setting_value) VALUES
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', ''),
('smtp_from_name', ''),
('registration_rate_limit_count', '5'),
('registration_rate_limit_minutes', '60')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Mark existing active users as verified (they were created before email verification)
UPDATE members SET email_verified_at = created_at WHERE is_active = 1 AND email_verified_at IS NULL;
