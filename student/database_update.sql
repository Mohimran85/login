-- SQL to ensure od_requests table has all required columns
-- Run this to update your database structure

-- Add event_days column if it doesn't exist
ALTER TABLE od_requests ADD COLUMN IF NOT EXISTS event_days INT DEFAULT 1;

-- Add event_poster column if it doesn't exist
ALTER TABLE od_requests ADD COLUMN IF NOT EXISTS event_poster VARCHAR(255) DEFAULT NULL;

-- Show current table structure
DESCRIBE od_requests;