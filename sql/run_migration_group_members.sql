-- Migration script to add group_members column to existing od_requests table
-- Run this script if the group members are not showing up

USE event_management_system;

-- Check if column exists and add it if not
ALTER TABLE od_requests 
ADD COLUMN IF NOT EXISTS group_members TEXT NULL COMMENT 'Comma-separated registration numbers for group OD requests' AFTER reason;

-- Show the table structure to verify
DESCRIBE od_requests;

-- Show sample data to verify group_members column
SELECT id, student_regno, event_name, group_members FROM od_requests LIMIT 5;
