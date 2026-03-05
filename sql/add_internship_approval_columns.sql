-- Add approval columns to internship_submissions table
-- Run this SQL in phpMyAdmin to add the missing columns

ALTER TABLE `internship_submissions`
ADD COLUMN IF NOT EXISTS `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER `submission_date`,
ADD COLUMN IF NOT EXISTS `counselor_remarks` TEXT NULL AFTER `approval_status`,
ADD COLUMN IF NOT EXISTS `approved_by` INT NULL AFTER `counselor_remarks`,
ADD COLUMN IF NOT EXISTS `approval_date` DATETIME NULL AFTER `approved_by`;

-- Update any existing records to have 'pending' status
UPDATE `internship_submissions` SET `approval_status` = 'pending' WHERE `approval_status` IS NULL;
