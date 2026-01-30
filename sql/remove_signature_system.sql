-- SQL to remove digital signature system from database
-- Run this in your MySQL database to completely remove signature functionality

-- Step 1: Remove foreign key constraint from od_requests table if it exists
-- Check if constraint exists first
SET @constraint_exists = (SELECT COUNT(*) 
  FROM information_schema.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'od_requests' 
  AND CONSTRAINT_NAME = 'od_requests_ibfk_2');

SET @sql = IF(@constraint_exists > 0, 
  'ALTER TABLE od_requests DROP FOREIGN KEY od_requests_ibfk_2', 
  'SELECT "Constraint does not exist, skipping"');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Remove all signature-related columns from od_requests table
ALTER TABLE od_requests 
DROP COLUMN IF EXISTS signature_id,
DROP COLUMN IF EXISTS digital_signature_hash,
DROP COLUMN IF EXISTS signature_timestamp,
DROP COLUMN IF EXISTS signature_verification_code;

-- Step 3: Drop the teacher_signatures table completely
DROP TABLE IF EXISTS teacher_signatures;

-- Verification: You can run these queries to confirm the changes
-- SHOW COLUMNS FROM od_requests WHERE Field LIKE '%signature%';
-- SHOW TABLES LIKE 'teacher_signatures';

-- Status: ✅ COMPLETED
-- All digital signature functionality has been removed from the database
