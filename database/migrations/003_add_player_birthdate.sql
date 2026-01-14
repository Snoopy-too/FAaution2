-- Migration: Add birth date columns to players table for age calculation
-- These columns store the day, month, and year of birth from OOTP CSV imports

ALTER TABLE players
    ADD COLUMN day_of_birth TINYINT UNSIGNED DEFAULT NULL AFTER position,
    ADD COLUMN month_of_birth TINYINT UNSIGNED DEFAULT NULL AFTER day_of_birth,
    ADD COLUMN year_of_birth SMALLINT UNSIGNED DEFAULT NULL AFTER month_of_birth;

-- Add the in-game date setting (for simulation leagues that may be set in past/future)
INSERT INTO settings (setting_key, setting_value) VALUES
    ('in_game_date', NULL)
ON DUPLICATE KEY UPDATE setting_key = setting_key;
