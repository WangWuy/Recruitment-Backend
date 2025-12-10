-- Add Google OAuth fields to users table
-- Run this SQL in your database

ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email,
ADD COLUMN photo_url TEXT NULL AFTER google_id,
ADD INDEX idx_google_id (google_id);

-- Make password_hash nullable for Google users
ALTER TABLE users 
MODIFY COLUMN password_hash VARCHAR(255) NULL;

-- Update existing users to ensure they have password_hash
-- (Google users won't have password_hash)
