-- Add approval columns to internship_submissions table
-- Run this SQL in phpMyAdmin to add the missing columns

ALTER TABLE `internship_submissions`
ADD COLUMN `approval_status` VARCHAR(20) DEFAULT 'pending' AFTER `submission_date`,
ADD COLUMN `counselor_remarks` TEXT NULL AFTER `approval_status`,
ADD COLUMN `approved_by` INT NULL AFTER `counselor_remarks`,
ADD COLUMN `approval_date` DATETIME NULL AFTER `approved_by`,
ADD INDEX `idx_approval_status` (`approval_status`);

-- Update any existing records to have 'pending' status
UPDATE `internship_submissions` SET `approval_status` = 'pending' WHERE `approval_status` IS NULL;
